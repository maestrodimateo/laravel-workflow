<?php

namespace Maestrodimateo\Workflow;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Maestrodimateo\Workflow\Actions\LogTransitionAction;
use Maestrodimateo\Workflow\Actions\RequireDocumentAction;
use Maestrodimateo\Workflow\Actions\SendEmailAction;
use Maestrodimateo\Workflow\Actions\WebhookAction;
use Maestrodimateo\Workflow\Console\MakeTransitionActionCommand;
use Maestrodimateo\Workflow\Repositories\BasketRepository;

class WorkflowServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/workflow.php', 'workflow');

        $this->app->singleton(WorkflowManager::class, fn () => new WorkflowManager(new BasketRepository));

        $this->app->register(WorkflowEventServiceProvider::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'workflow');
        $this->loadRoutes();
        $this->registerBuiltInActions();

        if ($this->app->runningInConsole()) {
            $this->commands([MakeTransitionActionCommand::class]);

            $this->publishes([
                __DIR__.'/../config/workflow.php' => config_path('workflow.php'),
            ], 'workflow-config');

            $this->publishes([
                __DIR__.'/../database/migrations/' => database_path('migrations'),
            ], 'workflow-migrations');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/workflow'),
            ], 'workflow-views');
        }
    }

    private function registerBuiltInActions(): void
    {
        WorkflowManager::registerAction(SendEmailAction::class);
        WorkflowManager::registerAction(LogTransitionAction::class);
        WorkflowManager::registerAction(WebhookAction::class);
        WorkflowManager::registerAction(RequireDocumentAction::class);
    }

    private function loadRoutes(): void
    {
        $prefix = config('workflow.routes.prefix', 'workflow');

        // Public API routes
        Route::prefix($prefix)
            ->middleware(config('workflow.routes.middleware', ['api']))
            ->group(__DIR__.'/../routes/api.php');

        // Admin UI + Admin API (share web middleware for session-based auth)
        Route::prefix($prefix.'/admin')
            ->middleware(config('workflow.routes.admin_middleware', ['web']))
            ->group(__DIR__.'/../routes/admin.php');
    }
}
