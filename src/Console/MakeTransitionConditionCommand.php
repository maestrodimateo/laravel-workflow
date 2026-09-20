<?php

namespace Maestrodimateo\Workflow\Console;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;

class MakeTransitionConditionCommand extends GeneratorCommand
{
    protected $name = 'make:workflow-condition';

    protected $description = 'Create a new workflow transition condition class';

    protected $type = 'TransitionCondition';

    protected function getStub(): string
    {
        return __DIR__.'/stubs/transition-condition.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\\Workflow\\Conditions';
    }

    protected function buildClass($name): string
    {
        $class = parent::buildClass($name);

        $baseName = class_basename($name);

        // budget_approved from BudgetApprovedCondition
        $key = Str::of($baseName)
            ->replaceLast('Condition', '')
            ->snake()
            ->toString();

        // Budget Approved from BudgetApprovedCondition
        $label = Str::of($baseName)
            ->replaceLast('Condition', '')
            ->headline()
            ->toString();

        $model = $this->option('model')
            ? '\\App\\Models\\'.$this->option('model').'::class'
            : '';

        return str_replace(
            ['{{ key }}', '{{ label }}', '{{ model }}'],
            [$key, $label, $model],
            $class,
        );
    }

    protected function getOptions(): array
    {
        return [
            ['model', 'm', InputOption::VALUE_OPTIONAL, 'The target Eloquent model for this condition'],
        ];
    }
}