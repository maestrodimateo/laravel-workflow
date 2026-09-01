<?php

namespace Maestrodimateo\Workflow\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves the designer's vendored front-end assets (compiled Tailwind, Alpine,
 * Quill) from the package itself, same-origin.
 *
 * This removes the runtime CDN dependency so the admin UI keeps working
 * offline / air-gapped and under a strict Content-Security-Policy. Only the
 * whitelisted filenames below are served; anything else 404s.
 */
class AssetController
{
    /** @var array<string, string> Whitelisted file => MIME type */
    private const ASSETS = [
        'app.css' => 'text/css',
        'quill.snow.css' => 'text/css',
        'quill.js' => 'text/javascript',
        'alpine.min.js' => 'text/javascript',
    ];

    public function __invoke(string $file): BinaryFileResponse
    {
        abort_unless(isset(self::ASSETS[$file]), 404);

        return response()->file(__DIR__.'/../../resources/dist/'.$file, [
            'Content-Type' => self::ASSETS[$file],
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
