<?php

// Deps
use Eventbrain\Cloner\Stubs\Article;
use Mockery as m;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

/**
 * Test the trait
 */
class CloneableTest extends TestCase {

    use MockeryPHPUnitIntegration;
    
	public function testDuplicate() {

		m::mock('alias:App', [
			'make' => m::mock('Eventbrain\Cloner\Cloner', [
				'duplicate' => m::mock('Eventbrain\Cloner\Stubs\Article'),
			])
		]);

		$article = new Article;
		$clone = $article->duplicate();
		$this->assertInstanceOf('Eventbrain\Cloner\Stubs\Article', $clone);
	}

	public function testDuplicateWithDifferentDB() {

		m::mock('alias:App', [
			'make' => m::mock('Eventbrain\Cloner\Cloner', [
				'duplicateTo' => m::mock('Eventbrain\Cloner\Stubs\Article'),
			])
		]);

		$article = new Article;
		$clone = $article->duplicateTo('connection');
		$this->assertInstanceOf('Eventbrain\Cloner\Stubs\Article', $clone);
	}

	public function testGetRelations() {
		$article = new Article;
		$this->assertEquals(['photos', 'authors', 'ratings'], $article->getCloneableRelations());
	}

	public function testAddRelation() {
		$article = new Article;
		$article->addCloneableRelation('test');
		$this->assertContains('test', $article->getCloneableRelations());
	}

	public function testAddDuplicateRelation() {
		$article = new Article;
		$article->addCloneableRelation('test');
		$article->addCloneableRelation('test');
		$this->assertEquals(['photos', 'authors', 'ratings', 'test'], $article->getCloneableRelations());
	}

	public function testCountColumnsAreExempt() {
	    $article = new Article;
	    $this->assertContains('photos_count', $article->getCloneExemptAttributes());
    }
}
