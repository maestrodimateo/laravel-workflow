// Headless UI smoke checks for the Workflow Designer.
// Invoked by tests/Browser/UiSmokeTest.php. Loads a pre-rendered designer HTML
// (env HTML) in headless Chrome (env CHROME) and asserts the regressions we've
// hit before stay fixed. Exits non-zero with a message on failure.
//
//   node tests/Browser/smoke.mjs   (with HTML=... CHROME=...)

import puppeteer from 'puppeteer-core';

const HTML = process.env.HTML;
const CHROME = process.env.CHROME || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const fail = (m) => { console.error('FAIL: ' + m); process.exit(1); };

const browser = await puppeteer.launch({
    executablePath: CHROME,
    headless: 'new',
    args: ['--no-sandbox', '--allow-file-access-from-files'],
});
const page = await browser.newPage();
await page.setViewport({ width: 1440, height: 900 });

await page.goto('file://' + HTML, { waitUntil: 'networkidle0', timeout: 20000 });
await page.waitForFunction(
    () => window.Alpine && window.Alpine.$data(document.body).baskets.length >= 2,
    { timeout: 10000 },
);
await sleep(1000);

// Check 1 — icon-only buttons must render a visible icon (regression: .sh-btn
// padding beat p-0, collapsing the SVG to width 0).
const minIcon = await page.evaluate(() => {
    // Only visible svgs — x-show toggles (e.g. the dark-mode moon/sun) hide one
    // with display:none, which legitimately has 0 rects.
    const svgs = [...document.querySelectorAll('header button svg')].filter((s) => s.getClientRects().length > 0);
    return svgs.length ? Math.min(...svgs.map((s) => s.getBoundingClientRect().width)) : -1;
});
if (!(minIcon > 0)) fail(`header icons collapsed (min svg width = ${minIcon})`);

// Check 2 — clicking a message variable tag must insert into the Quill editor
// (regression: openMsgModal nulled quillEditor, so insertVariable bailed out).
await page.evaluate(() => window.Alpine.$data(document.body).openMsgModal());
await page.waitForSelector('.ql-editor', { visible: true, timeout: 6000 });
await sleep(500);
await page.evaluate(() => {
    const b = [...document.querySelectorAll('[data-modal="msg"] button')].find((x) => /Variable/i.test(x.textContent));
    if (b) b.click();
});
await sleep(300);
await page.evaluate(() => {
    const t = document.querySelector('[data-modal="msg"] button.sh-badge');
    if (t) t.click();
});
await sleep(400);
const inserted = await page.evaluate(() => /\{\{/.test(document.querySelector('.ql-editor')?.innerHTML || ''));
if (!inserted) fail('message variable tag did not insert into the editor');

console.log(`OK — header icon width=${Math.round(minIcon)}px, variable tag insertion works`);
await browser.close();
