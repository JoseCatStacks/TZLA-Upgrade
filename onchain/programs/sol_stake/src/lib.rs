use anchor_lang::prelude::*;
use anchor_lang::solana_program::keccak;
use anchor_spl::token::{self, Mint, Token, TokenAccount, Transfer};

declare_id!("3pFCija5VgaUxJgoKMoGRCk79c2pkEgUA9NBzRPo8xjJ");

// Daily rate numerators over DAILY_RATE_DENOMINATOR: 69 = 0.069 %/day.
const BASE_DAILY_RATE_NUMERATOR: u128 = 69;
const NFT1_DAILY_RATE_NUMERATOR: u128 = 111;
const NFT2_DAILY_RATE_NUMERATOR: u128 = 222;
const NFT10_DAILY_RATE_NUMERATOR: u128 = 330;
const GOLDEN_DAILY_RATE_NUMERATOR: u128 = 369;
const DAILY_RATE_DENOMINATOR: u128 = 100_000;
const SECONDS_PER_DAY: u128 = 86_400;

// 0.00369 SOL
const STAKE_FEE_LAMPORTS: u64 = 3_690_000;

const FEE_WITHDRAW_AUTHORITY: Pubkey = pubkey!("TZLA26BrLtNQZDq6C1ZdAmRcpKGn8V6Dk7Vm1S2vjT3");

// Golden Ticket collections (mirrored in resources/js/staking.ts).
// These constants were missing from the handover copy of lib.rs.
const GOLDEN_COLLECTION: Pubkey = pubkey!("ETKq2GEUDYa5wm2PsNtxXRRn5iWBZyzWLXQ9WvKZptET");
const GOLDEN_NFT_COLLECTION: Pubkey = pubkey!("FUSkrmKPfJ39fZwJYSgUKYBNcytkWPYsV6n8LB21NB5Q");

const MPL_CORE_PROGRAM_ID: Pubkey = pubkey!("CoREENxT6tW1HoK8ypY1SxRMZTcVPm7R94rH4PZNhX7d");
const BUBBLEGUM_PROGRAM_ID: Pubkey = pubkey!("BGUMAp9Gq7iTEuizy4pqaxsTyUCBK68MDfK752saRPUY");
const SPL_ACCOUNT_COMPRESSION_PROGRAM_ID: Pubkey =
    pubkey!("cmtDvXumGCrqC1Age74AVPhSRVXJMd8PJS91L8KbNCK");
const TOKEN_METADATA_PROGRAM_ID: Pubkey = pubkey!("metaqbxxUerdq28cj1RbAWkYQm3ybzjb6a8bt518x1s");

#[cfg(feature = "localnet")]
const TICKET_TREE_INDEX: usize = 1;

#[cfg(feature = "localnet")]
const GOLDEN_NFT_TOKEN_INDEX: usize = 1;
#[cfg(feature = "localnet")]
const GOLDEN_NFT_METADATA_INDEX: usize = 2;

const NFT2_TIER: u8 = 2;
const GOLDEN_TICKET_TIER: u8 = 3;
const NFT10_TIER: u8 = 4;
const NFT10_THRESHOLD: u8 = 10;
const MAX_COUNTED_NFTS: usize = NFT10_THRESHOLD as usize;

// First byte of a Token Metadata account: Key::MetadataV1.
// https://github.com/metaplex-foundation/mpl-token-metadata (state/mod.rs)
const TOKEN_METADATA_KEY_V1: u8 = 4;

// MPL Core BaseAssetV1 borsh layout: [0] Key, [1..33] owner,
// [33] UpdateAuthority discriminant, [34..66] collection.
// https://github.com/metaplex-foundation/mpl-core (state/asset.rs)
const MPL_CORE_KEY_ASSET_V1: u8 = 1;
const MPL_CORE_UPDATE_AUTHORITY_COLLECTION: u8 = 2;

fn verify_tzla_nft(
    asset_info: &AccountInfo,
    user: &Pubkey,
    expected_collection: &Pubkey,
) -> Result<()> {
    require_keys_eq!(
        *asset_info.owner,
        MPL_CORE_PROGRAM_ID,
        CustomError::InvalidNft
    );

    let data = asset_info.try_borrow_data()?;
    require!(data.len() >= 66, CustomError::InvalidNft);
    require!(data[0] == MPL_CORE_KEY_ASSET_V1, CustomError::InvalidNft);

    let owner = Pubkey::new_from_array(
        data[1..33]
            .try_into()
            .map_err(|_| error!(CustomError::InvalidNft))?,
    );
    require_keys_eq!(owner, *user, CustomError::NotNftOwner);

    require!(
        data[33] == MPL_CORE_UPDATE_AUTHORITY_COLLECTION,
        CustomError::InvalidNft
    );
    let collection = Pubkey::new_from_array(
        data[34..66]
            .try_into()
            .map_err(|_| error!(CustomError::InvalidNft))?,
    );
    require_keys_eq!(collection, *expected_collection, CustomError::NotTzlaNft);

    Ok(())
}

fn count_valid_nfts(
    remaining_accounts: &[AccountInfo],
    user: &Pubkey,
    expected_collection: &Pubkey,
) -> u8 {
    let mut counted_keys: Vec<Pubkey> = Vec::with_capacity(MAX_COUNTED_NFTS);
    for account in remaining_accounts {
        if counted_keys.len() == MAX_COUNTED_NFTS {
            break;
        }
        let key = account.key();
        if counted_keys.contains(&key) {
            continue;
        }
        if verify_tzla_nft(account, user, expected_collection).is_ok() {
            counted_keys.push(key);
        }
    }
    counted_keys.len() as u8
}

fn nft_tier_for_count(nft_count: u8) -> u8 {
    if nft_count >= NFT10_THRESHOLD {
        NFT10_TIER
    } else {
        nft_count.min(NFT2_TIER)
    }
}

#[derive(AnchorSerialize, AnchorDeserialize, Clone)]
pub struct TicketCreator {
    pub address: Pubkey,
    pub verified: bool,
    pub share: u8,
}

#[derive(AnchorSerialize, AnchorDeserialize, Clone)]
pub struct TicketCollection {
    pub verified: bool,
    pub key: Pubkey,
}

#[derive(AnchorSerialize, AnchorDeserialize, Clone)]
pub struct TicketUses {
    pub use_method: u8,
    pub remaining: u64,
    pub total: u64,
}

// Byte-for-byte borsh mirror of Bubblegum's MetadataArgs — the serialized bytes
// are keccak-hashed and compared against the leaf's data_hash.
#[derive(AnchorSerialize, AnchorDeserialize, Clone)]
pub struct TicketMetadata {
    pub name: String,
    pub symbol: String,
    pub uri: String,
    pub seller_fee_basis_points: u16,
    pub primary_sale_happened: bool,
    pub is_mutable: bool,
    pub edition_nonce: Option<u8>,
    pub token_standard: Option<u8>,
    pub collection: Option<TicketCollection>,
    pub uses: Option<TicketUses>,
    pub token_program_version: u8,
    pub creators: Vec<TicketCreator>,
}

#[derive(AnchorSerialize, AnchorDeserialize, Clone)]
pub struct TicketProof {
    pub nonce: u64,
    pub index: u32,
    pub delegate: Pubkey,
    pub metadata: TicketMetadata,
    pub proof: Vec<[u8; 32]>,
}

// spl-account-compression ConcurrentMerkleTree layout offsets.
// https://github.com/solana-program/account-compression (concurrent_merkle_tree_header.rs)
const TREE_HEADER_LEN: usize = 56;

fn verify_golden_ticket(
    tree_info: &AccountInfo,
    ticket: &TicketProof,
    user: &Pubkey,
    expected_collection: &Pubkey,
) -> Result<()> {
    require_keys_eq!(
        *tree_info.owner,
        SPL_ACCOUNT_COMPRESSION_PROGRAM_ID,
        CustomError::InvalidTicketTree
    );
    let data = tree_info.try_borrow_data()?;
    require!(
        data.len() >= TREE_HEADER_LEN + 24,
        CustomError::InvalidTicketTree
    );
    require!(data[0] == 1 && data[1] == 0, CustomError::InvalidTicketTree);

    let max_buffer_size = u32::from_le_bytes(data[2..6].try_into().unwrap()) as usize;
    let max_depth = u32::from_le_bytes(data[6..10].try_into().unwrap()) as usize;

    // The tree authority must be the Bubblegum tree-config PDA; otherwise a
    // look-alike tree with forged leaves would pass.
    let tree_key = tree_info.key();
    let header_authority = Pubkey::new_from_array(data[10..42].try_into().unwrap());
    let (tree_config, _) =
        Pubkey::find_program_address(&[tree_key.as_ref()], &BUBBLEGUM_PROGRAM_ID);
    require_keys_eq!(
        header_authority,
        tree_config,
        CustomError::InvalidTicketTree
    );

    let active_index = u64::from_le_bytes(data[64..72].try_into().unwrap()) as usize;
    require!(
        active_index < max_buffer_size,
        CustomError::InvalidTicketTree
    );
    let changelog_size = 32 + 32 * max_depth + 8;
    let root_offset = TREE_HEADER_LEN + 24 + active_index * changelog_size;
    require!(
        data.len() >= root_offset + 32,
        CustomError::InvalidTicketTree
    );
    let root: [u8; 32] = data[root_offset..root_offset + 32].try_into().unwrap();

    let collection = ticket
        .metadata
        .collection
        .as_ref()
        .ok_or(error!(CustomError::NotGoldenTicket))?;
    require!(collection.verified, CustomError::NotGoldenTicket);
    require_keys_eq!(
        collection.key,
        *expected_collection,
        CustomError::NotGoldenTicket
    );

    let serialized = ticket.metadata.try_to_vec()?;
    let metadata_hash = keccak::hashv(&[&serialized]);
    let data_hash = keccak::hashv(&[
        metadata_hash.as_ref(),
        &ticket.metadata.seller_fee_basis_points.to_le_bytes(),
    ]);

    let creator_bytes: Vec<u8> = ticket
        .metadata
        .creators
        .iter()
        .flat_map(|c| {
            let mut v = c.address.to_bytes().to_vec();
            v.push(c.verified as u8);
            v.push(c.share);
            v
        })
        .collect();
    let creator_hash = keccak::hashv(&[&creator_bytes]);

    require!(
        ticket.nonce == ticket.index as u64,
        CustomError::InvalidTicketProof
    );
    let (asset_id, _) = Pubkey::find_program_address(
        &[b"asset", tree_key.as_ref(), &ticket.nonce.to_le_bytes()],
        &BUBBLEGUM_PROGRAM_ID,
    );

    // Rebuild the LeafSchema::V1 hash with the staker as owner and walk the
    // proof to the live root — a non-owner cannot produce a matching root.
    let mut node = keccak::hashv(&[
        &[1u8],
        asset_id.as_ref(),
        user.as_ref(),
        ticket.delegate.as_ref(),
        &ticket.nonce.to_le_bytes(),
        data_hash.as_ref(),
        creator_hash.as_ref(),
    ])
    .to_bytes();

    require!(
        ticket.proof.len() == max_depth,
        CustomError::InvalidTicketProof
    );
    let mut idx = ticket.index;
    for sibling in &ticket.proof {
        node = if idx & 1 == 0 {
            keccak::hashv(&[&node, sibling]).to_bytes()
        } else {
            keccak::hashv(&[sibling, &node]).to_bytes()
        };
        idx >>= 1;
    }
    require!(node == root, CustomError::InvalidTicketProof);

    Ok(())
}

#[cfg(not(feature = "localnet"))]
fn expected_golden_collection(_remaining_accounts: &[AccountInfo]) -> Result<Pubkey> {
    Ok(GOLDEN_COLLECTION)
}

#[cfg(feature = "localnet")]
fn expected_golden_collection(remaining_accounts: &[AccountInfo]) -> Result<Pubkey> {
    Ok(remaining_accounts
        .first()
        .ok_or(error!(CustomError::MissingTicketAccounts))?
        .key())
}

#[derive(AnchorDeserialize)]
struct MetadataCollection {
    verified: bool,
    key: Pubkey,
}

fn read_metadata_field<T: AnchorDeserialize>(cursor: &mut &[u8]) -> Result<T> {
    T::deserialize(cursor).map_err(|_| error!(CustomError::InvalidNft))
}

// Walks a Token Metadata account to its collection field.
// https://github.com/metaplex-foundation/mpl-token-metadata (state/metadata.rs)
fn metadata_collection(metadata: &[u8]) -> Result<MetadataCollection> {
    let mut cursor: &[u8] = metadata;

    let key: u8 = read_metadata_field(&mut cursor)?;
    require!(key == TOKEN_METADATA_KEY_V1, CustomError::InvalidNft);

    read_metadata_field::<Pubkey>(&mut cursor)?; // update_authority
    read_metadata_field::<Pubkey>(&mut cursor)?; // mint
    read_metadata_field::<String>(&mut cursor)?; // name
    read_metadata_field::<String>(&mut cursor)?; // symbol
    read_metadata_field::<String>(&mut cursor)?; // uri
    read_metadata_field::<u16>(&mut cursor)?; // seller_fee_basis_points
    read_metadata_field::<Option<Vec<(Pubkey, bool, u8)>>>(&mut cursor)?; // creators
    read_metadata_field::<bool>(&mut cursor)?; // primary_sale_happened
    read_metadata_field::<bool>(&mut cursor)?; // is_mutable
    read_metadata_field::<Option<u8>>(&mut cursor)?; // edition_nonce
    read_metadata_field::<Option<u8>>(&mut cursor)?; // token_standard

    read_metadata_field::<Option<MetadataCollection>>(&mut cursor)?
        .ok_or(error!(CustomError::NotGoldenTicket))
}

fn verify_golden_nft(
    token_account_info: &AccountInfo,
    metadata_info: &AccountInfo,
    user: &Pubkey,
    expected_collection: &Pubkey,
) -> Result<()> {
    use anchor_lang::solana_program::program_pack::Pack;
    require_keys_eq!(
        *token_account_info.owner,
        anchor_spl::token::ID,
        CustomError::InvalidNft
    );
    let token_data = token_account_info.try_borrow_data()?;
    let token_account = anchor_spl::token::spl_token::state::Account::unpack(&token_data)
        .map_err(|_| error!(CustomError::InvalidNft))?;
    require_keys_eq!(token_account.owner, *user, CustomError::NotNftOwner);
    require!(token_account.amount >= 1, CustomError::NotNftOwner);

    // The metadata account must be the Token Metadata PDA of that exact mint,
    // so a caller cannot pair their mint with someone else's metadata.
    require_keys_eq!(
        *metadata_info.owner,
        TOKEN_METADATA_PROGRAM_ID,
        CustomError::InvalidNft
    );
    let (expected_metadata, _) = Pubkey::find_program_address(
        &[
            b"metadata",
            TOKEN_METADATA_PROGRAM_ID.as_ref(),
            token_account.mint.as_ref(),
        ],
        &TOKEN_METADATA_PROGRAM_ID,
    );
    require_keys_eq!(
        metadata_info.key(),
        expected_metadata,
        CustomError::InvalidNft
    );

    let data = metadata_info.try_borrow_data()?;
    let collection = metadata_collection(&data)?;
    require!(collection.verified, CustomError::NotGoldenTicket);
    require_keys_eq!(
        collection.key,
        *expected_collection,
        CustomError::NotGoldenTicket
    );

    Ok(())
}

#[cfg(not(feature = "localnet"))]
fn expected_golden_nft_collection(_remaining_accounts: &[AccountInfo]) -> Result<Pubkey> {
    Ok(GOLDEN_NFT_COLLECTION)
}

#[cfg(feature = "localnet")]
fn expected_golden_nft_collection(remaining_accounts: &[AccountInfo]) -> Result<Pubkey> {
    Ok(remaining_accounts
        .first()
        .ok_or(error!(CustomError::MissingTicketAccounts))?
        .key())
}

fn calc_pending(stake_amount: u64, nft_tier: u8, elapsed_seconds: i64) -> Result<u128> {
    if elapsed_seconds <= 0 || stake_amount == 0 {
        return Ok(0);
    }
    let rate = match nft_tier {
        GOLDEN_TICKET_TIER => GOLDEN_DAILY_RATE_NUMERATOR,
        NFT10_TIER.. => NFT10_DAILY_RATE_NUMERATOR,
        2 => NFT2_DAILY_RATE_NUMERATOR,
        1 => NFT1_DAILY_RATE_NUMERATOR,
        _ => BASE_DAILY_RATE_NUMERATOR,
    };
    let pending = (stake_amount as u128)
        .checked_mul(rate)
        .and_then(|v| v.checked_mul(elapsed_seconds as u128))
        .and_then(|v| v.checked_div(DAILY_RATE_DENOMINATOR * SECONDS_PER_DAY))
        .ok_or(CustomError::RewardOverflow)?;
    Ok(pending)
}

/// Pay as many pending rewards as the vault can cover. An empty or short vault
/// must never abort the surrounding stake/unstake — that is what trapped
/// principal when the reward vault ran dry.
fn pay_pending_rewards<'info>(
    token_program: &Program<'info, Token>,
    reward_vault: &Account<'info, TokenAccount>,
    user_token: &Account<'info, TokenAccount>,
    authority: &UncheckedAccount<'info>,
    signer: &[&[&[u8]]],
    pending: u128,
) -> Result<()> {
    if pending == 0 {
        return Ok(());
    }
    require!(pending <= u64::MAX as u128, CustomError::RewardOverflow);
    let payable = std::cmp::min(pending as u64, reward_vault.amount);
    if payable == 0 {
        return Ok(());
    }
    token::transfer(
        CpiContext::new_with_signer(
            token_program.to_account_info(),
            Transfer {
                from: reward_vault.to_account_info(),
                to: user_token.to_account_info(),
                authority: authority.to_account_info(),
            },
            signer,
        ),
        payable,
    )
}

#[program]
pub mod sol_stake {
    use super::*;
    use anchor_spl::token;

    pub fn initialize(
        ctx: Context<Initialize>,
        stake_cap: u64,
        nft_collection: Pubkey,
    ) -> Result<()> {
        let pool = &mut ctx.accounts.pool;
        let clock = &ctx.accounts.clock;

        pool.authority = ctx.accounts.user.key();
        pool.stake_token_mint = ctx.accounts.stake_token_mint.key();
        pool.stake_token_vault = ctx.accounts.stake_token_vault.key();
        pool.reward_token_vault = ctx.accounts.reward_token_vault.key();
        pool.stake_cap = stake_cap;
        pool.nft_collection = nft_collection;
        pool.total_staked = 0;
        pool.bump = ctx.bumps.pool;
        pool.created_at_ts = clock.unix_timestamp;
        pool.created_at_epoch = clock.epoch;

        Ok(())
    }

    pub fn create_user_stake(ctx: Context<CreateUserStake>) -> Result<()> {
        let user_stake = &mut ctx.accounts.user_stake;
        user_stake.pool = ctx.accounts.pool.key();
        user_stake.stake_amount = 0;
        user_stake.last_stake_time = Clock::get()?.unix_timestamp;
        user_stake.nft_tier = 0;
        user_stake.bump = ctx.bumps.user_stake;
        Ok(())
    }

    pub fn stake(ctx: Context<Stake>, stake_amount: u64) -> Result<()> {
        let nft_count = count_valid_nfts(
            ctx.remaining_accounts,
            &ctx.accounts.user.key(),
            &ctx.accounts.pool.nft_collection,
        );
        process_stake(ctx, stake_amount, nft_tier_for_count(nft_count))
    }

    pub fn stake_with_ticket(
        ctx: Context<Stake>,
        stake_amount: u64,
        ticket: TicketProof,
    ) -> Result<()> {
        let expected_collection = expected_golden_collection(ctx.remaining_accounts)?;
        let tree = ctx
            .remaining_accounts
            .get(TICKET_TREE_INDEX)
            .ok_or(error!(CustomError::MissingTicketAccounts))?;
        verify_golden_ticket(
            tree,
            &ticket,
            &ctx.accounts.user.key(),
            &expected_collection,
        )?;
        process_stake(ctx, stake_amount, GOLDEN_TICKET_TIER)
    }

    pub fn unstake(ctx: Context<Unstake>, amount: u64) -> Result<()> {
        let new_tier = if ctx.accounts.user_stake.nft_tier == 0 && ctx.remaining_accounts.is_empty()
        {
            0
        } else {
            let nft_count = count_valid_nfts(
                ctx.remaining_accounts,
                &ctx.accounts.user.key(),
                &ctx.accounts.pool.nft_collection,
            );
            nft_tier_for_count(nft_count)
        };
        process_unstake(ctx, amount, new_tier)
    }

    pub fn unstake_with_ticket(
        ctx: Context<Unstake>,
        amount: u64,
        ticket: TicketProof,
    ) -> Result<()> {
        let expected_collection = expected_golden_collection(ctx.remaining_accounts)?;
        let tree = ctx
            .remaining_accounts
            .get(TICKET_TREE_INDEX)
            .ok_or(error!(CustomError::MissingTicketAccounts))?;
        verify_golden_ticket(
            tree,
            &ticket,
            &ctx.accounts.user.key(),
            &expected_collection,
        )?;
        process_unstake(ctx, amount, GOLDEN_TICKET_TIER)
    }

    pub fn stake_with_nft_ticket(ctx: Context<Stake>, stake_amount: u64) -> Result<()> {
        let expected_collection = expected_golden_nft_collection(ctx.remaining_accounts)?;
        let token_account = ctx
            .remaining_accounts
            .get(GOLDEN_NFT_TOKEN_INDEX)
            .ok_or(error!(CustomError::MissingTicketAccounts))?;
        let metadata = ctx
            .remaining_accounts
            .get(GOLDEN_NFT_METADATA_INDEX)
            .ok_or(error!(CustomError::MissingTicketAccounts))?;
        verify_golden_nft(
            token_account,
            metadata,
            &ctx.accounts.user.key(),
            &expected_collection,
        )?;
        process_stake(ctx, stake_amount, GOLDEN_TICKET_TIER)
    }

    pub fn unstake_with_nft_ticket(ctx: Context<Unstake>, amount: u64) -> Result<()> {
        let expected_collection = expected_golden_nft_collection(ctx.remaining_accounts)?;
        let token_account = ctx
            .remaining_accounts
            .get(GOLDEN_NFT_TOKEN_INDEX)
            .ok_or(error!(CustomError::MissingTicketAccounts))?;
        let metadata = ctx
            .remaining_accounts
            .get(GOLDEN_NFT_METADATA_INDEX)
            .ok_or(error!(CustomError::MissingTicketAccounts))?;
        verify_golden_nft(
            token_account,
            metadata,
            &ctx.accounts.user.key(),
            &expected_collection,
        )?;
        process_unstake(ctx, amount, GOLDEN_TICKET_TIER)
    }

    /// Pay accrued rewards from the reward vault without unstaking principal.
    /// Pays `min(pending, vault_balance)` so an empty vault never traps stakers.
    pub fn claim_rewards(ctx: Context<ClaimRewards>) -> Result<()> {
        process_claim_rewards(ctx)
    }

    pub fn fund_vault(ctx: Context<FundVault>, amount: u64) -> Result<()> {
        token::transfer(
            CpiContext::new(
                ctx.accounts.token_program.to_account_info(),
                Transfer {
                    from: ctx.accounts.authority_token_account.to_account_info(),
                    to: ctx.accounts.reward_token_vault.to_account_info(),
                    authority: ctx.accounts.authority.to_account_info(),
                },
            ),
            amount,
        )
    }

    pub fn update_collection(ctx: Context<UpdateCollection>, nft_collection: Pubkey) -> Result<()> {
        ctx.accounts.pool.nft_collection = nft_collection;
        Ok(())
    }

    pub fn set_stake_cap(ctx: Context<SetStakeCap>, new_stake_cap: u64) -> Result<()> {
        ctx.accounts.pool.stake_cap = new_stake_cap;
        Ok(())
    }

    pub fn withdraw_fees(ctx: Context<WithdrawFees>, amount: u64) -> Result<()> {
        require!(
            amount <= ctx.accounts.fee_vault.lamports(),
            CustomError::InsufficientFees
        );

        let pool_key = ctx.accounts.pool.key();
        let fee_vault_seeds = &[b"fee_vault", pool_key.as_ref(), &[ctx.bumps.fee_vault]];
        let signer = &[&fee_vault_seeds[..]];

        anchor_lang::system_program::transfer(
            CpiContext::new_with_signer(
                ctx.accounts.system_program.to_account_info(),
                anchor_lang::system_program::Transfer {
                    from: ctx.accounts.fee_vault.to_account_info(),
                    to: ctx.accounts.authority.to_account_info(),
                },
                signer,
            ),
            amount,
        )
    }
}

fn process_stake(ctx: Context<Stake>, stake_amount: u64, new_tier: u8) -> Result<()> {
    use anchor_spl::token;

    let pool = &mut ctx.accounts.pool;
    let user_stake = &mut ctx.accounts.user_stake;
    let clock = &ctx.accounts.clock;

    require!(
        pool.total_staked + (stake_amount as u128) <= pool.stake_cap as u128,
        CustomError::StakeCapExceeded
    );

    anchor_lang::system_program::transfer(
        CpiContext::new(
            ctx.accounts.system_program.to_account_info(),
            anchor_lang::system_program::Transfer {
                from: ctx.accounts.user.to_account_info(),
                to: ctx.accounts.fee_vault.to_account_info(),
            },
        ),
        STAKE_FEE_LAMPORTS,
    )?;

    let previous_tier = user_stake.nft_tier;
    user_stake.nft_tier = new_tier;

    let pool_key = pool.key();
    let authority_seeds = &[b"pool_authority", pool_key.as_ref(), &[ctx.bumps.authority]];
    let signer = &[&authority_seeds[..]];

    // Rewards accrued since the last interaction are paid at the tier that was
    // active during that period, not the tier proven now.
    let elapsed = clock.unix_timestamp - user_stake.last_stake_time;
    let pending = calc_pending(user_stake.stake_amount, previous_tier, elapsed)?;

    pay_pending_rewards(
        &ctx.accounts.token_program,
        &ctx.accounts.reward_token_vault,
        &ctx.accounts.user_token_account,
        &ctx.accounts.authority,
        signer,
        pending,
    )?;

    token::transfer(
        CpiContext::new(
            ctx.accounts.token_program.to_account_info(),
            Transfer {
                from: ctx.accounts.user_token_account.to_account_info(),
                to: ctx.accounts.stake_token_vault.to_account_info(),
                authority: ctx.accounts.user.to_account_info(),
            },
        ),
        stake_amount,
    )?;

    user_stake.stake_amount = user_stake
        .stake_amount
        .checked_add(stake_amount)
        .ok_or(error!(CustomError::ArithmeticOverflow))?;
    pool.total_staked = pool
        .total_staked
        .checked_add(stake_amount as u128)
        .ok_or(error!(CustomError::ArithmeticOverflow))?;
    user_stake.last_stake_time = clock.unix_timestamp;
    user_stake.pool = pool.key();

    Ok(())
}

fn process_unstake(ctx: Context<Unstake>, amount: u64, new_tier: u8) -> Result<()> {
    use anchor_spl::token;

    let pool = &mut ctx.accounts.pool;
    let user_stake = &mut ctx.accounts.user_stake;

    require!(
        amount <= user_stake.stake_amount,
        CustomError::InsufficientStake
    );

    let pool_key = pool.key();
    let authority_seeds = &[b"pool_authority", pool_key.as_ref(), &[ctx.bumps.authority]];
    let signer = &[&authority_seeds[..]];

    let clock = Clock::get()?;
    user_stake.nft_tier = new_tier;

    // Principal only — rewards are claimed separately via `claim_rewards`.
    token::transfer(
        CpiContext::new_with_signer(
            ctx.accounts.token_program.to_account_info(),
            Transfer {
                from: ctx.accounts.stake_token_vault.to_account_info(),
                to: ctx.accounts.user_token_account.to_account_info(),
                authority: ctx.accounts.authority.to_account_info(),
            },
            signer,
        ),
        amount,
    )?;

    user_stake.stake_amount = user_stake
        .stake_amount
        .checked_sub(amount)
        .ok_or(error!(CustomError::ArithmeticOverflow))?;
    pool.total_staked = pool
        .total_staked
        .checked_sub(amount as u128)
        .ok_or(error!(CustomError::ArithmeticOverflow))?;
    user_stake.last_stake_time = clock.unix_timestamp;

    Ok(())
}

fn process_claim_rewards(ctx: Context<ClaimRewards>) -> Result<()> {
    let user_stake = &mut ctx.accounts.user_stake;

    require!(user_stake.stake_amount > 0, CustomError::InsufficientStake);

    let pool_key = ctx.accounts.pool.key();
    let authority_seeds = &[b"pool_authority", pool_key.as_ref(), &[ctx.bumps.authority]];
    let signer = &[&authority_seeds[..]];

    let clock = Clock::get()?;
    let elapsed = clock.unix_timestamp - user_stake.last_stake_time;
    let pending = calc_pending(user_stake.stake_amount, user_stake.nft_tier, elapsed)?;

    pay_pending_rewards(
        &ctx.accounts.token_program,
        &ctx.accounts.reward_token_vault,
        &ctx.accounts.user_token_account,
        &ctx.accounts.authority,
        signer,
        pending,
    )?;

    user_stake.last_stake_time = clock.unix_timestamp;

    Ok(())
}

#[error_code]
pub enum CustomError {
    #[msg("Stake cap exceeded")]
    StakeCapExceeded,
    #[msg("Insufficient stake to unstake")]
    InsufficientStake,
    #[msg("NFT account is not a valid Metaplex Core asset")]
    InvalidNft,
    #[msg("NFT is not owned by the user")]
    NotNftOwner,
    #[msg("NFT does not belong to the TZLA collection")]
    NotTzlaNft,
    #[msg("Only the pool authority can perform this action")]
    Unauthorized,
    #[msg("Pending rewards exceed u64 maximum — contact the pool authority")]
    RewardOverflow,
    #[msg("Arithmetic overflow or underflow")]
    ArithmeticOverflow,
    #[msg("Merkle tree account is not a valid Bubblegum tree")]
    InvalidTicketTree,
    #[msg("cNFT is not a verified member of the Golden Ticket collection")]
    NotGoldenTicket,
    #[msg("Merkle proof is invalid or the ticket is not owned by the user")]
    InvalidTicketProof,
    #[msg("Missing the Golden Ticket Merkle tree account")]
    MissingTicketAccounts,
    #[msg("Not enough collected fees in the fee vault")]
    InsufficientFees,
}

#[derive(Accounts)]
pub struct Initialize<'info> {
    #[account(mut)]
    pub user: Signer<'info>,

    pub stake_token_mint: Box<Account<'info, Mint>>,

    #[account(
        init,
        payer = user,
        seeds = [b"stake_pool", user.key().as_ref()],
        bump,
        space = Pool::DISCRIMINATOR.len() + Pool::INIT_SPACE
    )]
    pub pool: Account<'info, Pool>,

    #[account(
        init,
        payer = user,
        seeds = [b"stake_vault", pool.key().as_ref()],
        bump,
        token::mint = stake_token_mint,
        token::authority = authority
    )]
    pub stake_token_vault: Box<Account<'info, TokenAccount>>,

    #[account(
        init,
        payer = user,
        seeds = [b"reward_vault", pool.key().as_ref()],
        bump,
        token::mint = stake_token_mint,
        token::authority = authority
    )]
    pub reward_token_vault: Box<Account<'info, TokenAccount>>,

    /// CHECK: PDA used as vault authority
    #[account(seeds = [b"pool_authority", pool.key().as_ref()], bump)]
    pub authority: UncheckedAccount<'info>,

    pub rent: Sysvar<'info, Rent>,
    pub clock: Sysvar<'info, Clock>,
    pub token_program: Program<'info, Token>,
    pub system_program: Program<'info, System>,
}

#[derive(Accounts)]
pub struct CreateUserStake<'info> {
    #[account(mut)]
    pub user: Signer<'info>,

    pub pool: Account<'info, Pool>,

    #[account(
        init,
        payer = user,
        seeds = [b"user_stake", pool.key().as_ref(), user.key().as_ref()],
        bump,
        space = UserStake::DISCRIMINATOR.len() + UserStake::INIT_SPACE
    )]
    pub user_stake: Account<'info, UserStake>,

    pub system_program: Program<'info, System>,
}

#[derive(Accounts)]
pub struct Stake<'info> {
    #[account(mut)]
    pub user: Signer<'info>,

    #[account(mut, has_one = stake_token_vault, has_one = reward_token_vault)]
    pub pool: Account<'info, Pool>,

    #[account(mut, constraint = user_token_account.mint == pool.stake_token_mint)]
    pub user_token_account: Box<Account<'info, TokenAccount>>,

    #[account(mut)]
    pub stake_token_vault: Box<Account<'info, TokenAccount>>,

    #[account(mut)]
    pub reward_token_vault: Box<Account<'info, TokenAccount>>,

    #[account(
        mut,
        seeds = [b"user_stake", pool.key().as_ref(), user.key().as_ref()],
        bump = user_stake.bump,
        has_one = pool
    )]
    pub user_stake: Account<'info, UserStake>,

    /// CHECK: PDA used as vault authority
    #[account(seeds = [b"pool_authority", pool.key().as_ref()], bump)]
    pub authority: UncheckedAccount<'info>,

    #[account(
        mut,
        seeds = [b"fee_vault", pool.key().as_ref()],
        bump
    )]
    pub fee_vault: SystemAccount<'info>,

    pub rent: Sysvar<'info, Rent>,
    pub clock: Sysvar<'info, Clock>,
    pub token_program: Program<'info, Token>,
    pub system_program: Program<'info, System>,
}

#[derive(Accounts)]
pub struct Unstake<'info> {
    #[account(mut)]
    pub user: Signer<'info>,

    #[account(mut, has_one = stake_token_vault, has_one = reward_token_vault)]
    pub pool: Account<'info, Pool>,

    #[account(mut, constraint = user_token_account.mint == pool.stake_token_mint)]
    pub user_token_account: Box<Account<'info, TokenAccount>>,

    #[account(mut)]
    pub stake_token_vault: Box<Account<'info, TokenAccount>>,

    #[account(mut)]
    pub reward_token_vault: Box<Account<'info, TokenAccount>>,

    #[account(
        mut,
        seeds = [b"user_stake", pool.key().as_ref(), user.key().as_ref()],
        bump = user_stake.bump,
        has_one = pool
    )]
    pub user_stake: Account<'info, UserStake>,

    /// CHECK: PDA used as vault authority
    #[account(seeds = [b"pool_authority", pool.key().as_ref()], bump)]
    pub authority: UncheckedAccount<'info>,

    pub token_program: Program<'info, Token>,
}

#[derive(Accounts)]
pub struct ClaimRewards<'info> {
    #[account(mut)]
    pub user: Signer<'info>,

    #[account(has_one = reward_token_vault)]
    pub pool: Account<'info, Pool>,

    #[account(mut, constraint = user_token_account.mint == pool.stake_token_mint)]
    pub user_token_account: Box<Account<'info, TokenAccount>>,

    #[account(mut)]
    pub reward_token_vault: Box<Account<'info, TokenAccount>>,

    #[account(
        mut,
        seeds = [b"user_stake", pool.key().as_ref(), user.key().as_ref()],
        bump = user_stake.bump,
        has_one = pool
    )]
    pub user_stake: Account<'info, UserStake>,

    /// CHECK: PDA used as vault authority
    #[account(seeds = [b"pool_authority", pool.key().as_ref()], bump)]
    pub authority: UncheckedAccount<'info>,

    pub token_program: Program<'info, Token>,
}

#[derive(Accounts)]
pub struct FundVault<'info> {
    #[account(has_one = reward_token_vault, has_one = authority @ CustomError::Unauthorized)]
    pub pool: Account<'info, Pool>,

    #[account(mut)]
    pub reward_token_vault: Box<Account<'info, TokenAccount>>,

    #[account(mut, constraint = authority_token_account.mint == pool.stake_token_mint)]
    pub authority_token_account: Box<Account<'info, TokenAccount>>,

    pub authority: Signer<'info>,

    pub token_program: Program<'info, Token>,
}

#[derive(Accounts)]
pub struct UpdateCollection<'info> {
    #[account(mut, has_one = authority @ CustomError::Unauthorized)]
    pub pool: Account<'info, Pool>,
    pub authority: Signer<'info>,
}

#[derive(Accounts)]
pub struct SetStakeCap<'info> {
    #[account(mut, has_one = authority @ CustomError::Unauthorized)]
    pub pool: Account<'info, Pool>,
    pub authority: Signer<'info>,
}

#[derive(Accounts)]
pub struct WithdrawFees<'info> {
    pub pool: Account<'info, Pool>,

    #[account(
        mut,
        seeds = [b"fee_vault", pool.key().as_ref()],
        bump
    )]
    pub fee_vault: SystemAccount<'info>,

    #[account(
        mut,
        address = FEE_WITHDRAW_AUTHORITY @ CustomError::Unauthorized
    )]
    pub authority: Signer<'info>,

    pub system_program: Program<'info, System>,
}

#[account]
#[derive(InitSpace)]
pub struct Pool {
    pub authority: Pubkey,
    pub stake_token_mint: Pubkey,
    pub stake_token_vault: Pubkey,
    pub reward_token_vault: Pubkey,
    pub nft_collection: Pubkey,
    // legacy_* fields keep the byte offsets of the old distribution model so
    // existing onchain accounts stay deserializable.
    pub legacy_acc_reward_per_share: u128,
    pub total_staked: u128,
    pub legacy_last_reward_balance: u64,
    pub stake_cap: u64,
    pub created_at_epoch: u64,
    pub legacy_updated_at_epoch: u64,
    pub created_at_ts: i64,
    pub legacy_updated_at_ts: i64,
    pub bump: u8,
}

#[account]
#[derive(InitSpace)]
pub struct UserStake {
    pub pool: Pubkey,
    pub stake_amount: u64,
    pub legacy_reward_debt: u128,
    pub last_stake_time: i64,
    pub nft_tier: u8,
    pub bump: u8,
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn tier_mapping_from_nft_count() {
        assert_eq!(nft_tier_for_count(0), 0);
        assert_eq!(nft_tier_for_count(1), 1);
        assert_eq!(nft_tier_for_count(2), NFT2_TIER);
        assert_eq!(nft_tier_for_count(9), NFT2_TIER);
        assert_eq!(nft_tier_for_count(10), NFT10_TIER);
        assert_eq!(nft_tier_for_count(255), NFT10_TIER);
    }

    #[test]
    fn calc_pending_uses_the_rate_for_each_tier() {
        // 1_000_000_000 staked for one full day pays exactly numerator × 10_000.
        let one_day = SECONDS_PER_DAY as i64;
        let staked = 1_000_000_000u64;
        for (tier, numerator) in [
            (0u8, BASE_DAILY_RATE_NUMERATOR),
            (1, NFT1_DAILY_RATE_NUMERATOR),
            (2, NFT2_DAILY_RATE_NUMERATOR),
            (GOLDEN_TICKET_TIER, GOLDEN_DAILY_RATE_NUMERATOR),
            (NFT10_TIER, NFT10_DAILY_RATE_NUMERATOR),
        ] {
            assert_eq!(
                calc_pending(staked, tier, one_day).unwrap(),
                staked as u128 * numerator / DAILY_RATE_DENOMINATOR,
                "tier {tier}"
            );
        }
        assert_eq!(calc_pending(staked, 0, 0).unwrap(), 0);
        assert_eq!(calc_pending(0, NFT10_TIER, one_day).unwrap(), 0);
    }

    // Expected values produced with @metaplex-foundation/mpl-bubblegum
    // (hashMetadataData, hashMetadataCreators, hashLeaf) for the same inputs.
    #[test]
    fn ticket_hashing_matches_bubblegum() {
        let owner: Pubkey = pubkey!("TZLAjDiR2M7bStK8Vy7QkCFfhD62HMQzFdoU7viBfVp");
        let tree: Pubkey = pubkey!("4MxEG8cVnB8vJxjv62ot2RFtrLwq7JdtXkEK35NiybYZ");
        let coll: Pubkey = pubkey!("ETKq2GEUDYa5wm2PsNtxXRRn5iWBZyzWLXQ9WvKZptET");

        let metadata = TicketMetadata {
            name: "TZLA Golden Ticket #14".to_string(),
            symbol: "".to_string(),
            uri: "https://arweave.net/example".to_string(),
            seller_fee_basis_points: 0,
            primary_sale_happened: false,
            is_mutable: true,
            edition_nonce: None,
            token_standard: Some(0), // NonFungible
            collection: Some(TicketCollection {
                verified: true,
                key: coll,
            }),
            uses: None,
            token_program_version: 0, // Original
            creators: vec![TicketCreator {
                address: owner,
                verified: true,
                share: 100,
            }],
        };

        let serialized = metadata.try_to_vec().unwrap();
        let metadata_hash = keccak::hashv(&[&serialized]);
        let data_hash = keccak::hashv(&[
            metadata_hash.as_ref(),
            &metadata.seller_fee_basis_points.to_le_bytes(),
        ]);
        assert_eq!(
            hex::encode(data_hash.as_ref()),
            "be5b4d44859d39fddbf89e730bc6f7b4b47ab853a5a535748d37244a340feae5"
        );

        let creator_bytes: Vec<u8> = metadata
            .creators
            .iter()
            .flat_map(|c| {
                let mut v = c.address.to_bytes().to_vec();
                v.push(c.verified as u8);
                v.push(c.share);
                v
            })
            .collect();
        let creator_hash = keccak::hashv(&[&creator_bytes]);
        assert_eq!(
            hex::encode(creator_hash.as_ref()),
            "d7a3b02932d485b16d1f2998711d1638a37d38504af3779a13159857bfc9ed63"
        );

        let nonce: u64 = 14;
        let (asset_id, _) = Pubkey::find_program_address(
            &[b"asset", tree.as_ref(), &nonce.to_le_bytes()],
            &BUBBLEGUM_PROGRAM_ID,
        );
        let leaf = keccak::hashv(&[
            &[1u8],
            asset_id.as_ref(),
            owner.as_ref(),
            owner.as_ref(), // delegate defaults to owner
            &nonce.to_le_bytes(),
            data_hash.as_ref(),
            creator_hash.as_ref(),
        ]);
        assert_eq!(
            hex::encode(leaf.as_ref()),
            "a3fa220b2bab6f49fc3bb4992598990d082d1b5de014d2e8a0534fe20b23e302"
        );
    }

    #[test]
    fn golden_nft_metadata_parses_collection() {
        let collection_key = pubkey!("FUSkrmKPfJ39fZwJYSgUKYBNcytkWPYsV6n8LB21NB5Q");

        let mut data = Vec::new();
        TOKEN_METADATA_KEY_V1.serialize(&mut data).unwrap(); // key
        Pubkey::new_unique().serialize(&mut data).unwrap(); // update_authority
        Pubkey::new_unique().serialize(&mut data).unwrap(); // mint
        "TZLA Golden Ticket"
            .to_string()
            .serialize(&mut data)
            .unwrap(); // name
        "TGT".to_string().serialize(&mut data).unwrap(); // symbol
        "https://example.com/golden.json"
            .to_string()
            .serialize(&mut data)
            .unwrap(); // uri
        0u16.serialize(&mut data).unwrap(); // seller_fee_basis_points
        Some(vec![(Pubkey::new_unique(), true, 100u8)])
            .serialize(&mut data)
            .unwrap(); // creators
        false.serialize(&mut data).unwrap(); // primary_sale_happened
        true.serialize(&mut data).unwrap(); // is_mutable
        None::<u8>.serialize(&mut data).unwrap(); // edition_nonce
        Some(0u8).serialize(&mut data).unwrap(); // token_standard
                                                 // collection = Some({ verified: true, key: collection_key })
        1u8.serialize(&mut data).unwrap();
        true.serialize(&mut data).unwrap();
        collection_key.serialize(&mut data).unwrap();

        let collection = metadata_collection(&data).unwrap();
        assert!(collection.verified);
        assert_eq!(collection.key, collection_key);
    }

    #[test]
    fn golden_nft_metadata_without_collection_is_rejected() {
        let mut data = Vec::new();
        TOKEN_METADATA_KEY_V1.serialize(&mut data).unwrap();
        Pubkey::new_unique().serialize(&mut data).unwrap();
        Pubkey::new_unique().serialize(&mut data).unwrap();
        "Some NFT".to_string().serialize(&mut data).unwrap();
        "SN".to_string().serialize(&mut data).unwrap();
        "https://example.com/x.json"
            .to_string()
            .serialize(&mut data)
            .unwrap();
        500u16.serialize(&mut data).unwrap();
        None::<Vec<(Pubkey, bool, u8)>>
            .serialize(&mut data)
            .unwrap();
        false.serialize(&mut data).unwrap();
        true.serialize(&mut data).unwrap();
        None::<u8>.serialize(&mut data).unwrap();
        Some(0u8).serialize(&mut data).unwrap();
        0u8.serialize(&mut data).unwrap(); // collection = None

        assert!(metadata_collection(&data).is_err());
    }
}
