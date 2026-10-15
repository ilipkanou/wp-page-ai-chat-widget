#!/bin/bash
# Rate-limit test for the widget backend. Test-only; runs against a local WordPress Playground site.
# Expected on v2.8.1: chat 21, lead 11, slow chat 0 blocks. Takes about 4 minutes.
# Needs a fresh site: earlier requests in the same minute or hour use up the allowance.
# Usage: rate-limit-test.sh <base-url> <label>
B="$1"; L="$2"
N=$(curl -sL "$B/?page_id=741" | /usr/bin/grep -oE '(nonce: "|data-nonce=")[0-9a-f]+' | /usr/bin/grep -oE '[0-9a-f]{10}$')
[ -z "$N" ] && { echo "$L: no nonce found on page"; exit 1; }

post() { curl -s -o /dev/null -w '%{http_code}' -X POST -H 'Content-Type: application/json' -H "X-WP-Nonce: $N" --data '{}' "$B/?rest_route=/my-chat-assistant/v1/$1"; }

# Count requests that pass before the first 429 (stops at the first 429).
burst() {
  local ep=$1 max=$2 i code
  for i in $(seq 1 $max); do
    code=$(post $ep)
    [ "$code" = "429" ] && { echo $((i - 1)); return; }
  done
  echo "$max+ (no 429)"
}

# Start the bursts early in a clock minute, so a fixed per-minute window cannot split them.
while [ $(date +%S | sed 's/^0//') -gt 20 ]; do sleep 1; done
CHAT=$(burst chat 25); echo "$L: chat burst passed before first 429: $CHAT"
LEAD=$(burst lead 15); echo "$L: lead burst passed before first 429: $LEAD"

# Let both the old sliding timer (60s after last allowed request) and the current minute expire.
sleep 65
slow429=0; first=""
for i in $(seq 1 26); do
  code=$(post chat)
  if [ "$code" = "429" ]; then slow429=$((slow429 + 1)); [ -z "$first" ] && first=$i; fi
  sleep 5
done
echo "$L: slow chat (26 requests, 1 per 5s): 429 count=$slow429 first429=${first:-none}"

# Verdict. The code checks "more than 20" and "more than 10", so 21 and 11 requests pass.
if [ "$CHAT" = "21" ] && [ "$LEAD" = "11" ] && [ "$slow429" = "0" ]; then echo "RATE LIMITS: PASS"; else echo "RATE LIMITS: FAIL (want chat 21, lead 11, slow 0)"; exit 1; fi
