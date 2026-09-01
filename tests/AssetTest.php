<?php

// ---------------------------------------------------------------------------
// Vendored asset serving (AssetController) — same-origin, whitelisted only.
// ---------------------------------------------------------------------------

it('serves a whitelisted vendored asset with the right content type', function () {
    $response = $this->get('workflow/assets/app.css');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/css');
});

it('404s on any non-whitelisted file (blocks path traversal / arbitrary reads)', function () {
    $this->get('workflow/assets/..')->assertNotFound();
    $this->get('workflow/assets/composer.json')->assertNotFound();
    $this->get('workflow/assets/AssetController.php')->assertNotFound();
});
