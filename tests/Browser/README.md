# Headless UI smoke test

The PHP suite can't see CSS/JS regressions in the designer (collapsed icons, a
dead WYSIWYG editor, …). This opt-in test renders the designer and drives it in
headless Chrome to assert those stay fixed.

## Run it

```bash
npm i puppeteer-core                     # one-time, dev-only (git-ignored)
WF_UI_SMOKE=1 \
  CHROME="/Applications/Google Chrome.app/Contents/MacOS/Google Chrome" \
  vendor/bin/pest tests/Browser
```

Without `WF_UI_SMOKE=1` the test is skipped, so `composer test` is unaffected.

## What it checks (`smoke.mjs`)

- **Icon buttons render** — every `header button svg` has width > 0 (guards the
  `.sh-btn` padding vs `p-0` cascade bug).
- **Variable tags insert** — clicking a message variable tag inserts `{{ … }}`
  into the Quill editor (guards `openMsgModal` nulling the editor).

Add a check here whenever a UI regression slips through.
