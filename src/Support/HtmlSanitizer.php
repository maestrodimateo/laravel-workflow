<?php

namespace Maestrodimateo\Workflow\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use DOMXPath;

/**
 * Whitelist-based HTML sanitizer for user-authored rich text (Quill output).
 *
 * Keeps a safe subset of formatting tags/attributes and strips everything
 * else: <script>/<style>/<iframe> and friends are removed with their content,
 * event handler attributes (on*) are dropped, and javascript:/data:/vbscript:
 * URLs are rejected. This neutralizes stored XSS both in transactional emails
 * and in the admin editor while preserving legitimate formatting.
 */
class HtmlSanitizer
{
    /** Tags kept as-is (attributes still filtered). */
    private const ALLOWED_TAGS = [
        'p', 'br', 'b', 'strong', 'i', 'em', 'u', 's', 'strike', 'a', 'ul', 'ol',
        'li', 'blockquote', 'pre', 'code', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'span', 'div', 'sub', 'sup', 'hr',
    ];

    /** Tags removed together with their whole subtree. */
    private const DROP_TAGS = [
        'script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button',
        'textarea', 'select', 'base', 'link', 'meta', 'svg', 'math', 'template',
        'noscript', 'title', 'head', 'audio', 'video', 'source', 'img',
    ];

    /** Attributes allowed per tag. */
    private const ALLOWED_ATTRIBUTES = [
        'a' => ['href', 'title', 'target', 'rel'],
        'span' => ['class'],
        'div' => ['class'],
        'p' => ['class'],
        'code' => ['class'],
        'pre' => ['class'],
        'ol' => ['class'],
        'ul' => ['class'],
        'li' => ['class'],
        'blockquote' => ['class'],
    ];

    /** URL schemes accepted in href attributes. */
    private const ALLOWED_URL_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    /**
     * Return a sanitized copy of the given HTML string.
     */
    public static function clean(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);

        $dom->loadHTML(
            '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head>'
            .'<body><div id="__wf_root__">'.$html.'</div></body></html>',
            LIBXML_NOERROR | LIBXML_NOWARNING
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = (new DOMXPath($dom))->query('//div[@id="__wf_root__"]')->item(0);

        if (! $root instanceof DOMElement) {
            return '';
        }

        static::sanitizeChildren($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $dom->saveHTML($child);
        }

        return trim($out);
    }

    /**
     * Recursively sanitize the children of a node in place.
     */
    private static function sanitizeChildren(DOMNode $node): void
    {
        // Snapshot the list first: the tree is mutated during iteration.
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMText) {
                continue;
            }

            if (! $child instanceof DOMElement) {
                // Comments, CDATA, processing instructions… drop them.
                $child->parentNode?->removeChild($child);

                continue;
            }

            $tag = strtolower($child->nodeName);

            if (in_array($tag, self::DROP_TAGS, true)) {
                $child->parentNode?->removeChild($child);

                continue;
            }

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                // Unknown but harmless tag: keep its (sanitized) contents, drop the wrapper.
                static::sanitizeChildren($child);
                static::unwrap($child);

                continue;
            }

            static::sanitizeAttributes($child, $tag);
            static::sanitizeChildren($child);
        }
    }

    /**
     * Strip disallowed attributes and unsafe URLs from an element.
     */
    private static function sanitizeAttributes(DOMElement $element, string $tag): void
    {
        $allowed = self::ALLOWED_ATTRIBUTES[$tag] ?? [];

        foreach (iterator_to_array($element->attributes) as $attr) {
            $name = strtolower($attr->name);

            if (! in_array($name, $allowed, true)) {
                $element->removeAttribute($attr->name);

                continue;
            }

            if (in_array($name, ['href', 'src'], true) && ! static::isSafeUrl($attr->value)) {
                $element->removeAttribute($attr->name);
            }
        }

        // Harden links that open a new tab against reverse tabnabbing.
        if ($tag === 'a' && $element->getAttribute('target') !== '') {
            $element->setAttribute('rel', 'noopener noreferrer nofollow');
        }
    }

    /**
     * Replace an element with its child nodes (drop the wrapper, keep content).
     */
    private static function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;

        if (! $parent) {
            return;
        }

        while ($element->firstChild) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $parent->removeChild($element);
    }

    /**
     * Determine whether a URL is safe to keep (relative or an allowed scheme).
     */
    private static function isSafeUrl(string $value): bool
    {
        $value = trim($value);

        if ($value === '') {
            return false;
        }

        // Entity-decoded by the DOM already; block obfuscation-prone schemes.
        if (preg_match('/^\s*(javascript|data|vbscript):/i', $value)) {
            return false;
        }

        $scheme = parse_url($value, PHP_URL_SCHEME);

        if ($scheme === null) {
            return true; // Relative URL (or anchor / query only).
        }

        return in_array(strtolower($scheme), self::ALLOWED_URL_SCHEMES, true);
    }
}
