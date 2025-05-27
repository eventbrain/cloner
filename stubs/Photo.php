<?php namespace Eventbrain\Cloner\Stubs;

use Eventbrain\Cloner\Cloneable as Cloneable;
use Illuminate\Database\Eloquent\Model as Eloquent;

class Photo extends Eloquent {
	use Cloneable;

	private $clone_exempt_attributes = ['uid', 'source'];
	private $cloneable_file_attributes = ['image'];

	public function article() {
		return $this->belongsTo('Eventbrain\Cloner\Stubs\Article');
	}

	public function onCloning() {
		$this->uid = 2;
	}
}