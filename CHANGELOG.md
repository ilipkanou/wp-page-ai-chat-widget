# Changelog

## 2.8.2 Lite (2026-10-14)

Brings Lite up to date with the fixes and features of the full edition, version 2.8.2. Lite still sends transcripts by email only and saves nothing in WordPress.

### Fixed

- **The chat broke on cached pages** with `403 "Cookie check failed"` once the cached copy was older than the WordPress security token (12 to 24 hours). A new `POST /nonce` route returns a fresh token; the widget asks for one and retries once. The widget sends no cookies, so the token always matches a logged-out visitor.
- **The widget did not appear for visitors who did not scroll or tap at once,** on sites that delay inline scripts (for example LiteSpeed Cache "Delay JS"). The widget's settings were in an inline script that had not run yet, so it crashed. The settings (REST address and security token) are now data attributes on the widget's `<div>`.
- **The rate limits blocked real visitors early.** WordPress runs a route's permission check a second time for each request (to build the `Allow` header), so every request counted twice, and every request restarted the timer. Now each request counts once, in fixed clock-minute (chat) and clock-hour (transcript) windows.
- **The name fallback used the whole first message as the name** ("Nice to meet you, Hello. My name is Bob.!") when the AI could not read it. The widget now finds the first name itself.

### Added

- **"Get this chat by email" bar** pinned under the chat header. The visitor can give an email address at any time. The chat so far is emailed after the assistant's first answer, the visitor keeps chatting, and the full transcript goes to the same address when the chat ends.
- **An illustrated avatar** for the assistant on the chat button and in the header, built into the code (no external request). Source images in `assets/`.
- **A test kit** in `tests/`: runs the widget in a throwaway local WordPress with a fake AI.

### Upgrade from 2.8 Lite

Replace the code of both snippets: the new frontend needs the new backend route. Then put back your own settings (`$admin_email`, `$email_subject`, `$display_on_pages`, and any numbers you changed) and clear your page cache.

## 2.8 Lite (published 2026-10-14)

- First public release: two PHP snippets, transcripts by email only.
- Before publishing: the AI model became a server-side setting (`GEMINI_MODEL`), the visitor's email address was removed from the error log, and the hotlinked background image was removed.
