<?php

namespace Eventbrain\Cloner\Tests;

use Eventbrain\Cloner\Cloner;
use Eventbrain\Cloner\Models\ModelClone;
use Eventbrain\Cloner\Models\ModelCloneProgress;
use Eventbrain\Cloner\Stubs\ScopedEvent;
use Eventbrain\Cloner\Stubs\ScopedProject;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Cache;

/**
 * Regression coverage for clone-progress bookkeeping that must never be filtered
 * by a consumer's Eloquent global scopes.
 *
 * @see \Eventbrain\Cloner\Cloner::fetchExistingClone()
 * @see \Eventbrain\Cloner\Cloner::isClone()
 */
class ClonerScopedBookkeepingTest extends IntegrationTestCase
{
    private const TEAM_ID = 7;

    protected function migrate(): void
    {
        parent::migrate();

        $schema = $this->capsule->schema();

        $schema->create('scoped_projects', function ($table) {
            $table->id();
            $table->timestamps();
            $table->string('name')->nullable();
            $table->unsignedBigInteger('team_id')->nullable();
            $table->string('projectable_type')->nullable();
            $table->unsignedBigInteger('projectable_id')->nullable();
        });

        $schema->create('scoped_events', function ($table) {
            $table->id();
            $table->timestamps();
            $table->string('name')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
        });
    }

    protected function setUp(): void
    {
        parent::setUp();
        ScopedEvent::$currentTeamId = self::TEAM_ID;
    }

    protected function tearDown(): void
    {
        ScopedEvent::$currentTeamId = null;
        parent::tearDown();
    }

    private function cloner(): Cloner
    {
        return new Cloner($this->app->make('events'));
    }

    private function makeModelClone(): ModelClone
    {
        return ModelClone::create([
            'user_id' => 1,
            'additional_attributes' => ['exempted_classes' => []],
        ]);
    }

    /**
     * event -> project -> projectable -> (same event). The global scope on
     * ScopedEvent hides any event whose project is not resolvable, which every
     * freshly created clone is until the tree is wired up. If the "already
     * cloned?" lookup honoured that scope, the cyclic walk would clone each row
     * forever and overflow the stack.
     */
    public function testDuplicateTerminatesOnCyclicGraphWithHidingGlobalScope()
    {
        $event = ScopedEvent::create(['name' => 'source event']);
        $project = ScopedProject::create(['name' => 'source project', 'team_id' => self::TEAM_ID]);
        $event->project()->associate($project)->save();
        $project->projectable()->associate($event)->save();

        // Sanity: source rows are visible under the scope, and the cycle exists.
        $this->assertSame(1, ScopedEvent::count());
        $this->assertTrue($project->is($event->fresh()->project));

        $modelClone = $this->makeModelClone();

        $clone = $this->cloner()->duplicate($event, null, null, $modelClone);

        // Exactly one clone per source row - no duplicates, no runaway recursion.
        $this->assertSame(2, ScopedEvent::withoutGlobalScopes()->count());
        $this->assertSame(2, ScopedProject::withoutGlobalScopes()->count());
        $this->assertSame(2, ModelCloneProgress::count());

        $eventProgress = ModelCloneProgress::where('model_table', 'scoped_events')->firstOrFail();
        $this->assertSame($event->getKey(), (int) $eventProgress->source_id);
        $this->assertSame($clone->getKey(), (int) $eventProgress->clone_id);
        $this->assertNotSame((int) $eventProgress->source_id, (int) $eventProgress->clone_id);

        // The clone points at the cloned project, and that project points back at
        // the cloned event - the cycle is reproduced, not re-pointed at sources.
        $clonedProject = ScopedProject::withoutGlobalScopes()->findOrFail($clone->project_id);
        $this->assertNotSame($project->getKey(), $clonedProject->getKey());
        $this->assertSame($clone->getKey(), (int) $clonedProject->projectable_id);
        $this->assertSame(ScopedEvent::class, $clonedProject->projectable_type);

        // Once wired up, the clone resolves its team and is visible under the scope again.
        $this->assertNotNull(ScopedEvent::find($clone->getKey()));
        $this->assertSame(2, ScopedEvent::count());
    }

    /**
     * The DB fallback branch of fetchExistingClone() must load the clone row
     * without the target model's global scopes - the plain `->clone` morphTo
     * accessor would apply them and hide a not-yet-wired clone.
     */
    public function testFetchExistingCloneDbBranchIgnoresTargetGlobalScope()
    {
        $source = ScopedEvent::create(['name' => 'source']);

        // A clone row with no project yet: hidden by the team scope.
        $hiddenClone = ScopedEvent::create(['name' => 'clone']);
        $this->assertNull(ScopedEvent::find($hiddenClone->getKey()));
        $this->assertNotNull(ScopedEvent::withoutGlobalScopes()->find($hiddenClone->getKey()));

        $modelClone = $this->makeModelClone();
        ModelCloneProgress::create([
            'model_clone_id' => $modelClone->getKey(),
            'model_type' => ScopedEvent::class,
            'model_table' => 'scoped_events',
            'source_id' => $source->getKey(),
            'clone_id' => $hiddenClone->getKey(),
        ]);

        // Force the DB branch (no cache entry).
        Cache::tags('eb-cloner')->flush();

        $cloner = $this->cloner();
        $ref = new \ReflectionClass(Cloner::class);
        $prop = $ref->getProperty('modelClone');
        $prop->setAccessible(true);
        $prop->setValue($cloner, $modelClone);

        $method = $ref->getMethod('fetchExistingClone');
        $method->setAccessible(true);

        $resolved = $method->invoke($cloner, $source);

        $this->assertNotNull($resolved, 'DB fallback must resolve a scope-hidden clone row');
        $this->assertSame($hiddenClone->getKey(), $resolved->getKey());
    }

    /**
     * saveCloneProcess() writes progress keys tagged "eb-cloner"; fetchExistingClone()
     * must read them the same way. If the tags drift apart the cache fast-path
     * silently never hits.
     */
    public function testCacheFastPathHitsAfterSaveCloneProcess()
    {
        $event = ScopedEvent::create(['name' => 'source event']);
        $project = ScopedProject::create(['name' => 'source project', 'team_id' => self::TEAM_ID]);
        $event->project()->associate($project)->save();
        $project->projectable()->associate($event)->save();

        $modelClone = $this->makeModelClone();
        $clone = $this->cloner()->duplicate($event, null, null, $modelClone);

        $cacheKey = "cloner-{$modelClone->getKey()}-scoped_events-{$event->getKey()}";
        $this->assertTrue(
            Cache::tags('eb-cloner')->has($cacheKey),
            'saveCloneProcess() must write a readable tagged cache entry'
        );
        $this->assertSame($clone->getKey(), (int) Cache::tags('eb-cloner')->get($cacheKey));

        // A second duplicate() served entirely from the cache fast-path returns
        // the same clone and creates no extra rows.
        $again = $this->cloner()->duplicate($event, null, null, $modelClone);
        $this->assertSame($clone->getKey(), $again->getKey());
        $this->assertSame(2, ScopedEvent::withoutGlobalScopes()->count());
        $this->assertSame(2, ModelCloneProgress::count());
    }
}
