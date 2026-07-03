#!/usr/bin/env bash
#
# PHP AI Labyrinth — smoke test
#
# Lints every PHP file, boots the built-in PHP web server, and verifies the
# behavior the README promises: deterministic pages, per-page variety,
# noindex headers, direct rendering through the bot-monitor gateway, and
# localized dates. Exits non-zero on the first failure.
#
# Usage: tests/smoke.sh   (from the repository root; requires php + curl)

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PORT="${SMOKE_PORT:-8973}"
BASE="http://127.0.0.1:${PORT}"
TMP="$(mktemp -d)"
SERVER_PID=""

cleanup() {
    [ -n "$SERVER_PID" ] && kill "$SERVER_PID" 2>/dev/null || true
    rm -rf "$TMP"
}
trap cleanup EXIT

fail() { echo "FAIL: $1" >&2; exit 1; }
pass() { echo "  ok: $1"; }

echo "== Lint =="
for f in "$ROOT"/*.php; do
    php -l "$f" >/dev/null || fail "syntax error in $f"
done
pass "php -l on all files"

echo "== Boot server =="
php -S "127.0.0.1:${PORT}" -t "$ROOT" >"$TMP/server.log" 2>&1 &
SERVER_PID=$!
for i in $(seq 1 20); do
    curl -s -o /dev/null "$BASE/labyrinth.php?p=boot" && break
    sleep 0.25
done

echo "== Behavior =="

# Pages must not fatal (a parse error in an include still yields HTTP 200
# from the built-in server, so check for actual article markup).
curl -s "$BASE/labyrinth.php?p=abc123&d=5" -o "$TMP/a.html"
grep -q '<article>' "$TMP/a.html" || fail "labyrinth page did not render an article"
pass "page renders"

# Determinism: identical URL → identical body
curl -s "$BASE/labyrinth.php?p=abc123&d=5" -o "$TMP/b.html"
cmp -s "$TMP/a.html" "$TMP/b.html" || fail "same page id produced different content"
pass "deterministic content"

# Variety: different page id → different body
curl -s "$BASE/labyrinth.php?p=xyz789&d=5" -o "$TMP/c.html"
cmp -s "$TMP/a.html" "$TMP/c.html" && fail "different page ids produced identical content"
pass "per-page variety"

# Outgoing labyrinth links present
grep -q '?p=' "$TMP/a.html" || fail "no outgoing labyrinth links found"
pass "outgoing links"

# noindex headers
curl -sI "$BASE/labyrinth.php?p=abc123" | grep -qi 'X-Robots-Tag: noindex' \
    || fail "X-Robots-Tag noindex header missing"
pass "noindex header"

# Localized dates: German month name present, English never
grep -qE '\. (Januar|Februar|März|April|Mai|Juni|Juli|August|September|Oktober|November|Dezember) 20[0-9]{2}' "$TMP/a.html" \
    || fail "no localized (German) article date found"
grep -qE 'January|February|March|April 2|May 2|June|July|August 2|September 2|October|November 2|December' "$TMP/a.html" \
    && fail "English month name leaked into the page" || true
pass "localized dates"

# Bot monitor renders the labyrinth directly — HTTP 200, no redirect
STATUS=$(curl -s -o "$TMP/m.html" -w '%{http_code}' "$BASE/bot-monitor.php")
[ "$STATUS" = "200" ] || fail "bot-monitor.php returned HTTP $STATUS (expected 200)"
grep -q '<article>' "$TMP/m.html" || fail "bot-monitor.php did not render a labyrinth article"
pass "bot-monitor gateway renders directly"

echo "All smoke tests passed."
