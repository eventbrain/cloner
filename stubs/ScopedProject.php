<?php namespace Eventbrain\Cloner\Stubs;

use Eventbrain\Cloner\Cloneable;
use Illuminate\Database\Eloquent\Model as Eloquent;

/**
 * Mirrors eventbrain-app's Project: it sits between two ScopedEvent nodes and
 * closes the relation cycle (event -> project -> projectable -> event). It has
 * no global scope of its own.
 */
class ScopedProject extends Eloquent {
	use Cloneable;

	protected $table = 'scoped_projects';

	protected $guarded = [];

	public $cloneable_relations = ['projectable'];

	public function projectable() {
		return $this->morphTo();
	}
}
