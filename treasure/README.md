# TZLA Treasure Hunt

Weekly word hunt for the TZLA site. This is the **Treasure** tab app (embedded from the portal via `PORTAL_TREASURE_URL`).

## Read this first

**[`HANDOFF.md`](./HANDOFF.md)** — what was patched, required `.env` values, Telegram, local boot.

## Quick start

```bash
cp .env.example .env
php artisan key:generate
# set HELIUS_API_KEY, SOLANA_PROVIDER=helius, GAME_TREASURY_ADDRESS,
# SOLANA_TZLA_MINT, SOLANA_NFT_COLLECTION (see HANDOFF.md)

composer install
touch database/database.sqlite
php artisan migrate --seed
npm install && npm run build
php artisan serve --port=8399
```

Then on the **portal/staking** app `.env`:

```env
PORTAL_TREASURE_URL=http://127.0.0.1:8399
```

## Production must-haves

| Variable | Notes |
|----------|--------|
| `SOLANA_PROVIDER=helius` | Stub blocked outside local |
| `HELIUS_API_KEY` | Fee + holdings |
| `GAME_TREASURY_ADDRESS` | Guess fees land here |
| `SOLANA_TZLA_MINT` | Eligibility |
| `SOLANA_NFT_COLLECTION` | NFT attempt tiers |
| `GAME_MAX_PLAYABLE_WEEK=1` | Only weeks ≤ this number unlock. **Week 2+ stay locked** until you raise this. |
| `GAME_UNLIMITED_ATTEMPT_WEEKS=1` | Week 1 has **unlimited** bundle attempts (fee still due each submit). |
| `TELEGRAM_WEBHOOK_SECRET` | If Telegram bot enabled |

`php artisan test` should report **64 passing**.
