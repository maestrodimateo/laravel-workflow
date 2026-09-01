<?php

namespace Maestrodimateo\Workflow;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Maestrodimateo\Workflow\Actions\LogTransitionAction;
use Maestrodimateo\Workflow\Controllers\AssetController;
use Maestrodimateo\Workflow\Actions\RequireDocumentAction;
use Maestrodimateo\Workflow\Actions\SendEmailAction;
use Maestrodimateo\Workflow\Actions\WebhookAction;
use Maestrodimateo\Workflow\Conditions\AttributeCondition;
use Maestrodimateo\Workflow\Console\MakeTransitionActionCommand;
use Maestrodimateo\Workflow\Events\TransitionEvent;
use Maestrodimateo\Workflow\Listeners\HistoryListener;

class WorkflowServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/workflow.php', 'workflow');

        $this->app->singleton(WorkflowManager::class, fn () => new WorkflowManager);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'workflow');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'workflow');
        Event::listen(TransitionEvent::class, HistoryListener::class);
        $this->registerAuthorization();
        $this->loadRoutes();
        $this->registerBuiltInActions();
        $this->registerBuiltInConditions();

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

            $this->publishes([
                __DIR__.'/../resources/lang' => $this->app->langPath('vendor/workflow'),
            ], 'workflow-lang');
        }
    }

    private function registerBuiltInActions(): void
    {
        WorkflowManager::registerAction(SendEmailAction::class);
        WorkflowManager::registerAction(LogTransitionAction::class);
        WorkflowManager::registerAction(WebhookAction::class);
        WorkflowManager::registerAction(RequireDocumentAction::class);

        foreach (config('workflow.actions', []) as $actionClass) {
            WorkflowManager::registerAction($actionClass);
        }
    }

    private function registerBuiltInConditions(): void
    {
        WorkflowManager::registerCondition(AttributeCondition::class);

        foreach (config('workflow.conditions', []) as $conditionClass) {
            WorkflowManager::registerCondition($conditionClass);
        }
    }

    /**
     * Define a safe default authorization gate when the application has not
     * provided its own. The default only grants access in the local
     * environment, keeping production locked down until the consumer defines
     * their own `manage-workflow` gate.
     */
    private function registerAuthorization(): void
    {
        $ability = config('workflow.authorization.gate');

        if ($ability && ! Gate::has($ability)) {
            Gate::define($ability, fn ($user = null): bool => $this->app->environment('local'));
        }
    }

    private function loadRoutes(): void
    {
        $prefix = config('workflow.routes.prefix', 'workflow');

        // Vendored front-end assets — public and same-origin (no auth gate),
        // so the login page's own designer chrome can load them too.
        Route::get($prefix.'/assets/{file}', AssetController::class)
            ->where('file', '[A-Za-z0-9._-]+')
            ->name('workflow.assets');

        // Authorization gate applied on top of every workflow route.
        $ability = config('workflow.authorization.gate');
        $authorize = $ability ? ['can:'.$ability] : [];

        // Public API routes
        Route::prefix($prefix)
            ->middleware([...config('workflow.routes.middleware', ['api', 'auth']), ...$authorize])
            ->group(__DIR__.'/../routes/api.php');

        // Admin UI + Admin API (share web middleware for session-based auth)
        Route::prefix($prefix.'/admin')
            ->middleware([...config('workflow.routes.admin_middleware', ['web', 'auth']), ...$authorize])
            ->group(__DIR__.'/../routes/admin.php');
    }
}
