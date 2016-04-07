<?php namespace App\Providers;

use Session;
use Auth;
use Utils;
use HTML;
use Form;
use URL;
use Request;
use Validator;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Vendor;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider {

	/**
	 * Bootstrap any application services.
	 *
	 * @return void
	 */
	public function boot()
	{
				require app_path('Helpers/helpers.php');
				require app_path('Helpers/macros.php');
	}

	/**
	 * Register any application services.
	 *
	 * This service provider is a great spot to register your various container
	 * bindings with the application. As you can see, we are registering our
	 * "Registrar" implementation here. You can add your own bindings too!
	 *
	 * @return void
	 */
	public function register()
	{
		$this->app->bind(
			'Illuminate\Contracts\Auth\Registrar',
			'App\Services\Registrar'
		);
	}

}
