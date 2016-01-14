<?php

namespace Laravel\Payonline;

use Illuminate\Support\ServiceProvider;

class PayonlineServiceProvider extends ServiceProvider {

	/**
	 * Bootstrap the application events.
	 *
	 * @return void
	 */
	public function boot() {
		$this->loadViewsFrom(__DIR__.'/../resources/views', 'payonline');

		$this->publishes([
			__DIR__.'/../resources/views' => base_path('resources/views/vendor/payonline'),
		]);
	}

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register() {
		//
	}
}