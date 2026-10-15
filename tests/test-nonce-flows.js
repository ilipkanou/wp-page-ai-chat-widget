// Browser test for the full chat flow and the nonce (security token) fix: visitor on a fresh page,
// visitor on a stale cached page (expired token swapped in), and a logged-in admin.
// Test-only. Runs against a local WordPress Playground site (see tests/run-tests.sh).
// Usage: node tests/test-nonce-flows.js <site-url>
const { chromium } = require('playwright');

const BASE = process.argv[2] || 'http://127.0.0.1:9400';
const STALE = '1b0bab7d97'; // an expired security token, as served by a page cache older than 24 hours

// Send a message, then return the needle found in the NEW model replies only.
async function sayAndWait(page, text, needles, timeout = 20000) {
  const before = await page.locator('.chat-message.model').count();
  await page.fill('.chat-input', text);
  await page.press('.chat-input', 'Enter');
  const found = await page.waitForFunction(([n, b]) => {
    if (document.querySelector('.chat-loading')) return null;
    const fresh = [...document.querySelectorAll('.chat-message.model')].slice(b).map((m) => m.innerText).join('\n');
    return n.find((x) => fresh.includes(x)) || null;
  }, [needles, before], { timeout }).catch(() => null);
  return found ? await found.jsonValue() : 'TIMEOUT';
}

async function scenario(name, { stale = false, login = false } = {}) {
  const base = BASE;
  const browser = await chromium.launch();
  const ctx = await browser.newContext();
  const page = await ctx.newPage();
  const net = { nonce: [], chat: [], lead: [], cookieSent: 0 };
  page.on('request', (r) => {
    if (r.url().includes('my-chat-assistant') && r.headers()['cookie']) net.cookieSent++;
  });
  page.on('response', (r) => {
    const u = r.url();
    if (u.includes('my-chat-assistant/v1/nonce')) net.nonce.push(r.status());
    else if (u.includes('my-chat-assistant/v1/chat')) net.chat.push(r.status());
    else if (u.includes('my-chat-assistant/v1/lead')) net.lead.push(r.status());
  });
  page.on('pageerror', (e) => console.log(`  [pageerror] ${e.message}`));

  if (login) {
    await page.goto(base + '/wp-login.php');
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', 'password');
    await page.click('#wp-submit');
    await page.waitForURL(/wp-admin/, { timeout: 20000 });
  }

  let swapped = null;
  if (stale) {
    await page.route((url) => url.searchParams.get('page_id') === '741' || url.pathname.startsWith('/test-sales-page'), async (route) => {
      const resp = await route.fetch();
      const body = await resp.text();
      // v2.8.1 prints the token as `nonce: "..."`, v2.8.2 as `data-nonce="..."`
      const out = body.replace(/(nonce: "|data-nonce=")[0-9a-f]+"/, `$1${STALE}"`);
      swapped = out !== body;
      await route.fulfill({ response: resp, body: out });
    });
  }

  await page.goto(base + '/?page_id=741', { waitUntil: 'domcontentloaded' });
  await page.click('.chat-float-btn', { timeout: 30000 });

  const steps = {};
  steps.name = await sayAndWait(page, 'Hello. My name is Bob.', ['FAKE-GREETING Bob', 'Nice to meet you']);
  steps.question = await sayAndWait(page, 'What is this book about?', ['FAKE-REPLY', 'trouble connecting']);
  steps.goodbye = await sayAndWait(page, 'Thanks, bye', ['enter your email address', 'trouble connecting']);
  if (steps.goodbye === 'enter your email address') {
    steps.transcript = await sayAndWait(page, 'test@example.com', ['transcript has been sent', 'trouble sending']);
  } else {
    steps.transcript = 'SKIPPED';
  }

  const pass = steps.name === 'FAKE-GREETING Bob' && steps.question === 'FAKE-REPLY'
    && steps.goodbye === 'enter your email address' && steps.transcript === 'transcript has been sent';
  console.log(`${pass ? 'PASS' : 'FAIL'}  ${name}${stale ? `  (stale token swapped in: ${swapped})` : ''}`);
  console.log(`  steps: ${JSON.stringify(steps)}`);
  console.log(`  network: nonce=${JSON.stringify(net.nonce)} chat=${JSON.stringify(net.chat)} lead=${JSON.stringify(net.lead)} requests-with-cookies=${net.cookieSent}`);
  await browser.close();
  return pass;
}

(async () => {
  const results = [];
  results.push(await scenario('visitor, fresh page'));
  results.push(await scenario('visitor, STALE cached page', { stale: true }));
  results.push(await scenario('logged-in admin', { login: true }));
  console.log(`\nTOTAL: ${results.filter(Boolean).length} pass, ${results.filter((x) => !x).length} fail`);
})();
