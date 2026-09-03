# TZLA Staking Upgrade

Rebuilt staking website + patched Solana program for the live TZLA pool.

**GitHub (Jose):** https://github.com/JoseCatStacks/TZLA-Upgrade

---

## Start here

| If you want to… | Read this |
|-----------------|-----------|
| **Upgrade the on-chain program** (unstake without empty vault) | [`UPGRADE.md`](./UPGRADE.md) |
| **Run the website locally** | Steps below |
| **Understand the codebase** | [`OVERVIEW.md`](./OVERVIEW.md) |

---

## What’s broken on mainnet today

Unstake tries to pay **all pending rewards** from the reward vault in the **same transaction**. The vault is nearly empty (~2 TZLA), so most wallets cannot exit.

### What this repo fixes (after program upgrade)

1. **Unstake = principal only** — no longer blocked by an empty treasury  
2. **Claim Rewards = separate button** — pays from the vault when funded  
3. **Empty vault never traps deposits** — claim pays `min(pending, vault balance)`  
4. **Wallet connect** — Phantom, Solflare, and other Solana wallets (desktop + mobile)

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

## Run the website

**Needs:** PHP 8.3+, Composer, Node 20+, a Helius API key ([dashboard.helius.dev](https://dashboard.helius.dev))

```bash
cp .env.example .env
# put your HELIUS_API_KEY in .env

composer install
php artisan key:generate
php artisan migrate

npm install
npm run build

php artisan serve
```

Open: http://127.0.0.1:8000/staking

Optional — sync live positions into the local DB:

```bash
php artisan staking:backfill
```

**Never commit `.env`.** Use your own Helius key; do not reuse a shared one in production.

---

## Upgrade the program (Jose)

Use the **TZLA26Br…** wallet. Full steps: **[`UPGRADE.md`](./UPGRADE.md)**

Short version:

```bash
cd onchain
anchor build
anchor upgrade target/deploy/sol_stake.so --program-id 3pFCija5VgaUxJgoKMoGRCk79c2pkEgUA9NBzRPo8xjJ
```

Then fund the reward vault when you want claims paid (~12.7k TZLA owed at last audit).

After upgrade, rebuild the site frontend (`npm run build`) so **Claim Rewards** matches the new instruction.

---

## After upgrade — how users use it

| Button | What it does |
|--------|----------------|
| **Claim Rewards** | Pays accrued TZLA from the reward vault (up to vault balance) |
| **Weigh Anchor (Unstake)** | Returns staked TZLA only — does not need the treasury |

Claim **before** unstaking if you want rewards. Unstake resets the accrual timer.

---

## Repo layout

```
onchain/programs/sol_stake/   ← Anchor program (claim_rewards + safe unstake)
app/                          ← Laravel API / jobs
resources/js/staking.ts       ← wallet connect, stake / unstake / claim
resources/js/wallets.ts       ← multi-wallet picker
resources/js/mobile-wallet.ts ← iPhone / Android wallet deep links
config/staking.php            ← mainnet addresses
UPGRADE.md                    ← program deploy steps
```

---

## Safety checklist before push / deploy

- [ ] `.env` is **not** in git (listed in `.gitignore`)
- [ ] No Helius / private keys in the commit
- [ ] Jose’s `TZLA26Br` key is the only wallet that can upgrade
- [ ] Do **not** make the program immutable until this upgrade is live and tested
