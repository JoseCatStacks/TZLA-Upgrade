# Program upgrade (Jose)

Use wallet **`TZLA26BrLtNQZDq6C1ZdAmRcpKGn8V6Dk7Vm1S2vjT3`** (upgrade authority + pool authority).

## Why upgrade

Live program pays pending rewards **before** returning principal. Empty reward vault → unstake fails for most wallets.

This patch:

1. **`unstake`** — returns principal only  
2. **`claim_rewards`** — new instruction; pays from treasury separately  
3. Claim pays `min(pending, vault_balance)` — empty vault never traps deposits  

## Steps

### 1. Build

Needs Solana CLI + Anchor 0.31.x. Point `Anchor.toml` `wallet` at the TZLA26Br keypair.

```bash
cd onchain
anchor build
solana program show 3pFCija5VgaUxJgoKMoGRCk79c2pkEgUA9NBzRPo8xjJ
```

### 2. Upgrade

```bash
anchor upgrade target/deploy/sol_stake.so --program-id 3pFCija5VgaUxJgoKMoGRCk79c2pkEgUA9NBzRPo8xjJ
```

Confirm authority is still `TZLA26Br...`. **Do not** make the program immutable yet.

### 3. Fund the reward vault (when ready to pay claims)

| | |
|--|--|
| Vault | `DqjRmDNu3JRpgnUhjBrGERF9Czir569H8BcxBZt5RtQ3` |
| Approx. liability (last audit) | ~12,700 TZLA |
| Emissions | ~751 TZLA/day |

Use the existing `fund_vault` instruction (script / Solana Playground). Unstake works without funding; **claims** need TZLA in the vault.

### 4. Site

```bash
npm run build
```

So the **Claim Rewards** button matches the new on-chain instruction.

## User flow after upgrade

| Action | Result |
|--------|--------|
| Claim Rewards | Pays accrued rewards (capped by vault). Resets accrual timer. |
| Unstake | Principal only. Does not touch reward vault. |

**Claim before unstaking** if users want rewards — unstake also resets `last_stake_time`.

Staking (adding more) still settles pending rewards on restake.

## Not changed by this patch

- Reward rates (golden 0.369%/day still expensive)  
- Pool authority (no transfer instruction)  
- Program ID / pool / vault addresses  

The old handover `lib.rs` did not compile (broken fee transfer + missing golden consts). Those are fixed here so the upgrade builds.
