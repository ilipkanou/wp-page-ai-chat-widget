# WordPress Page AI Chat Widget

An AI chat assistant for WordPress pages. It reads the text of the page it sits on, answers visitor questions about that page with Google Gemini, and emails a transcript of the conversation to the visitor and to you.

Version 2.8.2 Lite. It installs as two PHP code snippets and does not save conversations in WordPress. What changed: [CHANGELOG.md](CHANGELOG.md).

![The assistant's avatar](assets/maggie-avatar-112.webp)

## Real-world use

The full version of this widget runs on the sales page for the book *MAGIC Quick Wins* ([magicquickwins.com](https://www.magicquickwins.com/specials/mqw-book/get-ebook/)) and on the offer pages that follow it. A visitor can ask about the book before buying, and the assistant answers from the page. When the visitor asks for the chat by email, the site owner gets the transcript with a summary and follows up from the inbox. That site also adds each lead to FluentCRM; this Lite edition sends the emails only.

## Features

- A chat window in the bottom-right corner of the pages you choose, styled like a messaging app, with a mobile layout below 640px.
- Answers based on the page itself: the widget reads the page's visible text and sends it to Gemini as context.
- Email addresses and phone-number-like strings are removed from the page text before it is sent to the AI.
- The assistant asks for the visitor's first name and uses it in the conversation. If the AI cannot read the name, the widget finds the first name in the message itself.
- A bar pinned under the chat header, "Get this chat by email", takes the visitor's email address at any time. The chat so far is emailed after the assistant's first answer, and the full transcript when the conversation ends. If the visitor never uses the bar, the assistant asks for an address at the end. Both the visitor and you receive an HTML transcript with a short AI-written summary.
- Works on cached and optimised pages. When a cached page carries an expired security token, the widget fetches a fresh one and retries. Its settings sit on its own page element, so script-delay features such as LiteSpeed Cache "Delay JS" do not stop it from appearing.
- An illustrated assistant avatar on the chat button and in the header, built into the code.
- Your Gemini API key stays on the server. The browser only talks to your own WordPress REST API.
- Abuse protection: a WordPress security token on every request, per-visitor rate limits on chat and on transcript requests, a minimum conversation length and structure check before a transcript is sent, and a `website` field that must stay empty.

## How it works

The widget has two parts:

- `backend-snippet.php` registers three REST routes under `/wp-json/my-chat-assistant/v1/`. The `/chat` route forwards chat requests to the Gemini API using your key. The `/lead` route checks a transcript request and sends the emails with `wp_mail()`. The `/nonce` route returns a fresh security token, for pages served from a cache.
- `frontend-snippet.php` prints the widget in the footer of the pages whose IDs you list, with its settings (the REST address and a security token) as data attributes on its container. It loads React 19 from esm.sh through an import map and renders the chat window.

## Requirements

- A WordPress site whose REST API (`/wp-json/`) is reachable by visitors.
- A theme that calls `wp_footer()`, because the widget is printed on that hook.
- A plugin for running PHP code snippets, such as Code Snippets, WPCode or WP CodeBox.
- Access to `wp-config.php` to store the API key.
- A Google Gemini API key from [Google AI Studio](https://aistudio.google.com/app/apikey).
- Outgoing email that works from WordPress (`wp_mail()`). Many hosts need an SMTP plugin for reliable delivery.
- Visitors need a browser that supports import maps and JavaScript modules.

## Quick install

Try it on a staging site before your live site. Each step says what the part does and gives the line to copy.

1. **Store your Gemini API key.** The key stays on the server, so visitors never see it. Open `wp-config.php` in your WordPress root folder and paste this line just above `/* That's all, stop editing! Happy publishing. */`, with your own key in place of `your-gemini-api-key`:

   ```php
   define('GEMINI_API_KEY', 'your-gemini-api-key');
   ```

2. **Add the backend snippet.** It sends the chat to Gemini and emails the transcripts. In your snippet plugin, create a new PHP snippet and paste the whole of `backend-snippet.php`. Find the `$admin_email` line and replace the example address with the one that should receive transcripts:

   ```php
   $admin_email = 'you@yourdomain.com';
   ```

   Set the snippet to run everywhere, then save and activate it.

3. **Add the frontend snippet.** It shows the chat window. Create a second PHP snippet and paste the whole of `frontend-snippet.php`. Find `$display_on_pages` and list the pages that should show the chat. A page's ID is the number after `post=` in its editor URL:

   ```php
   $display_on_pages = [
       123,  // sales page
       456,  // product page
   ];
   ```

   Set the snippet to run everywhere, then save and activate it.

4. **Test it.** Open one of those pages, ask a question, enter your email address in the "Get this chat by email" bar, and check that the transcript arrives.

Upgrading from 2.8 Lite: replace both snippets and put your settings back (see [CHANGELOG.md](CHANGELOG.md)).

### Using it day to day

- Each chat where the visitor leaves an email address sends you an email with the visitor's name, address, a one-paragraph summary and the full chat. The visitor gets the same email. WordPress stores no conversations, so the email is your record.
- Reply from your inbox, or add the contact to your email list or CRM.
- When a visitor asks something the page does not cover, the assistant says it does not see that detail on the page. Add the answer to the page. The widget reads the page each time it loads, so the next visitor gets the new answer.

Step-by-step instructions, every setting, and troubleshooting are in [USAGE.md](USAGE.md).

## Configuration

| Setting | File | Search for | Default |
|---|---|---|---|
| Pages that show the widget | `frontend-snippet.php` | `$display_on_pages` | example IDs `123`, `456` |
| Gemini model | `wp-config.php` (optional) | `GEMINI_MODEL` | `gemini-2.5-flash-lite` |
| Address that receives transcripts | `backend-snippet.php` | `$admin_email` | `your-email@example.com` |
| Transcript email subject | `backend-snippet.php` | `$email_subject` | `Chat Transcript from Your Website` |
| Chat rate limit | `backend-snippet.php` | `$request_count > 20` | 21 per clock minute (the 22nd is refused) |
| Transcript rate limit | `backend-snippet.php` | `$request_count > 10` | 11 per clock hour (the 12th is refused) |
| Minimum messages before a transcript | `backend-snippet.php` | `count($transcript_data) < 4` | 4 |
| Assistant picture | `frontend-snippet.php` | `MAGGIE_AVATAR` | built-in illustration (see `assets/`) |

The Gemini model is a server-side setting. To use a model other than the default, add `define('GEMINI_MODEL', 'your-model-id');` to `wp-config.php` next to your API key.

The assistant is called "Maggie". USAGE.md lists every place to change the name, greeting, colors and the assistant's instructions.

## Security notes

- Keep the API key in `wp-config.php`. Do not paste it into either snippet.

Fixed:

- Visitors cannot choose the Gemini model. The backend uses only the `GEMINI_MODEL` setting (or the default) and ignores any model name a request sends.
- The visitor's email address is not written to the PHP error log. When a transcript email fails, the log says whether the email to you or to the visitor failed, without the address.
- Each request counts once against the rate limits, and the limits reset every clock minute (chat) and clock hour (transcript). Before 2.8.2, WordPress's second permission check made every request count twice.
- A cached page with an expired security token no longer breaks the chat: the widget gets a fresh token from `/nonce`.

Still open:

- The `/chat` route sends the page text and chat history it receives to Gemini with your key. Anyone who loads a widget page can call it within the rate limit. Watch your Gemini API usage.
- The rate limits count requests per IP address. The IP is read from the `CF-Connecting-IP`, `X-Forwarded-For` and `X-Real-IP` headers before `REMOTE_ADDR`. If no proxy in front of your site overwrites those headers, a client can send its own values and avoid the limits.
- The transcript is emailed to whatever address the visitor enters, within the transcript rate limit.
- The backend writes the visitor's IP address to the PHP error log when it rejects a transcript request. Check who can read your logs.

Privacy: visitors' browsers load React from esm.sh. Your server sends the page text, the conversation and the visitor's first name to Google's Gemini API. You may need to mention this in your privacy policy.

USAGE.md explains each protection and what it does not cover.

## Tests

`tests/run-tests.sh` runs the snippets in a throwaway local WordPress (WordPress Playground) with a fake AI and drives the widget in a real browser engine. It needs Node.js and Playwright (`npm i -g playwright && playwright install chromium`). Nothing is sent to Gemini and no email goes out. Add `--with-rate-limit` to also test the rate limits (about 4 more minutes). See [USAGE.md](USAGE.md), section 7.

## License

MIT. See [LICENSE](LICENSE).

## Author

Igor Lipkanou (igor@magicquickwins.com), built with Claude Sonnet 4.5.
