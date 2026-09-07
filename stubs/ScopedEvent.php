<?php namespace Eventbrain\Cloner\Stubs;

use Eventbrain\Cloner\Cloneable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model as Eloquent;

/**
 * Mirrors a consumer model (eventbrain-app's Event) that carries a Laravel
 * global scope resolving its owning "team" through a parent relation rather than
 * a local column. A freshly created clone has no project yet, so the scope
 * filters it out until the clone tree is wired up.
 *
 * `project_id` is clone-exempt, so the clone starts life invisible to the scope.
 */
class ScopedEvent extends Eloquent {
	use Cloneable;

	protected $table = 'scoped_events';

	protected $guarded = [];

	public $cloneable_relations = ['project'];

	private $clone_exempt_attributes = ['project_id'];

	/**
	 * The team whose rows are currently visible. null disables the scope.
	 * @var int|null
	 */
	public static $currentTeamId = null;

	protected static function booted()
	{
		static::addGlobalScope('team', function (Builder $builder) {
			if (is_null(static::$currentTeamId)) return;

			$builder->whereHas('project', function (Builder $query) {
				$query->where('team_id', static::$currentTeamId);
			});
		});
	}

	public function project() {
		return $this->belongsTo(ScopedProject::class, 'project_id');
	}
}
