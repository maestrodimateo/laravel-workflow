<?php

namespace Maestrodimateo\Workflow\Console;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;

class MakeTransitionActionCommand extends GeneratorCommand
{
    protected $name = 'make:workflow-action';

    protected $description = 'Create a new workflow transition action class';

    protected $type = 'TransitionAction';

    protected function getStub(): string
    {
        return __DIR__.'/stubs/transition-action.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\\Actions';
    }

    protected function buildClass($name): string
    {
        $class = parent::buildClass($name);

        $baseName = class_basename($name);

        // generate_pdf from GeneratePdfAction
        $key = Str::of($baseName)
            ->replaceLast('Action', '')
            ->snake()
            ->toString();

        // Generate PDF from GeneratePdfAction
        $label = Str::of($baseName)
            ->replaceLast('Action', '')
            ->headline()
            ->toString();

        return str_replace(
            ['{{ key }}', '{{ label }}'],
            [$key, $label],
            $class,
        );
    }
}
