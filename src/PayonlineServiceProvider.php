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
		$this->bootConfig();
	}

	/**
	 * Booting configure.
	 */
	protected function bootConfig() {
		$path = __DIR__.'/config/payonline.php';

		$this->mergeConfigFrom($path, 'payonline');

		if (function_exists('config_path')) {
			$this->publishes([$path => config_path('payonline.php')]);
		}
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