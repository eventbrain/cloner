<?php namespace Eventbrain\Cloner\Stubs;

use Eventbrain\Cloner\Cloneable as Cloneable;

class Image extends Photo {
	use Cloneable;

    public $cloneable_relations = ['article'];

	protected $table = 'photos';

    public function onCloning() {
        $this->uid = 2;
    }
}
