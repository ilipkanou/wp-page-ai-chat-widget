// Browser test: the widget must appear for a visitor who never scrolls or taps,
// on a site whose optimiser delays inline scripts until the visitor interacts
// (LiteSpeed Cache "Delay JS" turns them into type="litespeed/javascript").
// Test-only. Runs against a local WordPress Playground site (see tests/run-tests.sh).
// Usage: node tests/test-no-interaction.js <site-url>
const { chromium } = require('playwright');
const BASE = process.argv[2] || 'http://127.0.0.1:9400';

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 420, height: 860 } });
  const errors = [];
  page.on('pageerror', (e) => errors.push(e.message));

  // Delay every classic inline script, as LiteSpeed does. Module scripts and the import map are left alone,
  // as on the live site. Nobody interacts, so the delayed scripts never run.
  let delayed = 0, configDelayed = false;
  await page.route((url) => url.searchParams.get('page_id') === '741' || url.pathname.startsWith('/test-sales-page'), async (route) => {
    const resp = await route.fetch();
    const body = (await resp.text()).replace(/<script(?: type="text\/javascript")?>([\s\S]*?)<\/script>/g, (m, inner) => {
      delayed++;
      if (inner.includes('AI_CHAT_CONFIG')) configDelayed = true;
      return `<script type="litespeed/javascript">${inner}</script>`;
    });
    await route.fulfill({ response: resp, body });
  });

  await page.goto(BASE + '/?page_id=741', { waitUntil: 'domcontentloaded' });
  const appeared = await page.waitForSelector('.chat-float-btn', { timeout: 15000 }).then(() => true).catch(() => false);

  let greeting = 'not tried';
  if (appeared) {
    await page.click('.chat-float-btn');
    await page.fill('.chat-input', 'Hello. My name is Bob.');
    await page.press('.chat-input', 'Enter');
    greeting = await page.waitForFunction(() => document.querySelector('.chat-messages')?.innerText.includes('FAKE-GREETING Bob'), null, { timeout: 20000 })
      .then(() => 'FAKE-GREETING Bob').catch(() => 'no greeting');
  }

  const pass = appeared && errors.length === 0 && greeting === 'FAKE-GREETING Bob';
  console.log(`${pass ? 'PASS' : 'FAIL'}  widget appears without interaction while inline scripts are delayed`);
  console.log(`  scripts delayed: ${delayed} (old settings script among them: ${configDelayed}) | button: ${appeared} | greeting: ${greeting} | errors: ${errors.length ? errors.join(' || ').slice(0, 160) : 'none'}`);
  console.log(`\nTOTAL: ${pass ? 1 : 0} pass, ${pass ? 0 : 1} fail`);
  await browser.close();
  process.exit(pass ? 0 : 1);
})();
