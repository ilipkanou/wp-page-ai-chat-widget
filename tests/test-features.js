// Browser test for the v2.8.1 features: name fallback, pinned transcript bar, avatar, no external image.
// Test-only. Runs against a local WordPress Playground site (see tests/run-tests.sh).
// Usage: node tests/test-features.js <site-url> [screenshot-dir]
const { chromium } = require('playwright');
const BASE = process.argv[2] || 'http://127.0.0.1:9414';
const SHOTS = process.argv[3] || require('os').tmpdir();

const results = [];
const check = (name, ok, detail = '') => { results.push(ok); console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}${detail ? '  — ' + detail : ''}`); };

async function newPage(browser, net) {
  const page = await browser.newPage({ viewport: { width: 420, height: 860 } });
  page.on('response', (r) => {
    const u = r.url();
    if (u.includes('my-chat-assistant/v1/lead')) net.lead.push(r.status());
    if (u.includes('githubusercontent')) net.external.push(u);
  });
  page.on('request', (r) => { if (r.url().includes('githubusercontent')) net.external.push(r.url()); });
  page.on('pageerror', (e) => console.log(`  [pageerror] ${e.message}`));
  return page;
}

// Send a chat message, return the text of the NEW model replies once Maggie stops loading.
async function say(page, text, needles, timeout = 20000) {
  const before = await page.locator('.chat-message.model').count();
  await page.fill('.chat-input', text);
  await page.press('.chat-input', 'Enter');
  const h = await page.waitForFunction(([n, b]) => {
    if (document.querySelector('.chat-loading')) return null;
    const fresh = [...document.querySelectorAll('.chat-message.model')].slice(b).map((m) => m.innerText).join('\n');
    return n.find((x) => fresh.includes(x)) ? fresh : null;
  }, [needles, before], { timeout }).catch(() => null);
  return h ? await h.jsonValue() : 'TIMEOUT';
}

const barText = (page) => page.locator('.chat-pin').innerText().catch(() => '(no bar)');
const waitBar = (page, needle, timeout = 20000) =>
  page.waitForFunction((n) => (document.querySelector('.chat-pin')?.innerText || '').includes(n), needle, { timeout }).then(() => true).catch(() => false);

(async () => {
  const browser = await chromium.launch();

  // ---- 1. Name fallback: block the AI name call, the code must find "Bob" ----
  {
    const net = { lead: [], external: [] };
    const page = await newPage(browser, net);
    await page.route((url) => url.href.includes('my-chat-assistant/v1/chat'), async (route) => {
      const body = route.request().postData() || '';
      if (body.includes('extractNameAndGreet')) return route.abort();
      return route.continue();
    });
    await page.goto(BASE + '/?page_id=741', { waitUntil: 'domcontentloaded' });
    await page.click('.chat-float-btn', { timeout: 30000 });
    const reply = await say(page, 'Hello. My name is Bob.', ['Nice to meet you']);
    check('name fallback greets "Bob"', reply.includes('Nice to meet you, Bob! How can I help you?') && !reply.includes('My name is'), JSON.stringify(reply.split('\n')[0]));
    await page.close();
  }

  // ---- 2-5. Pinned bar: appears after the reminder, early send waits, auto-send, end-of-chat send ----
  {
    const net = { lead: [], external: [] };
    const page = await newPage(browser, net);
    await page.goto(BASE + '/?page_id=741', { waitUntil: 'domcontentloaded' });

    // avatar on the round button
    const btnImg = await page.locator('.chat-float-btn img').evaluate((i) => i.complete && i.naturalWidth).catch(() => 0);
    check('avatar loads on the round chat button', btnImg > 0, `naturalWidth=${btnImg}`);
    await page.screenshot({ path: `${SHOTS}/1-closed-button.png` });

    await page.click('.chat-float-btn', { timeout: 30000 });
    check('no pinned bar before the name step', (await page.locator('.chat-pin').count()) === 0);
    const hdrImg = await page.locator('.chat-header img.chat-avatar').evaluate((i) => i.complete && i.naturalWidth).catch(() => 0);
    check('avatar loads in the header', hdrImg > 0, `naturalWidth=${hdrImg}`);

    const greet = await say(page, 'Hello. My name is Bob.', ['FAKE-GREETING Bob']);
    const reminderShown = greet.includes('transcript of our conversation');
    const bar1 = await barText(page);
    check('pinned bar appears with the reminder', reminderShown && bar1.includes('Get this chat by email'), JSON.stringify(bar1));
    await page.screenshot({ path: `${SHOTS}/2-open-with-bar.png` });

    // bad email
    await page.click('.chat-pin-toggle');
    await page.fill('.chat-pin-input', 'not-an-email');
    await page.press('.chat-pin-input', 'Enter');
    const nativeBlocked = await page.locator('.chat-pin-input').evaluate((i) => !i.checkValidity());
    await page.waitForTimeout(800);
    check('browser blocks "not-an-email"', nativeBlocked && net.lead.length === 0 && (await page.locator('.chat-pin-input').count()) === 1, `blocked=${nativeBlocked} lead=${net.lead.length}`);
    await page.fill('.chat-pin-input', 'bob@site');
    await page.press('.chat-pin-input', 'Enter');
    const err = await page.locator('.chat-pin-error').innerText().catch(() => '');
    check('"bob@site" shows the widget error', err.includes('Please check the email address'), JSON.stringify(err));

    // early send: chat too short, must wait
    await page.fill('.chat-pin-input', 'test@example.com');
    await page.screenshot({ path: `${SHOTS}/3-bar-open.png` });
    await page.press('.chat-pin-input', 'Enter');
    const waiting = await waitBar(page, 'after my next answer', 5000);
    await page.waitForTimeout(1500);
    check('early send waits for the first answer', waiting && net.lead.length === 0, `bar=${JSON.stringify(await barText(page))} leadCalls=${net.lead.length}`);

    // first question answered -> automatic send
    const ans = await say(page, 'What is this book about?', ['FAKE-REPLY']);
    const sent = await waitBar(page, 'Sent to test@example.com');
    const confirm = await page.waitForFunction(() => document.querySelector('.chat-messages').innerText.includes('I sent our chat so far to test@example.com'), null, { timeout: 15000 }).then(() => true).catch(() => false);
    check('transcript goes out after the first answer', sent && confirm && JSON.stringify(net.lead) === '[200]', `bar=${JSON.stringify(await barText(page))} lead=${JSON.stringify(net.lead)}`);
    await page.screenshot({ path: `${SHOTS}/4-sent.png` });

    // visitor keeps chatting
    const more = await say(page, 'Is there a guarantee?', ['FAKE-REPLY', 'trouble']);
    check('visitor can keep chatting after the send', more.includes('FAKE-REPLY'));

    // end of chat -> full transcript to the same email, no email prompt
    const bye = await say(page, 'Thanks, bye', ['transcript has been sent', 'enter your email address', 'trouble']);
    check('end of chat sends the full transcript to the same email', bye.includes('sending the full transcript to test@example.com') && bye.includes('transcript has been sent') && !bye.includes('enter your email address') && JSON.stringify(net.lead) === '[200,200]',
      `lead=${JSON.stringify(net.lead)} reply=${JSON.stringify(bye.split('\n').slice(-2))}`);
    check('bar shows the final state', (await barText(page)).includes('Transcript sent to test@example.com'), JSON.stringify(await barText(page)));
    await page.screenshot({ path: `${SHOTS}/5-final.png` });
    check('no request to the old outside background picture', net.external.length === 0, `${net.external.length} requests`);
    await page.close();
  }

  await browser.close();
  console.log(`\nTOTAL: ${results.filter(Boolean).length} pass, ${results.filter((x) => !x).length} fail`);
})();
