import { Buffer } from 'buffer';
(globalThis as any).Buffer = Buffer;

import { AnchorProvider, Program, BN } from '@coral-xyz/anchor';
import { initStakingCard, refreshEarningsSummaryCard, clearEarningsSummaryCard } from './staking-card';
import type { Adapter } from '@solana/wallet-adapter-base';
import { Connection, PublicKey, TransactionInstruction } from '@solana/web3.js';
import {
    getAssociatedTokenAddress,
    createAssociatedTokenAccountIdempotentInstruction,
    getAccount,
    TOKEN_PROGRAM_ID,
} from '@solana/spl-token';
import {
    canConnect,
    forgetWallet,
    installUrl,
    isInstalled,
    lastWalletName,
    listWalletAdapters,
    rememberWallet,
    waitForWallets,
    walletStatusLabel,
} from './wallets';
import {
    hasInjectedWallet,
    isIos,
    isMobileDevice,
    isRedirectableAdapter,
    mobileWalletHint,
    needsWalletAppBrowser,
    redirectAdapterToApp,
    waitForInjectedWallet,
    walletAppLinks,
} from './mobile-wallet';

// ── IDL ──────────────────────────────────────────────────────────────────────

const IDL = {
    address: '3pFCija5VgaUxJgoKMoGRCk79c2pkEgUA9NBzRPo8xjJ',
    metadata: { name: 'sol_stake', version: '0.1.0', spec: '0.1.0' },
    instructions: [
        {
            name: 'create_user_stake',
            discriminator: [15, 76, 158, 149, 50, 56, 173, 23],
            accounts: [
                { name: 'user', writable: true, signer: true },
                { name: 'pool' },
                {
                    name: 'user_stake', writable: true,
                    pda: { seeds: [{ kind: 'const', value: [117,115,101,114,95,115,116,97,107,101] }, { kind: 'account', path: 'pool' }, { kind: 'account', path: 'user' }] },
                },
                { name: 'system_program', address: '11111111111111111111111111111111' },
            ],
            args: [],
        },
        {
            name: 'fund_vault',
            discriminator: [26, 33, 207, 242, 119, 108, 134, 73],
            accounts: [
                { name: 'pool' },
                { name: 'reward_token_vault', writable: true, relations: ['pool'] },
                { name: 'authority_token_account', writable: true },
                { name: 'authority', signer: true, relations: ['pool'] },
                { name: 'token_program', address: 'TokenkegQfeZyiNwAJbNbGKPFXCWuBvf9Ss623VQ5DA' },
            ],
            args: [{ name: 'amount', type: 'u64' }],
        },
        {
            name: 'initialize',
            discriminator: [175, 175, 109, 31, 13, 152, 155, 237],
            accounts: [
                { name: 'user', writable: true, signer: true },
                { name: 'stake_token_mint' },
                {
                    name: 'pool', writable: true,
                    pda: { seeds: [{ kind: 'const', value: [115,116,97,107,101,95,112,111,111,108] }, { kind: 'account', path: 'user' }] },
                },
                {
                    name: 'stake_token_vault', writable: true,
                    pda: { seeds: [{ kind: 'const', value: [115,116,97,107,101,95,118,97,117,108,116] }, { kind: 'account', path: 'pool' }] },
                },
                {
                    name: 'reward_token_vault', writable: true,
                    pda: { seeds: [{ kind: 'const', value: [114,101,119,97,114,100,95,118,97,117,108,116] }, { kind: 'account', path: 'pool' }] },
                },
                {
                    name: 'authority',
                    pda: { seeds: [{ kind: 'const', value: [112,111,111,108,95,97,117,116,104,111,114,105,116,121] }, { kind: 'account', path: 'pool' }] },
                },
                { name: 'rent', address: 'SysvarRent111111111111111111111111111111111' },
                { name: 'clock', address: 'SysvarC1ock11111111111111111111111111111111' },
                { name: 'token_program', address: 'TokenkegQfeZyiNwAJbNbGKPFXCWuBvf9Ss623VQ5DA' },
                { name: 'system_program', address: '11111111111111111111111111111111' },
            ],
            args: [
                { name: 'stake_cap', type: 'u64' },
                { name: 'nft_collection', type: 'pubkey' },
            ],
        },
        {
            name: 'stake',
            discriminator: [206, 176, 202, 18, 200, 209, 179, 108],
            accounts: [
                { name: 'user', writable: true, signer: true },
                { name: 'pool', writable: true },
                { name: 'user_token_account', writable: true },
                { name: 'stake_token_vault', writable: true, relations: ['pool'] },
                { name: 'reward_token_vault', writable: true, relations: ['pool'] },
                {
                    name: 'user_stake', writable: true,
                    pda: { seeds: [{ kind: 'const', value: [117,115,101,114,95,115,116,97,107,101] }, { kind: 'account', path: 'pool' }, { kind: 'account', path: 'user' }] },
                },
                {
                    name: 'authority',
                    pda: { seeds: [{ kind: 'const', value: [112,111,111,108,95,97,117,116,104,111,114,105,116,121] }, { kind: 'account', path: 'pool' }] },
                },
                {
                    name: 'fee_vault', writable: true,
                    pda: { seeds: [{ kind: 'const', value: [102,101,101,95,118,97,117,108,116] }, { kind: 'account', path: 'pool' }] },
                },
                { name: 'rent', address: 'SysvarRent111111111111111111111111111111111' },
                { name: 'clock', address: 'SysvarC1ock11111111111111111111111111111111' },
                { name: 'token_program', address: 'TokenkegQfeZyiNwAJbNbGKPFXCWuBvf9Ss623VQ5DA' },
                { name: 'system_program', address: '11111111111111111111111111111111' },
            ],
            args: [{ name: 'stake_amount', type: 'u64' }],
        },
        {
            name: 'unstake',
            discriminator: [90, 95, 107, 42, 205, 124, 50, 225],
            accounts: [
                { name: 'user', writable: true, signer: true },
                { name: 'pool', writable: true, relations: ['user_stake'] },
                { name: 'user_token_account', writable: true },
                { name: 'stake_token_vault', writable: true, relations: ['pool'] },
                { name: 'reward_token_vault', writable: true, relations: ['pool'] },
                {
                    name: 'user_stake', writable: true,
                    pda: { seeds: [{ kind: 'const', value: [117,115,101,114,95,115,116,97,107,101] }, { kind: 'account', path: 'pool' }, { kind: 'account', path: 'user' }] },
                },
                {
                    name: 'authority',
                    pda: { seeds: [{ kind: 'const', value: [112,111,111,108,95,97,117,116,104,111,114,105,116,121] }, { kind: 'account', path: 'pool' }] },
                },
                { name: 'token_program', address: 'TokenkegQfeZyiNwAJbNbGKPFXCWuBvf9Ss623VQ5DA' },
            ],
            args: [{ name: 'amount', type: 'u64' }],
        },
        {
            name: 'claim_rewards',
            discriminator: [4, 144, 132, 71, 116, 23, 151, 80],
            accounts: [
                { name: 'user', writable: true, signer: true },
                { name: 'pool', relations: ['user_stake'] },
                { name: 'user_token_account', writable: true },
                { name: 'reward_token_vault', writable: true, relations: ['pool'] },
                {
                    name: 'user_stake', writable: true,
                    pda: { seeds: [{ kind: 'const', value: [117,115,101,114,95,115,116,97,107,101] }, { kind: 'account', path: 'pool' }, { kind: 'account', path: 'user' }] },
                },
                {
                    name: 'authority',
                    pda: { seeds: [{ kind: 'const', value: [112,111,111,108,95,97,117,116,104,111,114,105,116,121] }, { kind: 'account', path: 'pool' }] },
                },
                { name: 'token_program', address: 'TokenkegQfeZyiNwAJbNbGKPFXCWuBvf9Ss623VQ5DA' },
            ],
            args: [],
        },
        {
            name: 'update_collection',
            discriminator: [97, 70, 36, 49, 138, 12, 199, 239],
            accounts: [
                { name: 'pool', writable: true },
                { name: 'authority', signer: true, relations: ['pool'] },
            ],
            args: [{ name: 'nft_collection', type: 'pubkey' }],
        },
        {
            name: 'stake_with_ticket',
            discriminator: [40, 125, 35, 32, 53, 50, 64, 243],
            accounts: [
                { name: 'user', writable: true, signer: true },
                { name: 'pool', writable: true },
                { name: 'user_token_account', writable: true },
                { name: 'stake_token_vault', writable: true, relations: ['pool'] },
                { name: 'reward_token_vault', writable: true, relations: ['pool'] },
                {
                    name: 'user_stake', writable: true,
                    pda: { seeds: [{ kind: 'const', value: [117,115,101,114,95,115,116,97,107,101] }, { kind: 'account', path: 'pool' }, { kind: 'account', path: 'user' }] },
                },
                {
                    name: 'authority',
                    pda: { seeds: [{ kind: 'const', value: [112,111,111,108,95,97,117,116,104,111,114,105,116,121] }, { kind: 'account', path: 'pool' }] },
                },
                {
                    name: 'fee_vault', writable: true,
                    pda: { seeds: [{ kind: 'const', value: [102,101,101,95,118,97,117,108,116] }, { kind: 'account', path: 'pool' }] },
                },
                { name: 'rent', address: 'SysvarRent111111111111111111111111111111111' },
                { name: 'clock', address: 'SysvarC1ock11111111111111111111111111111111' },
                { name: 'token_program', address: 'TokenkegQfeZyiNwAJbNbGKPFXCWuBvf9Ss623VQ5DA' },
                { name: 'system_program', address: '11111111111111111111111111111111' },
            ],
            args: [
                { name: 'stake_amount', type: 'u64' },
                { name: 'ticket', type: { defined: { name: 'TicketProof' } } },
            ],
        },
        {
            name: 'stake_with_nft_ticket',
            discriminator: [113, 149, 249, 186, 222, 220, 63, 105],
            accounts: [
                { name: 'user', writable: true, signer: true },
                { name: 'pool', writable: true },
                { name: 'user_token_account', writable: true },
                { name: 'stake_token_vault', writable: true, relations: ['pool'] },
                { name: 'reward_token_vault', writable: true, relations: ['pool'] },
                {
                    name: 'user_stake', writable: true,
                    pda: { seeds: [{ kind: 'const', value: [117,115,101,114,95,115,116,97,107,101] }, { kind: 'account', path: 'pool' }, { kind: 'account', path: 'user' }] },
                },
                {
                    name: 'authority',
                    pda: { seeds: [{ kind: 'const', value: [112,111,111,108,95,97,117,116,104,111,114,105,116,121] }, { kind: 'account', path: 'pool' }] },
                },
                {
                    name: 'fee_vault', writable: true,
                    pda: { seeds: [{ kind: 'const', value: [102,101,101,95,118,97,117,108,116] }, { kind: 'account', path: 'pool' }] },
                },
                { name: 'rent', address: 'SysvarRent111111111111111111111111111111111' },
                { name: 'clock', address: 'SysvarC1ock11111111111111111111111111111111' },
                { name: 'token_program', address: 'TokenkegQfeZyiNwAJbNbGKPFXCWuBvf9Ss623VQ5DA' },
                { name: 'system_program', address: '11111111111111111111111111111111' },
            ],
            args: [{ name: 'stake_amount', type: 'u64' }],
        },
        {
            name: 'unstake_with_ticket',
            discriminator: [30, 213, 26, 95, 152, 209, 61, 6],
            accounts: [
                { name: 'user', writable: true, signer: true },
                { name: 'pool', writable: true, relations: ['user_stake'] },
                { name: 'user_token_account', writable: true },
                { name: 'stake_token_vault', writable: true, relations: ['pool'] },
                { name: 'reward_token_vault', writable: true, relations: ['pool'] },
                {
                    name: 'user_stake', writable: true,
                    pda: { seeds: [{ kind: 'const', value: [117,115,101,114,95,115,116,97,107,101] }, { kind: 'account', path: 'pool' }, { kind: 'account', path: 'user' }] },
                },
                {
                    name: 'authority',
                    pda: { seeds: [{ kind: 'const', value: [112,111,111,108,95,97,117,116,104,111,114,105,116,121] }, { kind: 'account', path: 'pool' }] },
                },
                { name: 'token_program', address: 'TokenkegQfeZyiNwAJbNbGKPFXCWuBvf9Ss623VQ5DA' },
            ],
            args: [
                { name: 'amount', type: 'u64' },
                { name: 'ticket', type: { defined: { name: 'TicketProof' } } },
            ],
        },
        {
            name: 'unstake_with_nft_ticket',
            discriminator: [155, 114, 167, 96, 204, 173, 59, 242],
            accounts: [
                { name: 'user', writable: true, signer: true },
                { name: 'pool', writable: true, relations: ['user_stake'] },
                { name: 'user_token_account', writable: true },
                { name: 'stake_token_vault', writable: true, relations: ['pool'] },
                { name: 'reward_token_vault', writable: true, relations: ['pool'] },
                {
                    name: 'user_stake', writable: true,
                    pda: { seeds: [{ kind: 'const', value: [117,115,101,114,95,115,116,97,107,101] }, { kind: 'account', path: 'pool' }, { kind: 'account', path: 'user' }] },
                },
                {
                    name: 'authority',
                    pda: { seeds: [{ kind: 'const', value: [112,111,111,108,95,97,117,116,104,111,114,105,116,121] }, { kind: 'account', path: 'pool' }] },
                },
                { name: 'token_program', address: 'TokenkegQfeZyiNwAJbNbGKPFXCWuBvf9Ss623VQ5DA' },
            ],
            args: [{ name: 'amount', type: 'u64' }],
        },
    ],
    accounts: [
        { name: 'Pool',      discriminator: [241, 154, 109,  4, 17, 177, 109, 188] },
        { name: 'UserStake', discriminator: [102,  53, 163, 107,  9, 138,  87, 153] },
    ],
    errors: [
        { code: 6000, name: 'StakeCapExceeded',  msg: 'Stake cap exceeded' },
        { code: 6001, name: 'InsufficientStake', msg: 'Insufficient stake to unstake' },
        { code: 6002, name: 'InvalidNft',        msg: 'NFT account is not a valid Metaplex Core asset' },
        { code: 6003, name: 'NotNftOwner',       msg: 'NFT is not owned by the user' },
        { code: 6004, name: 'NotTzlaNft',        msg: 'NFT does not belong to the TZLA collection' },
        { code: 6005, name: 'Unauthorized',      msg: 'Only the pool authority can perform this action' },
        { code: 6006, name: 'RewardOverflow',     msg: 'Pending rewards exceed maximum — contact the pool authority' },
        { code: 6007, name: 'ArithmeticOverflow', msg: 'Arithmetic overflow or underflow' },
        { code: 6008, name: 'InvalidTicketTree',  msg: 'Merkle tree account is not a valid Bubblegum tree' },
        { code: 6009, name: 'NotGoldenTicket',    msg: 'NFT is not a verified member of the Golden Ticket collection' },
        { code: 6010, name: 'InvalidTicketProof', msg: 'Merkle proof is invalid or the ticket is not owned by the user' },
        { code: 6011, name: 'MissingTicketAccounts', msg: 'Missing the Golden Ticket account(s)' },
        { code: 6012, name: 'InsufficientFees',   msg: 'Not enough collected fees in the fee vault' },
    ],
    types: [
        {
            name: 'Pool',
            type: {
                kind: 'struct',
                fields: [
                    { name: 'authority',                      type: 'pubkey' },
                    { name: 'stake_token_mint',               type: 'pubkey' },
                    { name: 'stake_token_vault',              type: 'pubkey' },
                    { name: 'reward_token_vault',             type: 'pubkey' },
                    { name: 'nft_collection',                 type: 'pubkey' },
                    { name: 'legacy_acc_reward_per_share',    type: 'u128'   },
                    { name: 'total_staked',                   type: 'u128'   },
                    { name: 'legacy_last_reward_balance',     type: 'u64'    },
                    { name: 'stake_cap',                      type: 'u64'    },
                    { name: 'created_at_epoch',               type: 'u64'    },
                    { name: 'legacy_updated_at_epoch',        type: 'u64'    },
                    { name: 'created_at_ts',                  type: 'i64'    },
                    { name: 'legacy_updated_at_ts',           type: 'i64'    },
                    { name: 'bump',                           type: 'u8'     },
                ],
            },
        },
        {
            name: 'UserStake',
            type: {
                kind: 'struct',
                fields: [
                    { name: 'pool',               type: 'pubkey' },
                    { name: 'stake_amount',       type: 'u64'    },
                    { name: 'legacy_reward_debt', type: 'u128'   },
                    { name: 'last_stake_time',    type: 'i64'    },
                    { name: 'nft_tier',           type: 'u8'     },
                    { name: 'bump',               type: 'u8'     },
                ],
            },
        },
        {
            name: 'TicketCollection',
            type: {
                kind: 'struct',
                fields: [
                    { name: 'verified', type: 'bool'   },
                    { name: 'key',      type: 'pubkey' },
                ],
            },
        },
        {
            name: 'TicketCreator',
            type: {
                kind: 'struct',
                fields: [
                    { name: 'address',  type: 'pubkey' },
                    { name: 'verified', type: 'bool'   },
                    { name: 'share',    type: 'u8'     },
                ],
            },
        },
        {
            name: 'TicketUses',
            type: {
                kind: 'struct',
                fields: [
                    { name: 'use_method', type: 'u8'  },
                    { name: 'remaining',  type: 'u64' },
                    { name: 'total',      type: 'u64' },
                ],
            },
        },
        {
            name: 'TicketMetadata',
            type: {
                kind: 'struct',
                fields: [
                    { name: 'name',                    type: 'string' },
                    { name: 'symbol',                  type: 'string' },
                    { name: 'uri',                     type: 'string' },
                    { name: 'seller_fee_basis_points', type: 'u16'    },
                    { name: 'primary_sale_happened',   type: 'bool'   },
                    { name: 'is_mutable',              type: 'bool'   },
                    { name: 'edition_nonce',           type: { option: 'u8' } },
                    { name: 'token_standard',          type: { option: 'u8' } },
                    { name: 'collection',              type: { option: { defined: { name: 'TicketCollection' } } } },
                    { name: 'uses',                    type: { option: { defined: { name: 'TicketUses' } } } },
                    { name: 'token_program_version',   type: 'u8' },
                    { name: 'creators',                type: { vec: { defined: { name: 'TicketCreator' } } } },
                ],
            },
        },
        {
            name: 'TicketProof',
            type: {
                kind: 'struct',
                fields: [
                    { name: 'nonce',    type: 'u64'    },
                    { name: 'index',    type: 'u32'    },
                    { name: 'delegate', type: 'pubkey' },
                    { name: 'metadata', type: { defined: { name: 'TicketMetadata' } } },
                    { name: 'proof',    type: { vec: { array: ['u8', 32] } } },
                ],
            },
        },
    ],
} as const;

// ── Config ────────────────────────────────────────────────────────────────────

const CFG = (window as any).STAKING_CONFIG as {
    rpc: string;
    programId: string;
    stakeTokenMint: string;
    nftCollection: string;
    poolOwner: string;
};

// ── Constants ─────────────────────────────────────────────────────────────────

const PROGRAM_ID     = new PublicKey(CFG.programId);
const STAKE_MINT     = new PublicKey(CFG.stakeTokenMint);
const TOKEN_DECIMALS = 9;

// Golden Ticket collections — hardcoded in the on-chain program (lib.rs), so
// mirrored here. A holder of either gets the maximum rate (tier 3, 0.369%/day)
// via the dedicated golden instructions, which beat the Core-NFT tiers (≤2).
//   • GOLDEN_CNFT_COLLECTION    — Bubblegum compressed NFTs (stake_with_ticket)
//   • GOLDEN_CLASSIC_COLLECTION — classic Token Metadata NFTs (stake_with_nft_ticket)
const GOLDEN_CNFT_COLLECTION    = 'ETKq2GEUDYa5wm2PsNtxXRRn5iWBZyzWLXQ9WvKZptET';
const GOLDEN_CLASSIC_COLLECTION = 'FUSkrmKPfJ39fZwJYSgUKYBNcytkWPYsV6n8LB21NB5Q';
const TOKEN_METADATA_PROGRAM_ID = new PublicKey('metaqbxxUerdq28cj1RbAWkYQm3ybzjb6a8bt518x1s');

// ── Pre-computed PDAs ─────────────────────────────────────────────────────────

const [stakePool]     = PublicKey.findProgramAddressSync([Buffer.from('stake_pool'), new PublicKey(CFG.poolOwner).toBuffer()], PROGRAM_ID);
const [stakeVault]    = PublicKey.findProgramAddressSync([Buffer.from('stake_vault'), stakePool.toBuffer()], PROGRAM_ID);
const [rewardVault]   = PublicKey.findProgramAddressSync([Buffer.from('reward_vault'), stakePool.toBuffer()], PROGRAM_ID);
const [poolAuthority] = PublicKey.findProgramAddressSync([Buffer.from('pool_authority'), stakePool.toBuffer()], PROGRAM_ID);

// ── State ─────────────────────────────────────────────────────────────────────

const RPC_URL = CFG.rpc.startsWith('http')
    ? CFG.rpc
    : new URL(CFG.rpc, window.location.origin).toString();

const connection = new Connection(RPC_URL, 'confirmed');

const readonlyProvider = new AnchorProvider(
    connection,
    {
        publicKey: PublicKey.default,
        signTransaction: async (tx: any) => tx,
        signAllTransactions: async (txs: any[]) => txs,
    } as any,
    { commitment: 'confirmed' },
);
const readonlyProgram = new Program(IDL as any, readonlyProvider);

let provider:    AnchorProvider | null = null;
let program:     Program | null        = null;
let walletPubkey: PublicKey | null     = null;
let walletAdapter: Adapter | null      = null;
let coreNfts:    string[]              = [];  // TZLA Core NFT asset ids (tiers 1, 2, 4)
let goldenClassicMints: string[]      = [];  // classic Token Metadata golden ticket mints (tier 3)
let goldenCnftIds:      string[]      = [];  // compressed golden ticket asset ids (tier 3)
let _cachedStakeData: any              = null;

// True when the wallet holds at least one Golden Ticket of either collection.
function hasGoldenTicket(): boolean {
    return goldenClassicMints.length > 0 || goldenCnftIds.length > 0;
}
let _yieldTimer: ReturnType<typeof setInterval> | null = null;

// ── DAS RPC helper ────────────────────────────────────────────────────────────

async function rpcCall(method: string, params: any): Promise<any> {
    const resp = await fetch(RPC_URL, {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ jsonrpc: '2.0', id: `tzla-${method}`, method, params }),
    });
    const json = await resp.json();
    if (json?.error) throw new Error(json.error.message ?? `RPC error: ${method}`);
    return json?.result;
}

// ── NFT detection (Helius DAS) ────────────────────────────────────────────────
// Walks the wallet's assets once and sorts each into one of three buckets:
//   • TZLA Core NFTs           → tiers 1–2 via the plain stake/unstake path
//   • golden classic NFTs      → tier 3 via stake_with_nft_ticket
//   • golden compressed NFTs   → tier 3 via stake_with_ticket
// Golden tickets always win because they pay the maximum rate.

async function fetchOwnedAssets(owner: PublicKey): Promise<void> {
    const core: string[]    = [];
    const classic: string[] = [];
    const cnft: string[]    = [];
    const limit = 1000;
    let page = 1;

    while (true) {
        const result = await rpcCall('getAssetsByOwner', {
            ownerAddress: owner.toString(), page, limit,
        });
        const items: any[] = result?.items ?? [];

        for (const asset of items) {
            const collection = (asset.grouping ?? []).find(
                (g: any) => g.group_key === 'collection',
            )?.group_value;
            if (!collection) continue;

            if (collection === GOLDEN_CLASSIC_COLLECTION)      classic.push(asset.id);
            else if (collection === GOLDEN_CNFT_COLLECTION)     cnft.push(asset.id);
            else if (collection === CFG.nftCollection)          core.push(asset.id);
        }

        if (items.length < limit) break;
        page++;
    }

    coreNfts           = core;
    goldenClassicMints = classic;
    goldenCnftIds      = cnft;
}

function updateNftBoostUI(loading = false): void {
    const connected = walletPubkey !== null;
    const golden    = hasGoldenTicket();
    const coreCount = coreNfts.length;

    ['nftBoostStatus', 'unstakeBoostStatus'].forEach(id => {
        const statusEl = document.getElementById(id);
        if (!statusEl) return;

        if (!connected) {
            statusEl.style.display = 'none';
            return;
        }
        statusEl.style.display = '';

        if (loading) {
            statusEl.className   = 'nft-status nft-none';
            statusEl.textContent = 'Checking for TZLA NFTs & Golden Tickets…';
        } else if (golden) {
            statusEl.className   = 'nft-status nft-found';
            statusEl.textContent = '★ Golden Ticket detected — maximum rate (0.369%/day)';
        } else if (coreCount >= NFT10_THRESHOLD) {
            statusEl.className   = 'nft-status nft-found';
            statusEl.textContent = `⚡ ${coreCount} TZLA NFTs detected — whale boost (0.330%/day)`;
        } else if (coreCount >= 2) {
            statusEl.className   = 'nft-status nft-found';
            statusEl.textContent = `⚡ ${coreCount} TZLA NFTs detected — 2× boost (0.222%/day)`;
        } else if (coreCount === 1) {
            statusEl.className   = 'nft-status nft-found';
            statusEl.textContent = '⚡ 1 TZLA NFT detected — boost active (0.111%/day)';
        } else {
            statusEl.className   = 'nft-status nft-none';
            statusEl.textContent = 'No TZLA NFTs in wallet — base rate (0.069%/day)';
        }
    });

    // "TZLA NFTs" count shows Core NFTs; a golden-only holder reads as a ticket.
    setText('userNftCount', connected ? (golden && coreCount === 0 ? '★ Golden' : coreCount.toString()) : '–');
}

async function refreshNfts(): Promise<void> {
    if (!walletPubkey) {
        coreNfts = []; goldenClassicMints = []; goldenCnftIds = [];
        updateNftBoostUI();
        return;
    }
    updateNftBoostUI(true);
    try {
        await fetchOwnedAssets(walletPubkey);
    } catch (err) {
        console.error('Failed to fetch owned assets:', err);
        coreNfts = []; goldenClassicMints = []; goldenCnftIds = [];
    }
    updateNftBoostUI();
}

// ── Golden Ticket account / proof builders ────────────────────────────────────

/// Accounts for the classic-NFT golden path: the holder's associated token
/// account and the mint's Token Metadata PDA, in the order the program reads
/// them ([token_account, metadata]).
async function goldenClassicAccounts(mintStr: string): Promise<any[]> {
    const mint         = new PublicKey(mintStr);
    const tokenAccount = await getAssociatedTokenAddress(mint, walletPubkey!);
    const [metadata]   = PublicKey.findProgramAddressSync(
        [Buffer.from('metadata'), TOKEN_METADATA_PROGRAM_ID.toBuffer(), mint.toBuffer()],
        TOKEN_METADATA_PROGRAM_ID,
    );
    return [
        { pubkey: tokenAccount, isWritable: false, isSigner: false },
        { pubkey: metadata,     isWritable: false, isSigner: false },
    ];
}

/// Reconstructs the Bubblegum leaf (TicketProof) for a compressed golden ticket
/// from DAS getAsset + getAssetProof. The on-chain program re-hashes this
/// metadata and walks the proof to the live root, so every field must mirror
/// what was committed at mint time.
async function buildTicketProof(assetId: string): Promise<{ ticket: any; tree: PublicKey }> {
    const [asset, proofData] = await Promise.all([
        rpcCall('getAsset',      { id: assetId }),
        rpcCall('getAssetProof', { id: assetId }),
    ]);

    const tree  = new PublicKey(proofData.tree_id);
    // 32-byte base58 node hashes → byte arrays (PublicKey is a convenient codec).
    const proof = (proofData.proof as string[]).map(p => Array.from(new PublicKey(p).toBytes()));

    const comp     = asset.compression ?? {};
    const owner    = asset.ownership ?? {};
    const leafId   = comp.leaf_id ?? 0;
    const coll     = (asset.grouping ?? []).find((g: any) => g.group_key === 'collection');

    const metadata = {
        name:                  asset.content?.metadata?.name   ?? '',
        symbol:                asset.content?.metadata?.symbol ?? '',
        uri:                   asset.content?.json_uri          ?? '',
        sellerFeeBasisPoints:  asset.royalty?.basis_points      ?? 0,
        primarySaleHappened:   asset.royalty?.primary_sale_happened ?? false,
        isMutable:             asset.mutable                    ?? false,
        editionNonce:          asset.supply?.edition_nonce ?? null,
        tokenStandard:         0,                               // NonFungible
        collection:            coll ? { verified: true, key: new PublicKey(coll.group_value) } : null,
        uses:                  null,
        tokenProgramVersion:   0,                               // Original
        creators:              (asset.creators ?? []).map((c: any) => ({
            address:  new PublicKey(c.address),
            verified: !!c.verified,
            share:    c.share ?? 0,
        })),
    };

    const ticket = {
        nonce:    new BN(leafId),
        index:    leafId,
        delegate: new PublicKey(owner.delegate ?? owner.owner ?? walletPubkey!),
        metadata,
        proof,
    };

    return { ticket, tree };
}

// ── Math helpers ──────────────────────────────────────────────────────────────

function toBaseUnits(tokens: number): BN {
    return new BN(Math.round(tokens * 10 ** TOKEN_DECIMALS));
}

// Mirrors onchain constants from lib.rs — must stay in sync.
const BASE_DAILY_RATE_NUM   = 69n;    // 0.069 %/day  (0 NFTs)
const NFT1_DAILY_RATE_NUM   = 111n;   // 0.111 %/day  (1 NFT)
const NFT2_DAILY_RATE_NUM   = 222n;   // 0.222 %/day  (2–9 NFTs)
const NFT10_DAILY_RATE_NUM  = 330n;   // 0.330 %/day  (10+ NFTs, tier 4)
const GOLDEN_DAILY_RATE_NUM = 369n;   // 0.369 %/day  (Golden Ticket, tier 3)
const DAILY_RATE_DENOM      = 100_000n;
const SECONDS_PER_DAY_BI    = 86_400n;
// Tier values are identifiers, not rate-ordered: 3 = Golden (max), 4 = 10+ NFTs.
const GOLDEN_TICKET_TIER    = 3;
const NFT10_TIER            = 4;
const NFT10_THRESHOLD       = 10;
// The program counts at most this many Core NFT accounts per stake/unstake.
const MAX_CORE_NFT_ACCOUNTS = 10;

function dailyRateForTier(nftTier: number): bigint {
    if (nftTier === GOLDEN_TICKET_TIER) return GOLDEN_DAILY_RATE_NUM;
    if (nftTier >= NFT10_TIER) return NFT10_DAILY_RATE_NUM;
    if (nftTier >= 2) return NFT2_DAILY_RATE_NUM;
    if (nftTier === 1) return NFT1_DAILY_RATE_NUM;
    return BASE_DAILY_RATE_NUM;
}

function calcPendingRewards(userStake: any): bigint {
    if (!userStake) return 0n;
    const stakeAmount = BigInt(userStake.stakeAmount.toString());
    if (stakeAmount === 0n) return 0n;
    const lastStakeTs = BigInt(userStake.lastStakeTime.toNumber());
    const nowTs       = BigInt(Math.floor(Date.now() / 1000));
    const elapsed     = nowTs > lastStakeTs ? nowTs - lastStakeTs : 0n;
    const rate        = dailyRateForTier(userStake.nftTier);
    return stakeAmount * rate * elapsed / (DAILY_RATE_DENOM * SECONDS_PER_DAY_BI);
}

function fmtDPY(nftTier: number | null): string {
    if (nftTier === null) return '–';
    if (nftTier === GOLDEN_TICKET_TIER) return '0.369%/day';
    if (nftTier >= NFT10_TIER) return '0.330%/day';
    if (nftTier >= 2) return '0.222%/day';
    if (nftTier === 1) return '0.111%/day';
    return '0.069%/day';
}

// Handles BN (u64 or u128 Anchor values) safely via BigInt to avoid toNumber() precision loss.
// maxFrac caps decimal places shown (default: full precision).
function fmtTokens(bn: BN | bigint | number, maxFrac = TOKEN_DECIMALS): string {
    let raw: bigint;
    if (bn instanceof BN)            raw = BigInt(bn.toString());
    else if (typeof bn === 'bigint') raw = bn;
    else                             raw = BigInt(Math.round(bn));
    const divisor = BigInt(10 ** TOKEN_DECIMALS);
    const whole   = raw / divisor;
    const fracRaw = raw % divisor;
    if (fracRaw === 0n) return Number(whole).toLocaleString();
    const fracStr = fracRaw.toString().padStart(TOKEN_DECIMALS, '0').slice(0, maxFrac).replace(/0+$/, '');
    return fracStr ? `${Number(whole).toLocaleString()}.${fracStr}` : Number(whole).toLocaleString();
}

function shortKey(pk: PublicKey | string): string {
    const s = pk.toString();
    return `${s.slice(0, 4)}…${s.slice(-4)}`;
}

// ── DOM helpers ───────────────────────────────────────────────────────────────

function el<T extends HTMLElement>(id: string): T {
    return document.getElementById(id) as T;
}

function setText(id: string, val: string): void {
    const e = document.getElementById(id);
    if (e) e.textContent = val;
}

function setLoading(on: boolean): void {
    const overlay = el('loadingOverlay');
    if (overlay) overlay.style.display = on ? 'flex' : 'none';
}

function showToast(msg: string, type: 'success' | 'error' | 'info' = 'info'): void {
    const toast    = el('toast');
    const toastMsg = el('toastMsg');
    if (!toast || !toastMsg) return;

    const cls: Record<string, string> = {
        success: 'toast toast-success',
        error:   'toast toast-error',
        info:    'toast toast-info',
    };

    toast.className    = cls[type];
    toastMsg.textContent = msg;
    setTimeout(() => { toast.className = 'toast toast-hidden'; }, 6000);
}

// ── Wallet UI ─────────────────────────────────────────────────────────────────

function updateConnectButton(): void {
    const btn          = el('connectBtn');
    const walletSection = el('walletSection');
    const walletAddr   = el('walletAddr');

    if (walletPubkey) {
        btn.textContent = 'Disconnect';
        btn.className   = 'connect-btn connected';
        if (walletSection) walletSection.classList.remove('hidden');
        if (walletAddr)    walletAddr.textContent = walletPubkey.toString();
    } else {
        btn.textContent = 'Connect Wallet';
        btn.className   = 'connect-btn';
        if (walletSection) walletSection.classList.add('hidden');
        clearUserStats();
    }
}

// ── Pool UI ───────────────────────────────────────────────────────────────────

function updatePoolUI(pool: any): void {
    if (!pool) {
        setText('poolTotalStaked',    '–');
        setText('poolStakeCap',       '–');
        setText('poolNftCollection',  '–');
        setText('poolMint',           '–');
        setText('poolStatus',         'Not initialised');
        return;
    }

    const totalStaked = BigInt(pool.totalStaked.toString());

    setText('poolTotalStaked',    fmtTokens(pool.totalStaked, 2));
    setText('poolStakeCap',       fmtTokens(pool.stakeCap));
    setText('poolNftCollection',  shortKey(pool.nftCollection));
    setText('poolMint',           shortKey(pool.stakeTokenMint));
    setText('poolStatus',         'Active');

    const bar = el<HTMLDivElement>('capacityBar');
    if (bar) {
        const total = Number(totalStaked);
        const cap   = pool.stakeCap.toNumber();
        const pct   = cap > 0 ? Math.min((total / cap) * 100, 100) : 0;
        bar.style.width = `${pct}%`;
    }
}

// ── Predicted yield helpers ───────────────────────────────────────────────────

function fmtElapsed(seconds: number): string {
    if (seconds < 60) return `${seconds}s`;
    if (seconds < 3600) return `${Math.floor(seconds / 60)}m ${seconds % 60}s`;
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    if (seconds < 86400) return `${h}h ${m}m`;
    const d = Math.floor(seconds / 86400);
    const hh = Math.floor((seconds % 86400) / 3600);
    return `${d}d ${hh}h`;
}

function updateUnstakePanelYield(): void {
    const box = document.getElementById('predictedYieldBox');
    if (!box) return;

    const stakeData = _cachedStakeData;
    if (!stakeData || BigInt(stakeData.stakeAmount.toString()) === 0n) {
        box.style.display = 'none';
        stopYieldTimer();
        return;
    }

    box.style.display = '';

    const pending     = calcPendingRewards(stakeData);
    const lastStakeTs = stakeData.lastStakeTime.toNumber();
    const nowTs       = Math.floor(Date.now() / 1000);
    const elapsed     = Math.max(0, nowTs - lastStakeTs);

    const stakedDate = new Date(lastStakeTs * 1000).toLocaleDateString('en-GB', {
        day: 'numeric', month: 'short', year: 'numeric',
    });

    setText('pyStakedSince', stakedDate);
    setText('pyElapsed',     fmtElapsed(elapsed));
    setText('pyYield',       `${fmtTokens(pending)} TZLA`);
}

function startYieldTimer(): void {
    stopYieldTimer();
    _yieldTimer = setInterval(updateUnstakePanelYield, 5000);
}

function stopYieldTimer(): void {
    if (_yieldTimer !== null) { clearInterval(_yieldTimer); _yieldTimer = null; }
}

// ── Backend tracking (fire-and-forget) ───────────────────────────────────────

function postStakeEvent(wallet: string, amountRaw: string, nftTier: number, stakeTx: string): void {
    fetch('/api/staking/record-stake', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrf() },
        body:    JSON.stringify({ wallet, amount_raw: amountRaw, nft_tier: nftTier, stake_tx: stakeTx }),
    }).catch(() => {});
}

function postUnstakeEvent(wallet: string, unstakeTx: string): void {
    fetch('/api/staking/record-unstake', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrf() },
        body:    JSON.stringify({ wallet, unstake_tx: unstakeTx }),
    }).catch(() => {});
}

// The earnings card reads the verified DB record, which the queue writes a few
// seconds after the transaction confirms — refresh it once soon after and once
// more as a catch-up so the card tracks the new on-chain position.
function scheduleEarningsCardRefresh(): void {
    for (const delay of [8_000, 25_000]) {
        setTimeout(() => { refreshEarningsSummaryCard(true).catch(() => {}); }, delay);
    }
}

function getCsrf(): string {
    return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
}

// ── User UI ───────────────────────────────────────────────────────────────────

function updateUserUI(stakeData: any): void {
    _cachedStakeData = stakeData;

    if (stakeData) {
        const pending = calcPendingRewards(stakeData);
        const nftTier = stakeData.nftTier as number;

        setText('userStaked',         fmtTokens(stakeData.stakeAmount));
        setText('userPendingRewards', `${fmtTokens(pending)} TZLA`);
        setText('userDPY',            fmtDPY(nftTier));
    } else {
        setText('userStaked',         '0');
        setText('userPendingRewards', '–');
        setText('userDPY',            '–');
    }

    updateUnstakePanelYield();
}

function clearUserStats(): void {
    setText('userStaked',        '–');
    setText('userPendingRewards','–');
    setText('userDPY',           '–');
    setText('userNftCount',      '–');
}

// ── Data fetching ─────────────────────────────────────────────────────────────

async function refreshPoolStats(): Promise<void> {
    try {
        const pool = await readonlyProgram.account['pool'].fetch(stakePool);
        updatePoolUI(pool);
    } catch {
        updatePoolUI(null);
    }
}

// "TZLA distributed" (and its USD value) come from the Redis-backed backend
// oracle rather than the chain, so every visitor is served from cache and
// Jupiter/Helius are hit at most once per refresh interval regardless of traffic.
async function refreshDistributed(): Promise<void> {
    try {
        const resp = await fetch('/api/staking/pool-stats', { headers: { Accept: 'application/json' } });
        if (!resp.ok) throw new Error(`pool-stats ${resp.status}`);
        const s = await resp.json();

        const tokens = Number(s.distributed_tokens ?? 0);
        setText('poolDistributed', tokens.toLocaleString(undefined, { maximumFractionDigits: 0 }));

        const usd = s.distributed_usd;
        setText(
            'poolDistributedUsd',
            usd != null
                ? `$${Number(usd).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
                : '',
        );
    } catch (e) {
        console.error('[tzla] pool-stats failed:', e);
    }
}

async function refreshUserStats(): Promise<void> {
    if (!walletPubkey || !program) return;

    try {
        const [userStakePda] = PublicKey.findProgramAddressSync(
            [Buffer.from('user_stake'), stakePool.toBuffer(), walletPubkey.toBuffer()],
            PROGRAM_ID,
        );

        let stakeData: any = null;
        try {
            stakeData = await program.account['userStake'].fetch(userStakePda);
        } catch { /* no stake yet */ }

        updateUserUI(stakeData);
    } catch (err) {
        console.error('Failed to refresh user stats:', err);
    }
}

async function refreshAll(): Promise<void> {
    await Promise.all([refreshPoolStats(), refreshDistributed(), refreshUserStats(), refreshNfts()]);
}

// ── Wallet connection ─────────────────────────────────────────────────────────

function bindAdapter(adapter: Adapter): void {
    walletAdapter?.off('disconnect', onAdapterDisconnect);
    walletAdapter = adapter;
    adapter.on('disconnect', onAdapterDisconnect);
}

function onAdapterDisconnect(): void {
    walletPubkey = null;
    provider = null;
    program = null;
    walletAdapter = null;
    coreNfts = [];
    goldenClassicMints = [];
    goldenCnftIds = [];
    _cachedStakeData = null;
    stopYieldTimer();
    const box = document.getElementById('predictedYieldBox');
    if (box) box.style.display = 'none';
    updateNftBoostUI();
    updateConnectButton();
    clearEarningsSummaryCard();
}

function fillWalletModalList(): void {
    const list = document.getElementById('walletModalList');
    const hint = document.getElementById('walletModalHint');
    if (!list) return;

    const adapters = listWalletAdapters();
    list.replaceChildren();

    if (hint) {
        const message = mobileWalletHint();
        if (message) {
            hint.textContent = message;
            hint.style.display = 'block';
        } else {
            hint.style.display = 'none';
        }
    }

    if (needsWalletAppBrowser()) {
        for (const app of walletAppLinks()) {
            const row = document.createElement('a');
            row.className = 'wallet-row wallet-row-primary';
            row.href = app.url;
            row.innerHTML = `
                <img class="wallet-row-icon" alt="" src="${app.icon}" />
                <span class="wallet-row-name">${app.label}</span>
                <span class="wallet-row-state">Tap to open</span>
            `;
            list.appendChild(row);
        }
    }

    if (adapters.length === 0 && !needsWalletAppBrowser()) {
        const empty = document.createElement('p');
        empty.className = 'wallet-empty';
        empty.textContent = 'No Solana wallet found. Install Phantom, Solflare, or Backpack, then refresh.';
        list.appendChild(empty);
        return;
    }

    for (const adapter of adapters) {
        const ready = canConnect(adapter);
        const row = document.createElement('button');
        row.type = 'button';
        row.className = 'wallet-row';

        if (adapter.icon) {
            const icon = document.createElement('img');
            icon.className = 'wallet-row-icon';
            icon.alt = '';
            icon.src = adapter.icon;
            row.appendChild(icon);
        }

        const name = document.createElement('span');
        name.className = 'wallet-row-name';
        name.textContent = adapter.name;
        row.appendChild(name);

        const state = document.createElement('span');
        state.className = 'wallet-row-state';
        state.textContent = walletStatusLabel(adapter);
        row.appendChild(state);

        row.addEventListener('click', () => {
            if (isRedirectableAdapter(adapter)) {
                redirectAdapterToApp(adapter);
                return;
            }
            if (ready) {
                void connectAdapter(adapter);
                return;
            }
            const url = installUrl(adapter.name);
            if (url) window.open(url, '_blank', 'noopener,noreferrer');
            else showToast(`Install ${adapter.name}, then refresh this page.`, 'info');
        });
        list.appendChild(row);
    }
}

function openWalletModal(): void {
    const modal = document.getElementById('walletModal');
    const list = document.getElementById('walletModalList');
    if (!modal || !list) {
        showToast('Wallet picker is missing. Refresh the page.', 'error');
        return;
    }

    fillWalletModalList();
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('wallet-modal-open');

    void Promise.all([
        waitForInjectedWallet(isIos() ? 5000 : isMobileDevice() ? 2500 : 500),
        waitForWallets(800),
    ]).then(() => {
        if (modal.classList.contains('open')) fillWalletModalList();
    });
}

function closeWalletModal(): void {
    const modal = document.getElementById('walletModal');
    if (!modal) return;
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('wallet-modal-open');
}

async function connectAdapter(adapter: Adapter): Promise<void> {
    closeWalletModal();
    setLoading(true);
    try {
        bindAdapter(adapter);
        await adapter.connect();
        const publicKey = adapter.publicKey;
        if (!publicKey) throw new Error(`${adapter.name} did not return a public key.`);
        rememberWallet(adapter.name);
        await handleWalletConnected(publicKey);
    } catch (e: any) {
        adapter.off('disconnect', onAdapterDisconnect);
        if (walletAdapter === adapter) walletAdapter = null;
        const msg = String(e?.message ?? e);
        if (msg.toLowerCase().includes('reject') || msg.toLowerCase().includes('cancel')) {
            showToast('Connection cancelled.', 'info');
        } else {
            showToast(`Connection failed: ${msg}`, 'error');
        }
    } finally {
        setLoading(false);
    }
}

async function handleWalletConnected(publicKey: PublicKey): Promise<void> {
    if (!walletAdapter) return;

    walletPubkey = publicKey;
    provider = new AnchorProvider(connection, walletAdapter as any, {
        commitment:          'confirmed',
        preflightCommitment: 'confirmed',
    });
    program = new Program(IDL as any, provider);

    updateConnectButton();
    refreshEarningsSummaryCard();
    await Promise.all([refreshUserStats(), refreshNfts()]);
}

function connectOrDisconnect(): void {
    if (walletPubkey) {
        void disconnectWallet();
        return;
    }

    if (needsWalletAppBrowser() && !hasInjectedWallet()) {
        openWalletModal();
        return;
    }

    openWalletModal();
}

async function disconnectWallet(): Promise<void> {
    const adapter = walletAdapter;
    forgetWallet();
    await adapter?.disconnect().catch(() => {});
    onAdapterDisconnect();
}

// ── HTTP-only transaction confirmation ───────────────────────────────────────
// Anchor's .rpc() uses sendAndConfirmRawTransaction internally, which opens a
// WebSocket subscription alongside HTTP polling. Our proxy is HTTP-only, so we
// skip the WebSocket entirely and poll getSignatureStatuses directly.

async function sendAndConfirmNoWs(tx: any): Promise<string> {
    const { blockhash, lastValidBlockHeight } = await connection.getLatestBlockhash('confirmed');
    tx.recentBlockhash = blockhash;
    tx.feePayer        = walletPubkey!;

    if (!walletAdapter?.signTransaction) {
        throw new Error('Connected wallet cannot sign transactions.');
    }
    const signed  = await walletAdapter.signTransaction(tx);
    const sig     = await connection.sendRawTransaction(signed.serialize(), {
        skipPreflight:        false,
        preflightCommitment:  'confirmed',
    });

    const deadline = Date.now() + 60_000;
    while (Date.now() < deadline) {
        const { value } = await connection.getSignatureStatus(sig, { searchTransactionHistory: false });
        if (value?.err) throw Object.assign(new Error(JSON.stringify(value.err)), { transactionLogs: [] });
        if (value?.confirmationStatus === 'confirmed' || value?.confirmationStatus === 'finalized') {
            return sig;
        }
        await new Promise(r => setTimeout(r, 1500));
    }

    throw new Error(`Transaction was not confirmed in 60.00 seconds. It is unknown if it succeeded or failed. Check signature: ${sig}`);
}

// ── Token account recovery ────────────────────────────────────────────────────
// Some users close their TZLA token account in Phantom after staking their full
// balance. The account is the transfer destination on unstake (and source on
// stake), so without it the transaction fails. If it's missing, recreate it in
// the same transaction. The idempotent variant no-ops if the account reappears
// between this check and the send, so it can never cause a failure itself.

async function recreateUserAtaIfMissing(userAta: PublicKey): Promise<Array<TransactionInstruction>> {
    try {
        await getAccount(connection, userAta);
        return [];
    } catch {
        return [
            createAssociatedTokenAccountIdempotentInstruction(walletPubkey!, userAta, walletPubkey!, STAKE_MINT),
        ];
    }
}

// ── Stake ─────────────────────────────────────────────────────────────────────

async function stake(amount: number, nftAssets: Array<string> = []): Promise<void> {
    if (!provider || !walletPubkey || !program) {
        showToast('Connect your wallet first.', 'error');
        return;
    }
    if (!amount || amount <= 0) {
        showToast('Enter a valid amount.', 'error');
        return;
    }

    const amountBN = toBaseUnits(amount);
    const userAta  = await getAssociatedTokenAddress(STAKE_MINT, walletPubkey);
    const [userStakePda] = PublicKey.findProgramAddressSync(
        [Buffer.from('user_stake'), stakePool.toBuffer(), walletPubkey.toBuffer()],
        PROGRAM_ID,
    );

    setLoading(true);

    try {
        const preInstructions: Array<TransactionInstruction> = await recreateUserAtaIfMissing(userAta);

        // Create the user_stake account if this is the user's first stake.
        // Separated from stake() to remove init_if_needed (which disables Anchor's
        // re-initialisation guard and is a known security risk).
        try {
            await program.account['userStake'].fetch(userStakePda);
        } catch {
            const createStakeAccountIx = await (program.methods as any)
                .createUserStake()
                .accounts({ user: walletPubkey, pool: stakePool, userStake: userStakePda })
                .instruction();
            preInstructions.push(createStakeAccountIx);
        }

        // Shared accounts for every stake variant. fee_vault and the sysvars are
        // auto-resolved by Anchor from the IDL PDA/address definitions.
        const baseAccounts = {
            user:             walletPubkey,
            pool:             stakePool,
            userTokenAccount: userAta,
            stakeTokenVault:  stakeVault,
            rewardTokenVault: rewardVault,
            userStake:        userStakePda,
            authority:        poolAuthority,
        };

        // Route to the highest rate the wallet qualifies for. Golden Tickets
        // (tier 3, 0.369%/day) beat any number of Core NFTs, so they take
        // priority; the classic-NFT path is preferred over the compressed one as
        // it needs no Merkle proof.
        let stakeTx: any;
        let nftTierForRecord = 0;

        if (goldenClassicMints.length > 0) {
            stakeTx = await (program.methods as any)
                .stakeWithNftTicket(amountBN)
                .accounts(baseAccounts)
                .preInstructions(preInstructions)
                .remainingAccounts(await goldenClassicAccounts(goldenClassicMints[0]))
                .transaction();
            nftTierForRecord = 3;
        } else if (goldenCnftIds.length > 0) {
            const { ticket, tree } = await buildTicketProof(goldenCnftIds[0]);
            stakeTx = await (program.methods as any)
                .stakeWithTicket(amountBN, ticket)
                .accounts(baseAccounts)
                .preInstructions(preInstructions)
                .remainingAccounts([{ pubkey: tree, isWritable: false, isSigner: false }])
                .transaction();
            nftTierForRecord = 3;
        } else {
            // Pass up to 10 Core NFTs — the program counts valid ones for tier 0/1/2/4.
            const remainingAccounts = nftAssets.slice(0, MAX_CORE_NFT_ACCOUNTS).map(address => ({
                pubkey: new PublicKey(address), isWritable: false, isSigner: false,
            }));
            stakeTx = await (program.methods as any)
                .stake(amountBN)
                .accounts(baseAccounts)
                .preInstructions(preInstructions)
                .remainingAccounts(remainingAccounts)
                .transaction();
            nftTierForRecord = nftAssets.length >= NFT10_THRESHOLD ? NFT10_TIER
                             : nftAssets.length >= 2 ? 2
                             : nftAssets.length === 1 ? 1 : 0;
        }

        const sig = await sendAndConfirmNoWs(stakeTx);

        showToast(`Staked ${amount} TZLA successfully!`, 'success');
        await refreshAll();
        // Record to DB for yield tracking (fire-and-forget)
        postStakeEvent(walletPubkey!.toString(), amountBN.toString(), nftTierForRecord, sig);
        scheduleEarningsCardRefresh();
    } catch (e: any) {
        if (alreadyProcessed(e)) {
            showToast(`Staked ${amount} TZLA successfully!`, 'success');
            await refreshAll();
        } else {
            showToast(parseAnchorError(e), 'error');
        }
    } finally {
        setLoading(false);
    }
}

// ── Claim rewards ─────────────────────────────────────────────────────────────

async function claimRewards(): Promise<void> {
    if (!provider || !walletPubkey || !program) {
        showToast('Connect your wallet first.', 'error');
        return;
    }

    const userAta = await getAssociatedTokenAddress(STAKE_MINT, walletPubkey);
    const [userStakePda] = PublicKey.findProgramAddressSync(
        [Buffer.from('user_stake'), stakePool.toBuffer(), walletPubkey.toBuffer()],
        PROGRAM_ID,
    );

    setLoading(true);

    try {
        const preInstructions = await recreateUserAtaIfMissing(userAta);
        const tx = await (program.methods as any)
            .claimRewards()
            .accounts({
                user:             walletPubkey,
                pool:             stakePool,
                userTokenAccount: userAta,
                rewardTokenVault: rewardVault,
                userStake:        userStakePda,
                authority:        poolAuthority,
                tokenProgram:     TOKEN_PROGRAM_ID,
            })
            .preInstructions(preInstructions)
            .transaction();

        const sig = await sendAndConfirmNoWs(tx);
        showToast('Rewards claimed!', 'success');
        console.info('[tzla] claim_rewards', sig);
        scheduleEarningsCardRefresh();
        await refreshAll();
    } catch (e: any) {
        if (alreadyProcessed(e)) {
            showToast('Rewards claimed!', 'success');
            await refreshAll();
        } else {
            showToast(parseAnchorError(e), 'error');
        }
    } finally {
        setLoading(false);
    }
}

// ── Unstake ───────────────────────────────────────────────────────────────────

async function unstake(amount: number, nftAssets: Array<string> = []): Promise<void> {
    if (!provider || !walletPubkey || !program) {
        showToast('Connect your wallet first.', 'error');
        return;
    }
    if (!amount || amount <= 0) {
        showToast('Enter a valid amount.', 'error');
        return;
    }

    const amountBN = toBaseUnits(amount);
    const userAta  = await getAssociatedTokenAddress(STAKE_MINT, walletPubkey);
    const [userStakePda] = PublicKey.findProgramAddressSync(
        [Buffer.from('user_stake'), stakePool.toBuffer(), walletPubkey.toBuffer()],
        PROGRAM_ID,
    );

    setLoading(true);

    try {
        // The unstake transfer lands in this ATA — recreate it first if the
        // user closed it, otherwise the withdrawal is impossible.
        const preInstructions = await recreateUserAtaIfMissing(userAta);

        const baseAccounts = {
            user:             walletPubkey,
            pool:             stakePool,
            userTokenAccount: userAta,
            stakeTokenVault:  stakeVault,
            rewardTokenVault: rewardVault,
            userStake:        userStakePda,
            authority:        poolAuthority,
            tokenProgram:     TOKEN_PROGRAM_ID,
        };

        // Match the stake routing so the stored tier is preserved/updated for
        // future periods. Rewards for the elapsed period are always paid at the
        // tier recorded at the last interaction, regardless of which path runs.
        let unstakeTx: any;

        if (goldenClassicMints.length > 0) {
            unstakeTx = await (program.methods as any)
                .unstakeWithNftTicket(amountBN)
                .accounts(baseAccounts)
                .preInstructions(preInstructions)
                .remainingAccounts(await goldenClassicAccounts(goldenClassicMints[0]))
                .transaction();
        } else if (goldenCnftIds.length > 0) {
            const { ticket, tree } = await buildTicketProof(goldenCnftIds[0]);
            unstakeTx = await (program.methods as any)
                .unstakeWithTicket(amountBN, ticket)
                .accounts(baseAccounts)
                .preInstructions(preInstructions)
                .remainingAccounts([{ pubkey: tree, isWritable: false, isSigner: false }])
                .transaction();
        } else {
            // Pass up to 10 Core NFTs so the program can refresh the stored tier.
            const remainingAccounts = nftAssets.slice(0, MAX_CORE_NFT_ACCOUNTS).map(address => ({
                pubkey: new PublicKey(address), isWritable: false, isSigner: false,
            }));
            unstakeTx = await (program.methods as any)
                .unstake(amountBN)
                .accounts(baseAccounts)
                .preInstructions(preInstructions)
                .remainingAccounts(remainingAccounts)
                .transaction();
        }

        const sig = await sendAndConfirmNoWs(unstakeTx);

        showToast(`Unstaked ${amount} TZLA!`, 'success');
        postUnstakeEvent(walletPubkey!.toString(), sig);
        scheduleEarningsCardRefresh();
        await refreshAll();
    } catch (e: any) {
        if (alreadyProcessed(e)) {
            showToast(`Unstaked ${amount} TZLA!`, 'success');
            await refreshAll();
        } else {
            showToast(parseAnchorError(e), 'error');
        }
    } finally {
        setLoading(false);
    }
}

// ── Error helpers ─────────────────────────────────────────────────────────────

function alreadyProcessed(e: any): boolean {
    return (e?.message ?? '').includes('already been processed');
}

function parseAnchorError(e: any): string {
    console.error('[tzla-stake] error:', e);

    if (e?.error?.errorCode?.code !== undefined) {
        const code  = e.error.errorCode.code as number;
        const match = (IDL.errors as any[]).find((er: any) => er.code === code);
        return match?.msg ?? `Program error ${code}`;
    }

    const logs: string[] = e?.transactionLogs ?? e?.logs ?? [];
    for (const log of logs) {
        for (const err of IDL.errors as any[]) {
            if (log.includes(err.name)) return err.msg;
        }
    }

    const msgText = e?.message ?? '';
    for (const err of IDL.errors as any[]) {
        if (msgText.includes(err.name)) return err.msg;
    }

    const customMatch = msgText.match(/"Custom"\s*:\s*(\d+)/);
    if (customMatch) {
        const code  = parseInt(customMatch[1]);
        const match = (IDL.errors as any[]).find((er: any) => er.code === code);
        if (match) return match.msg;
    }

    if (msgText.includes('User rejected') || msgText.includes('user rejected') ||
        msgText.includes('Transaction cancelled')) {
        return 'Transaction cancelled.';
    }

    if (msgText.includes('Insufficient funds') || msgText.includes('insufficient lamports')) {
        return 'Insufficient SOL for transaction fees.';
    }

    if (msgText.includes('Blockhash not found')) return 'Network busy, please try again.';

    return msgText.slice(0, 150) || 'Unknown error';
}

// ── Exposed globals ───────────────────────────────────────────────────────────

(window as any).connectOrDisconnect = connectOrDisconnect;
(window as any).closeWalletModal = closeWalletModal;

(window as any).refreshUnstakeYield = () => {
    updateUnstakePanelYield();
    startYieldTimer();
};

(window as any).stakeTokens = () => {
    const amount = parseFloat((el<HTMLInputElement>('stakeAmount')).value);
    stake(amount, coreNfts.slice(0, MAX_CORE_NFT_ACCOUNTS));
};

(window as any).claimRewards = () => void claimRewards();
(window as any).unstakeTokens = () => {
    const amount = parseFloat((el<HTMLInputElement>('unstakeAmount')).value);
    unstake(amount, coreNfts.slice(0, MAX_CORE_NFT_ACCOUNTS));
};

(window as any).refreshStats = refreshAll;

(window as any).setMaxStake = async () => {
    if (!walletPubkey) return;
    const userAta = await getAssociatedTokenAddress(STAKE_MINT, walletPubkey);
    try {
        const bal = await connection.getTokenAccountBalance(userAta);
        (el<HTMLInputElement>('stakeAmount')).value = bal.value.uiAmount?.toString() ?? '0';
    } catch (e) { console.error('[tzla] setMaxStake failed:', e); }
};

(window as any).setMaxUnstake = async () => {
    if (!walletPubkey || !program) return;
    const [userStakePda] = PublicKey.findProgramAddressSync(
        [Buffer.from('user_stake'), stakePool.toBuffer(), walletPubkey.toBuffer()],
        PROGRAM_ID,
    );
    try {
        const stakeData  = await program.account['userStake'].fetch(userStakePda);
        const stakeBase  = BigInt(stakeData.stakeAmount.toString());
        const stakeWhole = stakeBase / BigInt(10 ** TOKEN_DECIMALS);
        const stakeFrac  = stakeBase % BigInt(10 ** TOKEN_DECIMALS);
        const tokens     = Number(stakeWhole) + Number(stakeFrac) / 10 ** TOKEN_DECIMALS;
        (el<HTMLInputElement>('unstakeAmount')).value = tokens.toString();
    } catch (e) { console.error('[tzla] setMaxUnstake failed:', e); }
};

// ── Boot ──────────────────────────────────────────────────────────────────────

window.addEventListener('DOMContentLoaded', async () => {
    initStakingCard(() => walletPubkey?.toString() ?? null, showToast);

    document.getElementById('connectBtn')?.addEventListener('click', (event) => {
        event.preventDefault();
        connectOrDisconnect();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeWalletModal();
    });

    await Promise.all([
        refreshPoolStats(),
        refreshDistributed(),
        waitForInjectedWallet(isIos() ? 5000 : isMobileDevice() ? 3000 : 500),
        waitForWallets(1500),
    ]);

    // Only restore the wallet the user explicitly chose last time.
    // Never auto-pick Phantom just because it is injected — that unlocks Phantom
    // on every refresh even when the user connected Jupiter / Solflare / etc.
    const saved = lastWalletName();
    if (saved && !walletPubkey) {
        const adapter = listWalletAdapters().find((item) => item.name === saved && isInstalled(item));
        if (adapter) {
            try {
                bindAdapter(adapter);
                // autoConnect is silent (Wallet Standard `silent: true`); full
                // connect() would pop unlock/approve prompts on every reload.
                await adapter.autoConnect();
                if (adapter.publicKey) {
                    await handleWalletConnected(adapter.publicKey);
                }
            } catch {
                adapter.off('disconnect', onAdapterDisconnect);
                if (walletAdapter === adapter) walletAdapter = null;
                // Stay disconnected if the wallet is locked or revoked — do not
                // fall back to another extension.
            }
        }
    }

    updateConnectButton();
});
