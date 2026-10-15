# Usage guide

This guide covers installation, every setting, how the widget reads your page, how the transcript email works, the abuse protection, and troubleshooting.

Settings are found by searching the snippet for a short piece of text, shown as "search for". Line numbers are not used because they change when you edit the file.

## 1. Before you start

- Use a staging copy of your site for installation and testing.
- Install a code snippet plugin: Code Snippets, WPCode or WP CodeBox. The menu names below are from Code Snippets; the other plugins use similar names.
- Get a Gemini API key from [Google AI Studio](https://aistudio.google.com/app/apikey).
- Make sure WordPress can send email. If your host does not deliver `wp_mail()` messages reliably, install an SMTP plugin first.
- If you use a security plugin, check that it does not block PHP snippets or the REST API (`/wp-json/`) for logged-out visitors. The widget needs both.

## 2. Install

### Step 1: Store the API key

1. Open `wp-config.php` in your WordPress root folder (through your host's file manager, SFTP or FTP).
2. Find the line `/* That's all, stop editing! Happy publishing. */`.
3. Just above it, add:

   ```php
   define('GEMINI_API_KEY', 'your-gemini-api-key');
   ```

4. Replace `your-gemini-api-key` with your key and save the file.

The backend reads the key from this constant. If it is missing or empty, every chat request fails with "API key not configured."

### Step 2: Add the backend snippet

1. Go to **Snippets > Add New**.
2. Name it, for example `AI Chat Widget - Backend`.
3. Paste the full contents of `backend-snippet.php`. If the editor already shows an opening `<?php` tag and reports an error on the first line, remove the `<?php` line from the pasted code.
4. Search for `$admin_email` and replace `your-email@example.com` with the address that should receive transcripts.
5. Set the snippet to run everywhere, then save and activate it.

### Step 3: Add the frontend snippet

1. Go to **Snippets > Add New** again.
2. Name it, for example `AI Chat Widget - Frontend`.
3. Paste the full contents of `frontend-snippet.php`.
4. Search for `$display_on_pages` and replace the example IDs `123` and `456` with your own page IDs (see below).
5. Set the snippet to run everywhere, then save and activate it.

### Finding a page ID

1. Go to **Pages > All Pages** (or **Posts > All Posts**).
2. Hover over the page title, or open the page in the editor.
3. The URL contains `post=123`. The number is the page ID.

```php
$display_on_pages = [
    45,   // pricing page
    128,  // product page
    256,  // a blog post
];
```

The widget loads only where WordPress's `is_singular()` is true: single pages, posts and other single post types. It does not appear on archives, category pages or the blog posts index, even if you add that page's ID.

### Upgrading from 2.8 Lite

1. Note your current settings: `$admin_email`, `$email_subject`, `$display_on_pages`, and any limit or length you changed.
2. Replace the code of both snippets with the new `backend-snippet.php` and `frontend-snippet.php`. Update both: the new frontend needs the backend's new `/nonce` route.
3. Put your settings back, then save both snippets.
4. Clear your page cache, so visitors get the new frontend.

## 3. Settings

### Pages

`frontend-snippet.php`, search for `$display_on_pages`. A list of page and post IDs.

### Gemini model

The model is a server-side setting. By default the backend uses `gemini-2.5-flash-lite`. To use a different model, add this line to `wp-config.php`, next to your API key:

```php
define('GEMINI_MODEL', 'your-model-id');
```

The backend inserts this name into the Gemini `generateContent` URL. Use a model ID from Google's current Gemini model list that supports `generateContent`. The browser does not send a model name, and the backend ignores one if a request includes it.

### Transcript email

`backend-snippet.php`, at the top of the `handle_ai_chat_submission` function:

```php
$admin_email = 'your-email@example.com'; // CHANGE THIS!
$email_subject = 'Chat Transcript from Your Website';
```

The same email, with the same subject, goes to `$admin_email` and to the visitor.

### Rate limits

`backend-snippet.php`:

- Chat: search for `$request_count > 20`. The counter resets at the start of each clock minute.
- Transcript: search for `$request_count > 10`. The counter resets at the start of each clock hour.

The first request sets the counter to 1, and a request is refused once the counter is above the number. With the defaults, a visitor can make 21 chat requests in a clock minute before the 22nd is refused, and 11 transcript requests in a clock hour before the 12th is refused. Every message the visitor sends counts as one chat request. So do the name step and each summary made before a transcript is sent.

Before 2.8.2 the limits were tighter than these numbers. WordPress runs a route's permission check a second time for each request (to build the HTTP `Allow` header), and the counting happens there, so every request counted twice. Each request also restarted the timer, so a visitor who kept chatting never got a fresh count. The check now skips the second run, and the windows follow the clock.

### Minimum conversation length

`backend-snippet.php`, search for `count($transcript_data) < 4`. A transcript request with fewer messages than this number is refused. In the normal widget flow the transcript already holds 4 messages once the visitor has given their name, so raise the number if you want to require at least one question and answer. The "Get this chat by email" bar always waits for the assistant's first answer before it sends, so a transcript from the bar has at least 6 messages.

### Assistant name

The assistant is called "Maggie". To rename it, change every place below.

`frontend-snippet.php`:

- Search for `Hi! I'm Maggie`: the first greeting.
- Search for `"chat-header-title" }, "Maggie"`: the name in the chat header.

`backend-snippet.php`:

- Search for `You are Maggie`: the assistant's instructions.
- Search for `'Maggie'`: the label used in the text sent for the summary.
- Search for `'Maggie (AI)'`: the label in the transcript email. It appears twice.

`MaggieAvatar` and `MAGGIE_AVATAR` in the frontend are only internal names for the avatar and do not need to change.

### Assistant picture

The avatar on the chat button and in the header is an illustration built into `frontend-snippet.php` as text (a "data URI"), so the page makes no extra request for it. The image files are in `assets/`: `maggie-avatar-112.webp` (the one in the code) and `maggie-avatar.png` (512 px).

To use your own picture:

1. Make a square image with the face centred, 112 × 112 pixels, saved as WebP (a few KB).
2. Turn it into text: `printf 'data:image/webp;base64,%s\n' "$(base64 -i your-avatar.webp)"` on macOS, or `base64 -w0` instead of `base64 -i` on Linux.
3. In `frontend-snippet.php`, search for `const MAGGIE_AVATAR = '` and replace everything between the quotes with the output.

### Header subtitle

`frontend-snippet.php`, search for `"chat-header-subtitle" }, "AI Assistant"`.

### Greeting and fixed messages

`frontend-snippet.php` holds the visible fixed messages. Search for these to edit them:

- `Hi! I'm Maggie`: the greeting that asks for the visitor's name.
- `By the way, when we're finished chatting`: the reminder that a transcript can be emailed. It appears twice (normal and fallback greeting).
- `I am glad I could help!`: the request for an email address.
- `Done! The transcript has been sent.`: the confirmation.
- `Done! I sent our chat so far to`: the confirmation after a send from the bar.
- `I'm sending the full transcript to`: the message at the end of the chat when the visitor already gave an email in the bar.
- `const getBarText`: every text the "Get this chat by email" bar shows.

### The assistant's instructions

`backend-snippet.php`, search for `COMMUNICATION STYLE`. This is the instruction text Gemini receives with every chat message. By default it tells the assistant to act as a friendly sales consultant, answer only from the page text, keep answers short (usually 2 to 4 sentences), use simple markdown, end each answer with an emoji and a follow-up question, and add `[END_OF_CONVERSATION]` when the visitor is done. Edit it to change the assistant's tone. Keep the `[END_OF_CONVERSATION]` instruction, because the email step depends on it.

### Colors

`frontend-snippet.php`, inside `injectStyles`:

| Color | Used for |
|---|---|
| `#008069` | floating chat button and send button |
| `#005E54` | header bar, hover and focus states |
| `#DCF8C6` | visitor message bubbles |
| `#E5DDD5` | chat background |
| `#FFF8E1` | the "Get this chat by email" bar |

The chat background is a plain color. The widget loads no background image. The transcript email has its own copy of `#E5DDD5` and `#DCF8C6` in `backend-snippet.php`.

## 4. How the page content is loaded

When the widget starts, it looks for the page's main content by trying these CSS selectors in order:

1. `main`
2. `.op-page-content`
3. `.opb-section-content`
4. `.op3-element[data-op3-element-type="column"]`
5. `article`
6. `.entry-content`
7. `.page-content`
8. `#content`
9. `body`

For each selector it takes the first matching element. It uses the first one whose visible text is longer than 100 characters. The browser console then shows `AI Chat Widget: Found content using selector: ...` with the selector and the character count. If none qualifies, the console shows a warning and the assistant answers without page context.

To point the widget at a specific part of your page, add your own selector at the top of the `selectors` list.

Before the text is used, the widget replaces:

- anything that looks like an email address with `[EMAIL]`
- anything that looks like a phone number with `[PHONE]`

The phone pattern is broad. Any run of four or more digits is replaced, including years (`2025`), prices written without a comma (`$1997`) and counts (`1200 readers`). The assistant cannot see or quote those numbers. Write them with a separator (`$1,997`) if the assistant needs them.

The full filtered page text is sent with every chat message as part of the assistant's instructions. A long page means a larger request each time.

## 5. How a conversation and the transcript email work

1. The visitor clicks the chat button. The assistant greets them and asks for their name.
2. The visitor types their name. The backend asks Gemini to pick out the first name and write a greeting. If that fails, the widget finds the first name in the message itself ("Hello. My name is Bob." gives "Bob"; with no name it says "Nice to meet you!").
3. The assistant adds a reminder that it can email a transcript. At the same moment the "Get this chat by email" bar appears under the header.
4. Each visitor message is sent to Gemini with the conversation so far, the page text and the visitor's first name.
5. **The bar, at any time.** The visitor clicks it and types an email address. If they have not asked a question yet, the bar says the chat will be sent after the assistant's next answer. Once there is a question and an answer, the widget sends the chat so far (steps 7 and 8) and the visitor keeps chatting. The bar then shows "Sent to …"; a click sends an updated copy.
6. **The end.** When Gemini's reply contains `[END_OF_CONVERSATION]`, the widget removes the marker. If the visitor already gave an email in the bar, the full transcript goes to that address straight away. If not, the widget asks for an email address and switches the input to an email field.
7. For each transcript, the widget asks Gemini for a one-paragraph summary and sends the name, email, transcript, summary and an empty `website` field to `/lead`.
8. The backend runs its checks (section 6) and sends the HTML email to `$admin_email` and to the visitor. The email shows the visitor's name and address, the summary, and the conversation as chat bubbles.
9. After the final transcript the widget confirms and the input stays disabled. Reloading the page starts a new chat.

A visitor who uses the bar and then finishes the chat gets two emails: the early copy and the full transcript. So do you.

What is stored: conversations are not saved in WordPress. The only data the widget stores are rate-limit counters (WordPress transients, keyed by a hash of the visitor's IP address). The transcript exists only in the two emails. The PHP error log does not record the visitor's email address or the conversation (see section 6). The visitor's email address is not sent to Gemini.

## 6. Abuse protection

### Security token

The `/chat` and `/lead` routes require the WordPress REST token (`X-WP-Nonce`, action `wp_rest`). The frontend snippet prints it into the page as the `data-nonce` attribute of the widget's container. For logged-out visitors this token is the same for everyone while it is valid, so it shows that the request came from someone who loaded one of your pages. It does not identify a visitor.

- A request with no token gets "Invalid security token." from the snippet.
- A request with an expired or wrong token gets "Cookie check failed" from WordPress itself (code `rest_cookie_invalid_nonce`). This happens when a page cache serves a copy older than the token (12 to 24 hours). The widget then asks the `/nonce` route for a fresh token and retries once, so the visitor does not notice.

The widget sends its requests without cookies, so WordPress treats every visitor, including a logged-in admin, as logged out and the token always matches. The `/nonce` route itself needs no token: anyone who loads your page can read the token anyway.

### Browser origin restriction

On its routes, the backend sets `Access-Control-Allow-Origin` to your site's home URL, so browsers block pages on other websites from calling them. Requests sent from outside a browser are not affected.

### Rate limits

See "Rate limits" in section 3. The visitor's IP address is read from the first of these that is set: `CF-Connecting-IP`, `X-Forwarded-For` (first address), `X-Real-IP`, `REMOTE_ADDR`. Behind Cloudflare or a proxy that sets these headers, this finds the real visitor IP. Without such a proxy, a client can send its own header values and get a new counter each time.

### Transcript checks

The `/lead` route refuses a request when:

- the name is empty, the email address is not valid, or the transcript is empty ("Invalid data.")
- the `website` field is not empty ("Spam detected.")
- the transcript has fewer than 4 messages ("Conversation too short. ...")
- a message has no `role` or `content` ("Invalid conversation structure.")
- the transcript does not contain at least one visitor message and one assistant message ("Invalid conversation - missing messages from user or assistant.")

The widget always sends `website` empty, and there is no `website` field on the page. This check only catches automated clients that put a value in every field of the request.

### Input handling

The backend cleans the name, email and summary with WordPress's `sanitize_*` functions, checks the email with `is_email()`, and escapes everything placed in the email HTML.

### Model choice

The Gemini model comes only from the `GEMINI_MODEL` setting in `wp-config.php`, or the default `gemini-2.5-flash-lite` (section 3). The backend ignores any model name in a request, so a visitor cannot switch your key to a different model.

### What is not covered

- Anyone who has loaded a widget page can call `/chat` with their own page text and history, up to the rate limit. Your API key pays for those requests.
- The transcript goes to the address in the request, so someone could send transcripts to other people's addresses, up to the transcript limit.
- See "Rate limits" above for the IP header limitation.

### Logs

The backend writes to the PHP error log:

- the result of every transcript send: "Transcript sent successfully", or "Email failed" with whether the email to you or to the visitor failed. The visitor's email address and the conversation are not logged.
- the visitor's IP address when a transcript request fails the `website`, length or structure check
- Gemini connection errors and Gemini error messages
- "API key not configured" when the key is missing

The success line is marked in the code (search for `Log successes for monitoring`) and can be commented out.

## 7. Testing checklist

- [ ] The chat button appears on each page in `$display_on_pages` and nowhere else, without scrolling or clicking first.
- [ ] The browser console shows `AI Chat Widget: Found content using selector: ...`.
- [ ] The assistant greets you by your first name.
- [ ] The "Get this chat by email" bar appears after the greeting.
- [ ] It answers a question using facts from the page.
- [ ] An email entered in the bar receives the chat so far, and you can keep chatting.
- [ ] When you say you are done, the full transcript goes to the same address (or, without the bar, it asks for your email address).
- [ ] The transcript email reaches both `$admin_email` and the address you entered (check spam folders).
- [ ] After the 11th transcript request within the clock hour, the next one is refused.

### Automated tests

`tests/run-tests.sh` runs the snippets in a throwaway local WordPress (WordPress Playground, which runs inside Node) with a fake AI, and drives the widget in a real browser engine. Nothing is sent to Gemini, no email goes out, and your site is not touched.

```bash
npm i -g playwright && playwright install chromium   # once
tests/run-tests.sh                                     # about 3 minutes
tests/run-tests.sh --with-rate-limit                   # about 4 minutes more
```

It checks: the name fallback (20 sample messages); that the widget appears and works with inline scripts delayed and no interaction; the bar, the avatar, bad email addresses and both transcript sends (13 checks); the full chat on a fresh page, on a cached page with an expired token, and as a logged-in admin; and, with the flag, the rate limits (21 chat requests and 11 transcripts pass, a steady chat is never blocked). The last line is `ALL TESTS PASSED` or `SOME TESTS FAILED`.

The fake AI lives in `tests/harness/zz-test-harness.php`. It is for the test site only; never add it to a real site.

## 8. Troubleshooting

### The chat button does not appear

- Both snippets are active.
- The page's ID is in `$display_on_pages`, and the page is a single page or post (see section 2).
- Your theme calls `wp_footer()`.
- Clear any page cache and your browser cache, then reload.
- Open the browser console (F12) and look for errors. `Cannot read properties of undefined (reading 'backendUrl')` means the page still runs a frontend older than 2.8.2 on a site that delays inline scripts (for example LiteSpeed Cache "Delay JS"): update the frontend snippet and clear the page cache.

### The chat opens but the assistant does not answer

The widget shows "I'm having trouble connecting right now." Open the browser's developer tools, go to the Network tab, and look at the response of the `chat` request.

| Response message | Meaning | What to do |
|---|---|---|
| `Cookie check failed` | The token in a cached page has expired. The widget normally gets a fresh one from `/nonce` and retries, so you only see this when that retry failed. | Check that the backend snippet is version 2.8.2 Lite or later (the `/nonce` route must exist, see below). |
| `Invalid security token.` | The request carried no token. | Check that the widget's `<div id="ai-chat-widget-root">` has a `data-nonce` attribute (right-click the page, View Page Source). |
| `Too many requests. Please wait.` | Chat rate limit reached. | Wait for the next clock minute. |
| `API key not configured.` | `GEMINI_API_KEY` is missing or empty. | Check `wp-config.php` (step 1). |
| `Failed to connect to AI service.` | Your server could not reach the Gemini API. | Check your host's outgoing connections and the PHP error log. |
| Another message from Gemini | Gemini refused the request, for example because of an invalid key or an unknown model name. | Fix the key or the model name. |

To check that the backend snippet is running, open `https://your-site.example/wp-json/my-chat-assistant/v1` in a browser. When the snippet is active, WordPress returns a JSON description of this namespace that lists the `/chat`, `/lead` and `/nonce` routes. A `rest_no_route` error means the backend snippet is not running.

### Page caching

Page caching is safe since 2.8.2; you do not need to exclude the widget pages. A WordPress token is valid for 12 to 24 hours, so a cached page can carry an expired one. WordPress then answers "Cookie check failed", and the widget gets a fresh token from `/nonce` and retries once. Script-delay features such as LiteSpeed Cache "Delay JS" are also safe: the widget reads its settings from attributes on its container, not from an inline script.

After you update the snippets, clear the page cache once, so visitors get the new frontend.

### The assistant does not know what is on the page

- Check the console message that names the selector used. If it is `body`, the text may include menus and footers; add a more specific selector (section 4).
- If the console shows "Could not find page content", no selector matched an element with more than 100 characters of visible text.
- If the assistant cannot quote a year, a price or a count, the phone filter has replaced it (section 4).

### The assistant never asks for an email address

The end-of-chat email step starts only when Gemini's reply contains `[END_OF_CONVERSATION]`. If you edited the assistant's instructions, check that the line asking for this marker is still there. Visitors can still use the "Get this chat by email" bar at any time.

### The transcript fails to send

The widget shows "I'm having trouble sending the transcript right now." In the Network tab, look at the response of the `lead` request (or the `chat` request before it, which makes the summary). The possible messages are listed in section 6, plus `Too many submissions. Please wait before trying again.` for the transcript rate limit.

### The widget says the transcript was sent, but no email arrives

The backend reports success even when `wp_mail()` fails. Check:

- `$admin_email` is set to your address.
- Spam and junk folders.
- The PHP error log for `AI Chat Widget: Email failed`, which shows whether the email to you or to the visitor failed.
- That WordPress can send email at all. An SMTP plugin usually fixes delivery problems.

### The input is disabled after the transcript is sent

This is expected after the final transcript at the end of the chat. Reload the page to start a new chat. A send from the bar does not disable the input.

### The bar says "I'll send this chat to … after my next answer"

The visitor gave an email before asking a question. The chat is sent right after the assistant's next answer.
