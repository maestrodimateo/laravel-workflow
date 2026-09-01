<?php

use Maestrodimateo\Workflow\Controllers\WorkflowAdminController;
use Maestrodimateo\Workflow\Models\Circuit;

/*
 * Opt-in headless visual smoke test for the designer. Catches CSS/JS regressions
 * the PHP suite can't see (collapsed icons, dead Quill editor, …).
 *
 * Requires Node + a Chromium/Chrome binary + puppeteer-core:
 *   npm i puppeteer-core
 *   WF_UI_SMOKE=1 CHROME="/path/to/Chrome" vendor/bin/pest tests/Browser
 *
 * Skipped (no-op) in the normal `composer test` run.
 */
it('passes the headless designer smoke checks', function () {
    // 1. Render the designer to a temp HTML file, pointing assets at local dist.
    $circuit = Circuit::create(['name' => 'Smoke', 'targetModel' => 'App\\Models\\Smoke']);
    $draft = $circuit->baskets()->first();
    $done = $circuit->baskets()->create(['name' => 'Done', 'status' => 'DONE', 'color' => '#059669']);
    $draft->next()->attach($done->id, ['label' => 'Go']);

    $html = (new WorkflowAdminController)()->render();
    $dist = realpath(__DIR__.'/../../resources/dist');
    $html = preg_replace('#https?://[^/]+/workflow/assets/([A-Za-z0-9._-]+)#', 'file://'.$dist.'/$1', $html);
    $htmlPath = sys_get_temp_dir().'/wf-ui-smoke.html';
    file_put_contents($htmlPath, $html);

    // 2. Drive it with headless Chrome (assertions live in smoke.mjs).
    $cmd = sprintf(
        'CHROME=%s HTML=%s node %s 2>&1',
        escapeshellarg((string) getenv('CHROME')),
        escapeshellarg($htmlPath),
        escapeshellarg(__DIR__.'/smoke.mjs'),
    );
    exec($cmd, $output, $code);

    expect($code)->toBe(0, "Headless UI smoke failed:\n".implode("\n", $output));
})->skip(getenv('WF_UI_SMOKE') !== '1', 'Set WF_UI_SMOKE=1 (needs node + puppeteer-core + CHROME) to run the headless UI smoke test.');
