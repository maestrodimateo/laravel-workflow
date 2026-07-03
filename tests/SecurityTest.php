<?php

use Illuminate\Support\Facades\Http;
use Maestrodimateo\Workflow\Actions\WebhookAction;
use Maestrodimateo\Workflow\Exceptions\UnsafeWebhookUrlException;
use Maestrodimateo\Workflow\Models\Circuit;
use Maestrodimateo\Workflow\Models\Message;
use Maestrodimateo\Workflow\Support\HtmlSanitizer;
use Maestrodimateo\Workflow\Tests\Fixtures\TestModel as Test;

// ---------------------------------------------------------------------------
// Webhook SSRF guard
// ---------------------------------------------------------------------------

function runWebhook(string $url): void
{
    $circuit = Circuit::create(['name' => 'Approval', 'targetModel' => Test::class]);
    $from = $circuit->baskets()->first();
    $to = $circuit->baskets()->create(['name' => 'Done', 'status' => 'DONE', 'color' => '#059669']);
    $model = Test::create(['name' => 'M']);

    (new WebhookAction)->execute($model, $from, $to, ['url' => $url]);
}

it('blocks webhooks to the cloud metadata endpoint', function () {
    Http::fake();

    expect(fn () => runWebhook('https://169.254.169.254/latest/meta-data/'))
        ->toThrow(UnsafeWebhookUrlException::class);

    Http::assertNothingSent();
});

it('blocks webhooks to loopback addresses', function () {
    Http::fake();

    expect(fn () => runWebhook('https://127.0.0.1/internal'))
        ->toThrow(UnsafeWebhookUrlException::class);
});

it('blocks webhooks to private ranges', function () {
    Http::fake();

    expect(fn () => runWebhook('https://10.0.0.5/hook'))
        ->toThrow(UnsafeWebhookUrlException::class);
});

it('blocks non-https schemes by default', function () {
    Http::fake();

    expect(fn () => runWebhook('http://93.184.216.34/hook'))
        ->toThrow(UnsafeWebhookUrlException::class);
    expect(fn () => runWebhook('file:///etc/passwd'))
        ->toThrow(UnsafeWebhookUrlException::class);
});

it('allows webhooks to a public https host', function () {
    Http::fake(['*' => Http::response('ok', 200)]);

    runWebhook('https://93.184.216.34/hook');

    Http::assertSent(fn ($request) => $request->url() === 'https://93.184.216.34/hook');
});

// ---------------------------------------------------------------------------
// HTML sanitizer (stored XSS)
// ---------------------------------------------------------------------------

it('strips script tags and event handlers while keeping safe formatting', function () {
    $dirty = '<p>Hello <strong>world</strong></p><script>alert(1)</script>'
        .'<img src=x onerror="alert(2)"><a href="javascript:alert(3)">x</a>';

    $clean = HtmlSanitizer::clean($dirty);

    expect($clean)->toContain('<p>Hello <strong>world</strong></p>')
        ->and($clean)->not->toContain('<script')
        ->and($clean)->not->toContain('onerror')
        ->and($clean)->not->toContain('<img')
        ->and($clean)->not->toContain('javascript:');
});

it('keeps safe links and hardens new-tab links', function () {
    $clean = HtmlSanitizer::clean('<a href="https://example.com" target="_blank">link</a>');

    expect($clean)->toContain('href="https://example.com"')
        ->and($clean)->toContain('rel="noopener noreferrer nofollow"');
});

it('sanitizes message content on write', function () {
    $circuit = Circuit::create(['name' => 'Approval', 'targetModel' => Test::class]);

    $message = Message::create([
        'subject' => 'Hi',
        'content' => '<p>ok</p><script>steal()</script>',
        'type' => 'email',
        'recipient' => 'subject',
        'circuit_id' => $circuit->id,
    ]);

    expect($message->content)->toContain('<p>ok</p>')
        ->and($message->content)->not->toContain('<script');
});
