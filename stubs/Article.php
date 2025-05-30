<?php namespace Eventbrain\Cloner\Stubs;

use Eventbrain\Cloner\Cloneable as Cloneable;
use Illuminate\Database\Eloquent\Model as Eloquent;

class Article extends Eloquent {
	use Cloneable;

	protected $withCount = ['photos'];

	public $cloneable_relations = ['photos', 'authors', 'ratings'];

	public function photos() {
		return $this->hasMany('Eventbrain\Cloner\Stubs\Photo');
	}

	public function authors() {
		return $this->belongsToMany('Eventbrain\Cloner\Stubs\Author');
	}

	public function ratings() {
		return $this->belongsToMany(User::class)->withPivot('rating')->withTimestamps();
	}
}