const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

const root = path.resolve(__dirname, '..');
const source = fs.readFileSync(path.join(root, 'app', 'partials', 'dashboard_pipeline.php'), 'utf8');
const functionStart = source.indexOf('    function setTimelineFilter(');
const functionEnd = source.indexOf('    function renderUnifiedTimeline(', functionStart);

if (functionStart < 0 || functionEnd < 0) {
  throw new Error('Could not locate the production timeline filter function.');
}

const setTimelineFilterSource = source.slice(functionStart, functionEnd);
const outputDir = path.join(root, 'tests', 'artifacts');
fs.mkdirSync(outputDir, { recursive: true });

(async () => {
  const systemChrome = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
  const browser = await chromium.launch({ headless: true, ...(fs.existsSync(systemChrome) ? { executablePath: systemChrome } : {}) });
  const page = await browser.newPage({ viewport: { width: 900, height: 500 } });
  const consoleErrors = [];
  page.on('console', (message) => {
    if (message.type() === 'error') consoleErrors.push(message.text());
  });

  await page.setContent(`
    <!doctype html>
    <html lang="en">
      <body style="font-family:Arial,sans-serif;background:#f8fafc;padding:40px">
        <div id="filters" role="tablist" aria-label="Filter communication timeline" style="display:flex;gap:8px">
          ${['sms', 'email', 'notes', 'all'].map((filter) => `<button type="button" role="tab" data-timeline-filter="${filter}" aria-selected="false" class="timeline-filter-button text-slate-600 hover:bg-white hover:text-slate-900"><span>${filter}</span><span data-timeline-filter-count="${filter}">0</span></button>`).join('')}
        </div>
      </body>
    </html>
  `);

  await page.addScriptTag({ content: `
    const timelineFilterButtons = Array.from(document.querySelectorAll('[data-timeline-filter]'));
    let activeTimelineFilter = 'sms';
    let activeTimelineThread = {};
    window.timelineRenderCount = 0;
    function renderUnifiedTimeline() { window.timelineRenderCount += 1; }
    ${setTimelineFilterSource}
    timelineFilterButtons.forEach((button, index) => {
      button.addEventListener('click', () => setTimelineFilter(button.dataset.timelineFilter || 'sms'));
      button.addEventListener('keydown', (event) => {
        if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
        event.preventDefault();
        let nextIndex = index;
        if (event.key === 'Home') nextIndex = 0;
        if (event.key === 'End') nextIndex = timelineFilterButtons.length - 1;
        if (event.key === 'ArrowLeft') nextIndex = (index - 1 + timelineFilterButtons.length) % timelineFilterButtons.length;
        if (event.key === 'ArrowRight') nextIndex = (index + 1) % timelineFilterButtons.length;
        const nextButton = timelineFilterButtons[nextIndex];
        setTimelineFilter(nextButton.dataset.timelineFilter || 'sms');
        nextButton.focus();
      });
    });
    setTimelineFilter('sms', { render: false });
  ` });

  const selected = async () => page.locator('[role="tab"][aria-selected="true"]').getAttribute('data-timeline-filter');
  if (await selected() !== 'sms') throw new Error('SMS was not selected by default.');

  await page.locator('[data-timeline-filter="email"]').click();
  if (await selected() !== 'email') throw new Error('Clicking Email did not select its filter.');
  if (await page.evaluate(() => window.timelineRenderCount) !== 1) throw new Error('Selecting a filter did not rerender the timeline exactly once.');

  await page.keyboard.press('ArrowRight');
  if (await selected() !== 'notes') throw new Error('ArrowRight did not move selection to Notes.');
  if (await page.locator('[data-timeline-filter="notes"]').getAttribute('tabindex') !== '0') throw new Error('Keyboard selection did not update roving tabindex.');

  await page.screenshot({ path: path.join(outputDir, 'lead-communication-filters.png') });
  await browser.close();

  if (consoleErrors.length) throw new Error(`Browser console errors: ${consoleErrors.join(' | ')}`);
  console.log('Lead communication timeline browser tests passed.');
})().catch((error) => {
  console.error(error);
  process.exit(1);
});
