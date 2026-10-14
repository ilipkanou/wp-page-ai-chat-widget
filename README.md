# WordPress Page AI Chat Widget

An AI chat assistant for WordPress pages. It reads the text of the page it sits on, answers visitor questions about that page with Google Gemini, and emails a transcript of the conversation to the visitor and to you.

Version 2.8 Lite. It installs as two PHP code snippets and does not save conversations in WordPress.

## Features

- A chat window in the bottom-right corner of the pages you choose, styled like a messaging app, with a mobile layout below 640px.
- Answers based on the page itself: the widget reads the page's visible text and sends it to Gemini as context.
- Email addresses and phone-number-like strings are removed from the page text before it is sent to the AI.
- The assistant asks for the visitor's first name and uses it in the conversation.
- When the conversation ends, the visitor can enter an email address. Both the visitor and you receive an HTML transcript with a short AI-written summary.
- Your Gemini API key stays on the server. The browser only talks to your own WordPress REST API.
- Abuse protection: a WordPress security token on every request, per-visitor rate limits on chat and on transcript requests, a minimum conversation length and structure check before a transcript is sent, and a `website` field that must stay empty.

## How it works

The widget has two parts:

- `backend-snippet.php` registers two REST routes under `/wp-json/my-chat-assistant/v1/`. The `/chat` route forwards chat requests to the Gemini API using your key. The `/lead` route checks a transcript request and sends the emails with `wp_mail()`.
- `frontend-snippet.php` prints the widget in the footer of the pages whose IDs you list. It loads React 19 from esm.sh through an import map and renders the chat window.

## Requirements

- A WordPress site whose REST API (`/wp-json/`) is reachable by visitors.
- A theme that calls `wp_footer()`, because the widget is printed on that hook.
- A plugin for running PHP code snippets, such as Code Snippets, WPCode or WP CodeBox.
- Access to `wp-config.php` to store the API key.
- A Google Gemini API key from [Google AI Studio](https://aistudio.google.com/app/apikey).
- Outgoing email that works from WordPress (`wp_mail()`). Many hosts need an SMTP plugin for reliable delivery.
- Visitors need a browser that supports import maps and JavaScript modules.

## Quick install

Try it on a staging site before your live site.

1. Add your API key to `wp-config.php`, above the line `/* That's all, stop editing! Happy publishing. */`:

   ```php
   define('GEMINI_API_KEY', 'your-gemini-api-key');
   ```

2. Create a new PHP snippet, paste `backend-snippet.php`, set `$admin_email` to your address, set the snippet to run everywhere, and activate it.
3. Create a second PHP snippet, paste `frontend-snippet.php`, replace the example IDs in `$display_on_pages` with your page IDs, set it to run everywhere, and activate it.
4. Open one of those pages, have a short conversation, and check that the transcript email arrives.

Step-by-step instructions, every setting, and troubleshooting are in [USAGE.md](USAGE.md).

## Configuration

| Setting | File | Search for | Default |
|---|---|---|---|
| Pages that show the widget | `frontend-snippet.php` | `$display_on_pages` | example IDs `123`, `456` |
| Gemini model | `wp-config.php` (optional) | `GEMINI_MODEL` | `gemini-2.5-flash-lite` |
| Address that receives transcripts | `backend-snippet.php` | `$admin_email` | `your-email@example.com` |
| Transcript email subject | `backend-snippet.php` | `$email_subject` | `Chat Transcript from Your Website` |
| Chat rate limit | `backend-snippet.php` | `$request_count > 20` | 20 (per minute) |
| Transcript rate limit | `backend-snippet.php` | `$request_count > 10` | 10 (per hour) |
| Minimum messages before a transcript | `backend-snippet.php` | `count($transcript_data) < 4` | 4 |

The Gemini model is a server-side setting. To use a model other than the default, add `define('GEMINI_MODEL', 'your-model-id');` to `wp-config.php` next to your API key.

The assistant is called "Maggie". USAGE.md lists every place to change the name, greeting, colors and the assistant's instructions.

## Security notes

- Keep the API key in `wp-config.php`. Do not paste it into either snippet.

Fixed:

- Visitors cannot choose the Gemini model. The backend uses only the `GEMINI_MODEL` setting (or the default) and ignores any model name a request sends.
- The visitor's email address is not written to the PHP error log. When a transcript email fails, the log says whether the email to you or to the visitor failed, without the address.

Still open:

- The `/chat` route sends the page text and chat history it receives to Gemini with your key. Anyone who loads a widget page can call it within the rate limit. Watch your Gemini API usage.
- The rate limits count requests per IP address. The IP is read from the `CF-Connecting-IP`, `X-Forwarded-For` and `X-Real-IP` headers before `REMOTE_ADDR`. If no proxy in front of your site overwrites those headers, a client can send its own values and avoid the limits.
- The transcript is emailed to whatever address the visitor enters, within the transcript rate limit.
- The backend writes the visitor's IP address to the PHP error log when it rejects a transcript request. Check who can read your logs.

Privacy: visitors' browsers load React from esm.sh. Your server sends the page text, the conversation and the visitor's first name to Google's Gemini API. You may need to mention this in your privacy policy.

USAGE.md explains each protection and what it does not cover.

## License

MIT. See [LICENSE](LICENSE).

## Author

Igor Lipkanou (igor@magicquickwins.com), built with Claude Sonnet 4.5.
