# TZLA Staking Platform — Codebase Overview

## What it is

A Laravel-based web application for the TZLA token on Solana. It hosts a staking portal where users connect a Solana wallet (Phantom, Solflare, and others), stake TZLA into an on-chain program, and earn tiered rewards. After the program upgrade, unstake returns principal only and rewards are claimed separately. The backend never touches user private keys — it only verifies on-chain events and keeps a local mirror of stake positions.

---

## Tech stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.x / Laravel |
| Frontend | Blade templates, TypeScript (`resources/js/staking.ts`) |
| Frontend build | Vite (`@vite` directive in staking.blade.php) |
| Blockchain | Solana — Anchor program (`sol_stake`) |
| Wallet | Wallet Standard + Phantom/Solflare adapters; mobile deep links / MWA |
| RPC / indexer | Helius (`mainnet.helius-rpc.com`) |
| Price feed | Jupiter Price API |
| Cache / queue broker | Redis |
| Queue driver | Laravel Queue (any driver; Redis recommended) |
| Database | SQL (Laravel migrations, `stake_records` table) |

---

## Directory structure (source only)

```
app/
  Http/Controllers/
    RpcProxyController.php   — forwards whitelisted RPC calls from the browser
    StakingController.php    — REST endpoints: stake, unstake, pool stats, rewards
  Jobs/
    RefreshPoolOracle.php    — scheduled job: warms Redis with on-chain account data + price
    VerifyAndRecordStake.php — queued job: verifies a stake tx and writes stake_records
    VerifyAndRecordUnstake.php — queued job: verifies an unstake tx, closes/reopens position
  Models/
    StakeRecord.php          — Eloquent model + reward maths
    User.php                 — default Laravel user (not actively used by staking)
  Providers/
    AppServiceProvider.php   — registers per-wallet rate limiter for write endpoints
  Services/
    HeliusService.php        — Helius RPC proxy + transaction / account fetchers
    JupiterPriceService.php  — TZLA/USD price oracle, Redis-backed
    SolanaOracleService.php  — Redis cache for global accounts (pool + reward vault)
    StakeVerificationService.php — Anchor discriminator + PDA verification
    RewardService.php        — projected weekly/monthly reward calculator
  Console/Commands/
    BackfillStakeRecords.php — one-time CLI backfill from on-chain UserStake accounts
    ListStakeRecords.php     — CLI inspector for the stake_records table

resources/
  views/
    welcome.blade.php        — landing page
    staking.blade.php        — staking UI (main user-facing page)
    portal.blade.php         — wrapper for iframe / external-link / coming-soon portals
    partials/
      nav.blade.php          — shared navigation bar
      nav-css.blade.php      — nav styles (included in every page)
      footer.blade.php       — footer with contract address copy widget + X link
  js/
    staking.ts               — wallet connect, stake / unstake / claim, pool stats
    wallets.ts               — multi-wallet picker (Wallet Standard)
    mobile-wallet.ts         — iPhone / Android wallet app deep links + MWA
    staking-card.ts          — canvas-based "earnings card" image generator
    app.js / bootstrap.js    — Laravel defaults
  css/
    app.css                  — global styles
```

---

## How the system works

### On-chain program

The Solana program (`sol_stake`, Anchor) maintains:
- **Pool account** — stake cap, total staked, NFT collection, pool authority
- **Stake token vault** — holds staked TZLA
- **Reward token vault** — funded by the operator; drained as rewards
- **UserStake PDA** — one per (pool, wallet) pair; tracks cumulative `stake_amount`, `nft_tier`, `last_stake_time`

Stake and unstake instructions come in three variants each (plain / golden-cNFT / golden-classic NFT) — all share the same account layout and are handled by a single verification path.

### Staking flow (browser → chain → backend)

```
1. User clicks "Cast Anchor" in staking.blade.php
2. staking.ts builds the Anchor transaction (stake / stake_with_ticket / stake_with_nft_ticket)
   → signed and submitted via Phantom
3. On success, staking.ts POSTs { wallet, amount_raw, nft_tier, stake_tx } to /api/staking/record
4. StakingController dispatches VerifyAndRecordStake job
5. Job fetches the transaction from Helius, verifies the Anchor discriminator, confirms
   the wallet is a signer, checks the amount, reads the on-chain UserStake PDA
6. PDA is proven via sha256("user_stake" ‖ pool ‖ wallet ‖ bump ‖ program_id ‖ "ProgramDerivedAddress")
7. On-chain cumulative amount + tier are written to stake_records (closing any prior open position)
```

The unstake flow mirrors this: browser submits the tx, backend queues VerifyAndRecordUnstake, which closes the open record and reopens it with the on-chain remainder if a partial unstake occurred.

### Redis oracle

`RefreshPoolOracle` runs on a scheduler tick (every few seconds) and:
- Calls `getAccountInfo` on the pool account → Redis
- Calls `getTokenAccountBalance` on the reward vault → Redis
- Calls Jupiter Price API for TZLA/USD → Redis

All three are stored "forever" (last-known-good) with a staleness flag. The browser's RPC calls to `/api/rpc` hit `HeliusService.proxyRpc`, which answers pool-account and reward-vault reads directly from Redis — Helius is only called for per-wallet reads and transaction operations.

### Reward calculation

`RewardService` and `StakeRecord` use integer (bcmath) arithmetic throughout:

```
reward_raw = amount_raw × rate_numerator × elapsed_seconds
             ────────────────────────────────────────────
             rate_denominator × seconds_per_day
```

Tier rates are configured in `config/staking.php`. All base-unit amounts are PHP string types to handle u64 safely beyond PHP_INT_MAX.

### RPC proxy security

`HeliusService::proxyRpc` only forwards a fixed allowlist of methods. Any other method returns a `-32601 Method not allowed` JSON-RPC error. Batch requests are size-limited. This prevents the Helius API key from being abused by the browser.

---

## Key config keys (in `.env` / `config/`)

| Key | Purpose |
|---|---|
| `HELIUS_API_KEY` | Helius RPC API key |
| `oracle.global.pool.address` | On-chain pool account pubkey |
| `oracle.global.reward_vault.address` | Reward vault token account |
| `oracle.price.mint` | TZLA token mint pubkey (also shown in footer as CA) |
| `oracle.price.endpoint` | Jupiter Price API URL |
| `staking.program_id` | Anchor program ID |
| `staking.rate_numerators` | Per-tier daily reward numerators |
| `staking.rate_denominator` | Common denominator for rate fractions |
| `staking.token_base_units` | 10^9 (9 decimals) |

---

## Database

One table: **`stake_records`**

| Column | Type | Notes |
|---|---|---|
| `wallet` | string | Solana pubkey |
| `amount_raw` | string | u64 cumulative staked amount |
| `nft_tier` | int | 0–4; drives reward rate |
| `staked_at` | datetime | On-chain blockTime of last stake |
| `unstaked_at` | datetime\|null | null = open position |
| `stake_tx` | string\|null | Transaction signature (unique) |
| `unstake_tx` | string\|null | Transaction signature |

A wallet can have multiple rows (history). Only rows with `unstaked_at IS NULL` are "open" and earn rewards going forward.

---

## CLI commands

```bash
# Backfill positions for wallets that staked before tracking existed
php artisan staking:backfill
php artisan staking:backfill --dry-run

# Inspect the stake_records table
php artisan stake:records
php artisan stake:records --status=active --days=30 --wallet=<pubkey>
```

---

## Deployment requirements

- PHP 8.x with `bcmath`, `gmp` extensions
- Redis (cache + queue broker)
- A running queue worker: `php artisan queue:work`
- The scheduler running: `php artisan schedule:run` (cron every minute) — `RefreshPoolOracle` is dispatched on its own sub-minute cadence from inside the scheduler
- Node / npm for the Vite build (`npm run build` produces `public/build/`)
- `.env` with all keys above filled in

---

## Next steps (brainstorm)

### Reliability
- **Queue monitoring** — add Laravel Horizon or a health-check endpoint so a stalled queue worker doesn't silently stop recording stakes
- **Scheduler heartbeat** — alert if `RefreshPoolOracle` hasn't run in >2× its cadence (stale oracle)
- **Retry dead-letter handling** — `VerifyAndRecordStake` fails permanently after 5 attempts; add a Slack/email hook on `job.failed`

### Features
- **Multi-wallet / portfolio view** — aggregate rewards across wallets owned by the same user
- **Leaderboard** — top stakers by amount or rewards earned, served from the DB
- **Reward history graph** — per-wallet time-series of accrued rewards
- **Claim log** — track each unstake as a "reward claim event" for tax/reporting exports
- **Notifications** — email or Telegram alerts when rewards cross a threshold
- **Admin dashboard** — view total stakers, pool utilisation, vault runway (days until reward vault is empty at current rate)

### On-chain / tokenomics
- **Auto-compound** — unstake → restake in one tx (requires program support)
- **Lock-up tiers** — time-locked stake for higher rates
- **Vault top-up alert** — warn when the reward vault has <N days of runway left

### Developer experience
- **Migrations + seeders** — the codebase ships without them; add `database/migrations/` so a new dev can spin up from scratch
- **Pest/PHPUnit test suite** — unit tests for `StakeVerificationService`, `RewardService`, and the bcmath reward calculations
- **Docker / Sail setup** — `docker-compose.yml` so a new owner can run locally without installing PHP/Redis manually
- **`composer.json` + `package.json`** — currently absent from this bundle; the new owner needs these to install dependencies
- **`.env.example`** — document all required env vars
- **CLAUDE.md** — add a brief developer onboarding doc for the new owner's AI assistant

### Security
- **Rate limiting on `/api/rpc`** — currently only the staking-writes limiter exists; add per-IP limits on the RPC proxy
- **Input validation on `wallet`** — validate it is a base58 Solana pubkey before dispatching jobs
- **Signature verification** — optionally require the browser to sign a nonce to prove wallet ownership before queuing a stake record (prevents spam with random wallet addresses)
