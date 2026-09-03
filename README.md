# TZLA Website

One product site (Treasure / Staking / NFT / Swap tabs). Two Laravel apps in this repo:

| Part | Folder | What it is |
|------|--------|------------|
| **Portal + Staking** | repo root | Nav, Staking tab, embeds Treasure when configured |
| **Treasure Hunt** | [`treasure/`](./treasure/) | Weekly word game (patched — guesses actually pay + submit) |
| **On-chain staking program** | [`onchain/`](./onchain/) | Upgrade so unstake isn’t blocked by empty reward vault |

**GitHub:** https://github.com/JoseCatStacks/TZLA-Upgrade

---

## Start here

| If you want to… | Read this |
|-----------------|-----------|
| **Run staking / portal** | [Run staking](#run-staking--portal) below |
| **Run treasure hunt** | [`treasure/HANDOFF.md`](./treasure/HANDOFF.md) |
| **Upgrade the staking program** | [`UPGRADE.md`](./UPGRADE.md) |
| **Staking codebase notes** | [`OVERVIEW.md`](./OVERVIEW.md) |

---

## How the Treasure tab works

The nav **Treasure** tab loads the hunt app via iframe when set:

```env
PORTAL_TREASURE_URL=https://your-treasure-domain.com
```

Until that URL is set and the hunt is deployed, the tab shows “coming soon.”

---

## What’s broken on mainnet (staking)

Unstake tries to pay **all pending rewards** from the reward vault in the **same transaction**. The vault is nearly empty, so most wallets cannot exit.

### Fixed after program upgrade (`UPGRADE.md`)

1. **Unstake = principal only**
2. **Claim Rewards** = separate instruction / button
3. Empty vault never traps deposits
4. Multi-wallet connect (Phantom, Solflare, mobile)

### Treasure hunt (already fixed in `treasure/`)

Guesses failed because the client never sent a fee payment; plus fee-replay and related bugs. See [`treasure/HANDOFF.md`](./treasure/HANDOFF.md). Deploy that app + set `PORTAL_TREASURE_URL` — **no Solana program upgrade** for treasure.

---

## Mainnet addresses

| Role | Address |
|------|---------|
| Program | `3pFCija5VgaUxJgoKMoGRCk79c2pkEgUA9NBzRPo8xjJ` |
| Pool | `2yYgVz8CDzvMFYZ2cfMy854RETrafVYSAAaUUJw9bAVV` |
| Upgrade / pool authority | `TZLA26BrLtNQZDq6C1ZdAmRcpKGn8V6Dk7Vm1S2vjT3` |
| TZLA mint | `4tWMJCW6tdpVUkwDpX1NEQURbtuQDg7H9DfkjEpGnq5D` |
| Reward vault | `DqjRmDNu3JRpgnUhjBrGERF9Czir569H8BcxBZt5RtQ3` |
| NFT collection | `8cTpLj5JkptcbYfonkRWMaa7MRAsrqqxHQYKz6J1rTQw` |

---

## Run staking / portal

**Needs:** PHP 8.3+, Composer, Node 20+, Helius API key

```bash
cp .env.example .env
# HELIUS_API_KEY=...
# PORTAL_TREASURE_URL=http://127.0.0.1:8399   # optional local hunt URL

composer install
php artisan key:generate
php artisan migrate

npm install
npm run build

php artisan serve
```

Open: http://127.0.0.1:8000/staking  

Optional: `php artisan staking:backfill`

---

## Run treasure hunt

```bash
cd treasure
cp .env.example .env
# SOLANA_PROVIDER=helius
# HELIUS_API_KEY=...
# GAME_TREASURY_ADDRESS=...
# SOLANA_TZLA_MINT=4tWMJCW6tdpVUkwDpX1NEQURbtuQDg7H9DfkjEpGnq5D
# SOLANA_NFT_COLLECTION=8cTpLj5JkptcbYfonkRWMaa7MRAsrqqxHQYKz6J1rTQw

composer install
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
npm install && npm run build
php artisan serve --port=8399
```

Details + security notes: **[`treasure/HANDOFF.md`](./treasure/HANDOFF.md)**

---

## Upgrade the staking program (Jose)

Wallet: **`TZLA26Br…`**. Full steps: **[`UPGRADE.md`](./UPGRADE.md)**

```bash
cd onchain
anchor build
anchor upgrade target/deploy/sol_stake.so --program-id 3pFCija5VgaUxJgoKMoGRCk79c2pkEgUA9NBzRPo8xjJ
```

Then fund the reward vault when claims should pay out.

---

## Repo layout

```
app/ resources/ …     ← portal + staking site (root)
onchain/              ← Anchor program patch
treasure/             ← patched treasure hunt app
UPGRADE.md            ← staking program deploy
treasure/HANDOFF.md   ← treasure deploy + what was fixed
```

---

## Safety

- Never commit `.env` (root or `treasure/`)
- Use Jose’s own Helius key in production
- Do **not** make the staking program immutable until the upgrade is live and tested
