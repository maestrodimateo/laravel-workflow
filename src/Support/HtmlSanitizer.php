<?php

namespace Maestrodimateo\Workflow\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer as SymfonyHtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Whitelist-based HTML sanitizer for user-authored rich text (Quill output).
 *
 * Thin wrapper around symfony/html-sanitizer: keeps a safe subset of formatting
 * tags/attributes and strips everything else — <script>/<style>/<iframe> and
 * friends, event handler attributes (on*), and javascript:/data:/vbscript:
 * URLs. This neutralizes stored XSS both in transactional emails and in the
 * admin editor while preserving legitimate formatting.
 */
class HtmlSanitizer
{
    private static ?SymfonyHtmlSanitizer $sanitizer = null;

    /**
     * Return a sanitized copy of the given HTML string.
     */
    public static function clean(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        return trim(static::sanitizer()->sanitize($html));
    }

    /**
     * Build (once) the configured Symfony sanitizer.
     */
    private static function sanitizer(): SymfonyHtmlSanitizer
    {
        return static::$sanitizer ??= new SymfonyHtmlSanitizer(
            (new HtmlSanitizerConfig)
                // Safe formatting elements with their safe attributes; unsafe
                // elements (script, style, iframe, …) are dropped automatically.
                ->allowSafeElements()
                // Drop images entirely (avoids remote-content / tracking pixels
                // in emails) rather than keeping them.
                ->dropElement('img')
                // Links: only safe schemes, and hardened against tabnabbing.
                ->allowElement('a', ['href', 'title', 'target', 'rel'])
                ->allowLinkSchemes(['https', 'http', 'mailto', 'tel'])
                ->allowRelativeLinks()
                ->forceAttribute('a', 'rel', 'noopener noreferrer nofollow')
        );
    }
}
