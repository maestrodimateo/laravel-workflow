<?php

use Illuminate\Support\Facades\Route;

// ---------------------------------------------------------------------------
// Route surface — apiResource conversion must preserve verbs, URIs and names.
// ---------------------------------------------------------------------------

/** @return array{0: string, 1: array<int, string>}|null [uri, methods] for a named route */
function routeInfo(string $name): ?array
{
    $route = Route::getRoutes()->getByName($name);

    return $route ? [$route->uri(), $route->methods()] : null;
}

it('registers the admin circuits resource (full CRUD)', function () {
    expect(routeInfo('workflow.admin.circuits.index'))->toBe(['workflow/admin/api/circuits', ['GET', 'HEAD']]);
    expect(routeInfo('workflow.admin.circuits.store')[0])->toBe('workflow/admin/api/circuits');
    expect(routeInfo('workflow.admin.circuits.show')[0])->toBe('workflow/admin/api/circuits/{circuit}');
    expect(routeInfo('workflow.admin.circuits.destroy')[1])->toContain('DELETE');
});

it('registers baskets and nested messages as partial resources', function () {
    // baskets: only store/update/destroy — no index/show
    expect(routeInfo('workflow.admin.baskets.index'))->toBeNull();
    expect(routeInfo('workflow.admin.baskets.update')[0])->toBe('workflow/admin/api/baskets/{basket}');

    // messages: nested + scoped, only store/update/destroy — no show from the resource
    expect(routeInfo('workflow.admin.circuits.messages.store')[0])
        ->toBe('workflow/admin/api/circuits/{circuit}/messages');
    expect(routeInfo('workflow.admin.circuits.messages.show'))->toBeNull();
    // the list (index) is served by the custom WorkflowAdminController@messages route
    expect(routeInfo('workflow.admin.circuits.messages.index'))
        ->toBe(['workflow/admin/api/circuits/{circuit}/messages', ['GET', 'HEAD']]);
});

it('keeps the designer-specific custom routes', function () {
    expect(routeInfo('workflow.admin.circuits.positions'))
        ->toBe(['workflow/admin/api/circuits/{circuit}/positions', ['PATCH']]);
    expect(routeInfo('workflow.admin.circuits.baskets')[0])->toBe('workflow/admin/api/circuits/{circuit}/baskets');
    expect(routeInfo('workflow.admin.transitions.update')[0])->toBe('workflow/admin/api/transitions/{from}/{to}');
    expect(routeInfo('workflow.admin.circuits.import')[1])->toContain('POST');
});

it('registers the public API resources under the workflow prefix', function () {
    expect(routeInfo('workflow.circuits.index')[0])->toBe('workflow/circuits');
    expect(routeInfo('workflow.baskets.update')[0])->toBe('workflow/baskets/{basket}');
    expect(routeInfo('workflow.circuits.messages.store')[0])->toBe('workflow/circuits/{circuit}/messages');
});
