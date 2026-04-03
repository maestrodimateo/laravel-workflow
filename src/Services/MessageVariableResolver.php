<?php

namespace Maestrodimateo\Workflow\Services;

use Illuminate\Database\Eloquent\Model;
use Maestrodimateo\Workflow\Models\Basket;

class MessageVariableResolver
{
    /**
     * Replace {{ variable }} placeholders in a string with resolved values.
     */
    public static function resolve(string $content, Model $model, Basket $from, Basket $to): string
    {
        $variables = static::getVariables($model, $from, $to);

        // Replace {{ key }} patterns
        return preg_replace_callback('/\{\{\s*(\w+)\s*\}\}/', function ($matches) use ($variables) {
            $key = $matches[1];

            return $variables[$key] ?? $matches[0]; // Keep original if unknown
        }, $content);
    }

    /**
     * Get all resolved variables for a given context.
     */
    public static function getVariables(Model $model, Basket $from, Basket $to): array
    {
        // Built-in variables (always available)
        $builtIn = [
            'model_id' => (string) $model->getKey(),
            'model_type' => class_basename($model),
            'from_status' => $from->status,
            'from_name' => $from->name,
            'to_status' => $to->status,
            'to_name' => $to->name,
            'circuit_name' => $from->circuit?->name ?? '',
            'date' => now()->format('d/m/Y'),
            'heure' => now()->format('H:i'),
            'datetime' => now()->format('d/m/Y H:i'),
            'user' => auth()->user()?->name ?? auth()->user()?->{config('workflow.auth_identifier', 'id')} ?? 'Système',
        ];

        // User-defined variables from config
        $custom = [];
        foreach (config('workflow.message_variables', []) as $key => $resolver) {
            if (is_callable($resolver)) {
                try {
                    $custom[$key] = (string) $resolver($model, $from, $to);
                } catch (\Throwable) {
                    $custom[$key] = '';
                }
            }
        }

        return array_merge($builtIn, $custom);
    }

    /**
     * Get the list of available variable keys (for the admin UI).
     */
    public static function availableKeys(): array
    {
        $builtIn = [
            'model_id' => 'Identifiant du modèle',
            'model_type' => 'Type du modèle',
            'from_status' => 'Statut de départ',
            'from_name' => 'Nom du panier de départ',
            'to_status' => "Statut d'arrivée",
            'to_name' => "Nom du panier d'arrivée",
            'circuit_name' => 'Nom du circuit',
            'date' => 'Date du jour (JJ/MM/AAAA)',
            'heure' => 'Heure (HH:MM)',
            'datetime' => 'Date et heure',
            'user' => 'Utilisateur qui effectue la transition',
        ];

        $custom = [];
        foreach (array_keys(config('workflow.message_variables', [])) as $key) {
            $custom[$key] = $key;
        }

        return array_merge($builtIn, $custom);
    }
}
