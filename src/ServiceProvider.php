<?php namespace Eventbrain\Cloner;

// Deps
use Illuminate\Support\ServiceProvider as LaravelServiceProvider;

/**
 * Bootstrap the package for Laravel
 */
class ServiceProvider extends LaravelServiceProvider {

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register() {
		// Instantiate main Cloner instance
		$this->app->singleton('cloner', function($app) {
			return new Cloner(
				$app['events']
			);
		});

		$this->publishes([
			__DIR__.'/../config/cloner.php' => config_path('cloner.php'),
		], "cloner-config");

		$this->publishes([
			__DIR__.'/../database/migrations/' => database_path('migrations/cloner'),
		], "cloner-migrations");

		$this->mergeConfigFrom(
			__DIR__.'/../config/cloner.php', 'cloner'
		);
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides() {
		return [
			'cloner',
		];
	}

	public function boot() {
		$this->loadMigrationsFrom(__DIR__.'/../database/migrations');
	}

}
