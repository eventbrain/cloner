<?php

namespace Eventbrain\Cloner\Tests;

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Minimal Laravel wiring (container + facades + in-memory sqlite) so the cloner
 * can be exercised end to end without pulling in orchestra/testbench.
 */
abstract class IntegrationTestCase extends TestCase
{
    protected Container $app;

    protected Capsule $capsule;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app = new Container();
        Container::setInstance($this->app);

        $this->app->instance('app', $this->app);
        $this->app->instance('config', new ConfigRepository([
            'cloner' => [
                'should_clone_media' => false,
                'trait_cloneable_relations' => [],
            ],
        ]));

        $events = new Dispatcher($this->app);
        $this->app->instance('events', $events);
        $this->app->instance('log', new NullLogger());
        $this->app->instance('cache', new CacheRepository(new ArrayStore()));

        $this->capsule = new Capsule($this->app);
        $this->capsule->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $this->capsule->setAsGlobal();
        $this->capsule->setEventDispatcher($events);
        $this->capsule->bootEloquent();

        $this->app->instance('db', $this->capsule->getDatabaseManager());
        $this->app->singleton('cloner', fn () => new \Eventbrain\Cloner\Cloner($events));

        Facade::setFacadeApplication($this->app);

        $this->migrate();
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Container::setInstance(null);
        Eloquent::unsetConnectionResolver();
        Eloquent::unsetEventDispatcher();

        parent::tearDown();
    }

    /**
     * Create the package tables plus whatever the concrete test needs.
     */
    protected function migrate(): void
    {
        $schema = $this->capsule->schema();

        $schema->create('model_clones', function ($table) {
            $table->id();
            $table->timestamps();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->json('additional_attributes')->nullable();
        });

        $schema->create('model_clone_progress', function ($table) {
            $table->id();
            $table->timestamps();
            $table->unsignedBigInteger('model_clone_id')->nullable();
            $table->string('model_type');
            $table->string('model_table')->nullable();
            $table->unsignedBigInteger('source_id');
            $table->unsignedBigInteger('clone_id');
            $table->unique(['model_type', 'clone_id']);
        });

        $schema->create('model_clone_exemptable', function ($table) {
            $table->id();
            $table->unsignedBigInteger('model_clone_id');
            $table->string('exemptable_type');
            $table->unsignedBigInteger('exemptable_id');
            $table->timestamps();
        });
    }
}
