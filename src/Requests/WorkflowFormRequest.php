<?php

namespace Maestrodimateo\Workflow\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Base request for all workflow write operations.
 *
 * Authorization is delegated to the configured `manage-workflow` Gate ability,
 * providing defense in depth on top of the route middleware. When the gate is
 * disabled (config value null), the request is authorized by default.
 */
abstract class WorkflowFormRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $ability = config('workflow.authorization.gate');

        return $ability ? Gate::allows($ability) : true;
    }
}
