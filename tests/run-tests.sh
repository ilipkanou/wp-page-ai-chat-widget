#!/bin/bash
# Run the test kit against a throwaway local WordPress (WordPress Playground, runs in Node).
#   tests/run-tests.sh [--with-rate-limit]
# Needs: Node, and Playwright installed globally (npm i -g playwright && playwright install chromium).
# Uses a fake Gemini (tests/harness/zz-test-harness.php): no API key, no cost, no email sent.
set -u
PLAYGROUND="@wp-playground/cli@3.1.55"
PORT="${PORT:-9400}"
R="$(cd "$(dirname "$0")/.." && pwd)"
RATE="${1:-}"
export NODE_PATH="$(npm root -g)"
WORK="$(mktemp -d)"; SHOTS="$WORK/screenshots"; mkdir -p "$WORK/mu-plugins" "$SHOTS"

# The snippets become must-use plugins. The frontend's example page IDs are replaced
# with the test page (ID 741, created by the harness) in this temporary copy only.
cp "$R/backend-snippet.php" "$WORK/mu-plugins/ai-chat-backend.php"
node -e '
  const fs = require("fs");
  const s = fs.readFileSync(process.argv[1], "utf8");
  const out = s.replace(/\$display_on_pages = \[[\s\S]*?\];/, "$display_on_pages = [ 741 ];");
  if (out === s) { console.error("could not set the test page ID"); process.exit(1); }
  fs.writeFileSync(process.argv[2], out);
' "$R/frontend-snippet.php" "$WORK/mu-plugins/ai-chat-frontend.php" || exit 1
cp "$R/tests/harness/zz-test-harness.php" "$WORK/mu-plugins/"

# Start a fresh test WordPress (new empty database each time).
BASE="http://127.0.0.1:$PORT"
start_site() {
  echo "== Starting a fresh test WordPress on port $PORT"
  npx -y "$PLAYGROUND" server --port="$PORT" --mount="$WORK/mu-plugins:/wordpress/wp-content/mu-plugins" > "$WORK/server.log" 2>&1 &
  SERVER=$!
  for i in $(seq 1 90); do grep -q "Ready!" "$WORK/server.log" && break; sleep 2; done
  grep -q "Ready!" "$WORK/server.log" || { echo "Test WordPress did not start:"; tail -5 "$WORK/server.log"; exit 1; }
  curl -sL -o /dev/null "$BASE/?page_id=741"   # first request creates the test page
}
stop_site() {   # npx starts child processes; stop all of them, matched by this run's folder
  pkill -f -- "--mount=$WORK/mu-plugins" 2>/dev/null; wait $SERVER 2>/dev/null; sleep 2; : > "$WORK/server.log"
}
SERVER=""
trap 'stop_site' EXIT
start_site

FAIL=0
echo; echo "== 1. Name fallback";        node "$R/tests/test-name-fallback.js" "$R/frontend-snippet.php" || FAIL=1
echo; echo "== 2. No interaction (scripts delayed as by LiteSpeed)"; node "$R/tests/test-no-interaction.js" "$BASE" || FAIL=1
echo; echo "== 3. Features";             node "$R/tests/test-features.js" "$BASE" "$SHOTS" | tee "$WORK/features.txt"; grep -q " 0 fail" "$WORK/features.txt" || FAIL=1
# Chat requests are limited per clock minute, so start the next test in a new minute.
while [ "$(date +%S | sed 's/^0//')" -gt 2 ]; do sleep 1; done
echo; echo "== 4. Chat flow and token"; node "$R/tests/test-nonce-flows.js" "$BASE" | tee "$WORK/flows.txt"; grep -q " 0 fail" "$WORK/flows.txt" || FAIL=1
if [ "$RATE" = "--with-rate-limit" ]; then
  # The earlier tests used part of this minute's and hour's allowance, so use a fresh site.
  stop_site; start_site
  echo; echo "== 5. Rate limits (about 4 minutes)"; bash "$R/tests/rate-limit-test.sh" "$BASE" "lite" || FAIL=1
fi
echo; echo "Screenshots: $SHOTS"
[ $FAIL = 0 ] && echo "ALL TESTS PASSED" || echo "SOME TESTS FAILED"
exit $FAIL
