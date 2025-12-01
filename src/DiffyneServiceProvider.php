<?php

namespace Diffyne;

use Diffyne\Commands\MakeDiffyneCommand;
use Diffyne\Commands\DiffyneInstallCommand;
use Diffyne\Commands\DiffyneServeCommand;
use Diffyne\VirtualDOM\Renderer;
use Diffyne\State\ComponentHydrator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class DiffyneServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Merge package config
        $this->mergeConfigFrom(
            __DIR__ . '/../config/diffyne.php',
            'diffyne'
        );

        // Register singletons
        $this->app->singleton(Renderer::class, function ($app) {
            return new Renderer();
        });

        $this->app->singleton(ComponentHydrator::class, function ($app) {
            return new ComponentHydrator();
        });

        // Register Diffyne facade
        $this->app->singleton('diffyne', function ($app) {
            return new DiffyneManager($app);
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish config
        $this->publishes([
            __DIR__ . '/../config/diffyne.php' => config_path('diffyne.php'),
        ], 'diffyne-config');

        // Publish JavaScript assets
        $this->publishes([
            __DIR__ . '/../public/js/diffyne.js' => public_path('vendor/diffyne/diffyne.js'),
        ], 'diffyne-assets');

        // Publish views
        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/diffyne'),
        ], 'diffyne-views');

        // Load views
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'diffyne');

        // Register routes
        $this->registerRoutes();

        // Register Blade directives
        $this->registerBladeDirectives();

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeDiffyneCommand::class,
                DiffyneInstallCommand::class,
                DiffyneServeCommand::class,
            ]);
        }
    }

    /**
     * Register Diffyne routes.
     */
    protected function registerRoutes(): void
    {
        Route::group([
            'prefix' => config('diffyne.route_prefix', '_diffyne'),
            'middleware' => config('diffyne.middleware', ['web']),
        ], function () {
            $this->loadRoutesFrom(__DIR__ . '/../routes/diffyne.php');
        });
    }

    /**
     * Register custom Blade directives.
     */
    protected function registerBladeDirectives(): void
    {
        // @diffyne directive for rendering components
        Blade::directive('diffyne', function ($expression) {
            return "<?php echo app('diffyne')->mount({$expression}); ?>";
        });

        // @diffyneScripts directive for including JS
        Blade::directive('diffyneScripts', function () {
            return "<?php echo view('diffyne::scripts'); ?>";
        });

        // @diffyneStyles directive for including CSS (if needed)
        Blade::directive('diffyneStyles', function () {
            return '<!-- Diffyne styles -->';
        });
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return ['diffyne', Renderer::class, ComponentHydrator::class];
    }
}
