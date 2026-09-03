# TZLA Treasure Hunt — Patch Handoff

This is the original codebase with security and correctness fixes applied. The
original folder was left untouched, so you can diff the two if you want to see
exactly what moved.

**Test suite: 64 passing** (was 37 passing / 1 failing).

Run it yourself with `php artisan test`.

---

## 1. Read this first: what must be configured before going live

The app now **refuses to run insecurely** rather than silently doing the wrong
thing. That means a few settings are mandatory. Copy `.env.example` to `.env`
and fill these in:

| Variable | Why it matters |
| --- | --- |
| `SOLANA_PROVIDER=helius` | Anything else now throws on boot outside local dev. The old default was `stub`, which accepted **any** fee payment. |
| `HELIUS_API_KEY` | Required for holdings and fee verification. |
| `GAME_TREASURY_ADDRESS` | **No longer has a default.** Guesses are refused while blank. Previously it silently fell back to a hardcoded address — confirm you control whatever you put here. |
| `SOLANA_TZLA_MINT` | If blank, no wallet ever qualifies via TZLA holdings. |
| `TELEGRAM_WEBHOOK_SECRET` | The webhook rejects every request while blank. Generate one and register it (command below). |
| `APP_KEY` | `php artisan key:generate` |

Register the Telegram webhook with the secret:

```bash
curl -F "url=https://YOURDOMAIN/api/telegram/webhook" \
     -F "secret_token=YOUR_TELEGRAM_WEBHOOK_SECRET" \
     https://api.telegram.org/bot<BOT_TOKEN>/setWebhook
```

Add the scheduler to cron (nothing was scheduled before):

```
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

Fee amounts are now read from `GAME_FEE_STANDARD_SOL` and
`GAME_FEE_GOLDEN_TICKET_SOL`. The old `GAME_SUBMISSION_FEES_SOL` variable was
never read by any code — if you had values in it, they were being ignored and
the code defaults were used instead.

---

## 2. Critical fixes

### Fee payments could be replayed forever

A player paid once, then reused the same transaction signature on every
subsequent guess. Nothing recorded which signatures had been spent.

Added a `fee_payments` table with a unique index on `signature`, plus
`FeeLedger`, which claims the signature atomically. The unique constraint is
what makes it safe when two requests race. A spent signature cannot be reused by
the same wallet or by a different one.

Covered by `test_fee_signature_cannot_be_replayed` and
`test_fee_signature_cannot_be_reused_by_a_different_wallet`.

### The fee verifier did not check who actually paid

`HeliusFeeVerifier` had a comment saying "sender must appear as a signer" but the
code only checked whether the address appeared anywhere in `accountKeys` — which
includes every account a transaction touches, signer or not. It also measured the
treasury's total balance change rather than what this wallet sent.

Combined with the replay bug, anyone could find one large payment to the
treasury and use it as an unlimited free pass.

Now it reads `numRequiredSignatures` from the transaction header and confirms the
wallet is within the actual signer range, then confirms the signer's own balance
fell by at least the amount the treasury gained. Also added a freshness window
(`GAME_FEE_MAX_AGE_SECONDS`, default 1 hour) so old transactions cannot be
recycled, and it now refuses everything if no treasury is configured rather than
comparing against an empty string.

Covered by `tests/Unit/Services/HeliusFeeVerifierTest.php`.

### The non-verifying stub was the default

`AppServiceProvider` used `match` with `default => new StubFeeVerifier`, so any
value other than exactly `helius` — a typo, a missing variable, a copied
`.env.example` — meant free guesses, with nothing logged.

The provider is now resolved explicitly: unknown values throw, and `stub` throws
outside `local`/`testing`.

Covered by `tests/Feature/SolanaProviderBindingTest.php`.

### The Telegram webhook was unauthenticated

`POST /api/telegram/webhook` accepted any JSON. The only check was comparing the
chat id inside the payload, which the caller controls. Since the bot exposes
`/wordlist` (prints every answer) and `/wordset` (rewrites them), guessing the
chat id was enough to own the game.

Now verifies Telegram's `X-Telegram-Bot-Api-Secret-Token` header with
`hash_equals`, and fails closed when no secret is configured.

Covered by `tests/Feature/TelegramWebhookTest.php`.

---

## 3. Correctness and fairness fixes

**Players were charged for guesses that were thrown away.** The fee was verified
before `GuessService` checked whether the word was already solved or attempts
were exhausted — those cases returned a normal 200 with no guess recorded and no
refund. Those checks now run *before* payment and return `409` with an explicit
"you have not been charged" message.

**Attempts were unbounded.** `1 + nftCount()` meant a wallet with 200 NFTs got
201 attempts per word. Now capped by `GAME_ATTEMPTS_MAX` (default 5).

**The spam guard contradicted the attempt policy.** Attempts scaled with NFTs but
the rate limit was hardcoded to 3/minute, so holders were sold attempts they
could not spend. This was the pre-existing failing test. The limit is now
configurable via `GAME_SPAM_MAX_PER_MINUTE` (default 10) and must stay at or
above `GAME_ATTEMPTS_MAX`.

**Draft weeks leaked.** `GET /api/weeks` returned inactive weeks with titles and
reward descriptions, even though the Telegram bot creates them inactive
specifically to hide them. Now filtered.

**CSRF was not enforced.** The frontend was faithfully sending `X-XSRF-TOKEN` but
nothing validated it. Enabled on the API group, with the Telegram webhook
exempted since it authenticates via its own secret.

**Race on word completion.** Two concurrent correct guesses could both pass the
`hasSolved` check and collide on the unique index, throwing a 500 after payment.
Now uses `firstOrCreate`.

**N+1 queries** in both week endpoints, which issued a query per word per
request. Now batched.

**Missing storage directories.** `storage/framework/views` and `sessions` were
absent, so a fresh clone returned a 500 on every page. Added with `.gitignore`
files.

---

## 4. The game is now actually playable

This is the largest functional change. Previously the frontend **could not
submit a single guess**: it sent only `{ guess }` while the API required
`fee_signature`, so every submission failed validation with a 422. There was no
payment code anywhere in the client.

Added:

- `resources/js/payment.js` — builds a SOL transfer to the treasury and sends it
  through Phantom's `signAndSendTransaction`, returning the signature.
- `GET /api/game-config` — tells the browser the treasury address and its fee
  tier. Previously that information only appeared inside a 402 error response.
- `GET /api/solana/blockhash` — server-side proxy so the browser can build a
  transaction without ever seeing the Helius API key. There is a test asserting
  the key does not leak.
- Confirmation retry. Phantom returns as soon as a transaction is submitted, not
  confirmed, so the client retries the guess for ~15s while the network catches
  up. Retrying is safe because a signature is only consumed on success.

**The response contract was also broken.** The client branched on `is_correct`
and `was_already_solved`, neither of which the API returned, so a correct answer
would always have rendered "Nay." The API now returns `is_correct`, and the
client reads it.

Also: real error messages are surfaced for 402/429/403/409 instead of raw codes
like `invalid_fee_payment`; ineligible wallets are told why instead of getting
`not_eligible`; and optional display-name and payout-address fields were added,
since `WinnerLogger` records them but no UI ever collected them.

Assets are rebuilt. The committed bundle was stale — it was missing the
reward-detail feature that already existed in source.

**In local development** (`SOLANA_PROVIDER=stub`), `payments_enabled` comes back
false and the client skips the real transfer, so you can play through without
needing devnet SOL.

---

## 5. What I could not do

- **No end-to-end payment test on real infrastructure.** Everything is verified
  against faked RPC responses. Before launch, do one real guess on devnet or
  with a small mainnet amount and confirm SOL arrives in the treasury and the
  guess registers.
- **Confirm the treasury address.** The old hardcoded fallback was
  `TZLA26BrLtNQZDq6C1ZdAmRcpKGn8V6Dk7Vm1S2vjT3`. I removed it rather than guess
  whether it is real.
- **Payouts are still manual.** Winning writes a log line and sends a Telegram
  message. Nothing moves funds. That was true before and I did not change it.
- **The Telegram bot still has no per-user admin check.** Any member of the
  configured chat can run every command including `/wordlist`. The webhook is
  now authenticated, so this is a "trust everyone in the group" question rather
  than an open door, but consider an admin allowlist.
- **`/how-it-works` mentions Monero/XMR** while the game uses SOL and TZLA. Looks
  like copy from another project. Left alone since it is a content decision.

---

## 6. Running it locally

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
npm run build
php artisan serve
```

Requires PHP 8.3+ with the `sodium` extension (used for wallet signature
verification) and `pdo_sqlite`.

`php artisan test` should report **64 passing**.

---

## 7. Launch rollout (Week 1 only)

As of the latest patch, treasure hunt play is intentionally limited:

| Setting | Default | Effect |
| --- | --- | --- |
| `GAME_MAX_PLAYABLE_WEEK` | `1` | Weeks with `number > 1` return **403 week_locked** even if `starts_at` is in the past and the week is active. Raise this when opening Week 2, 3, etc. |
| `GAME_UNLIMITED_ATTEMPT_WEEKS` | `1` | Listed weeks ignore the `GAME_ATTEMPTS_MAX` cap. Week 1 players can submit bundles until they clear it; **each submit still requires its own fee**. |

To open Week 2 later:

```env
GAME_MAX_PLAYABLE_WEEK=2
# Remove 1 from unlimited list if Week 2 should use normal attempt caps:
GAME_UNLIMITED_ATTEMPT_WEEKS=
```

The frontend How-to-Play copy and locked-week popup message reflect this rollout. `GET /api/game-config` exposes `max_playable_week` and `unlimited_attempt_weeks` for the client.
