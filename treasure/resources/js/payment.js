import { Buffer } from 'buffer';

// @solana/web3.js still expects a Node-style Buffer global.
if (typeof window !== 'undefined' && !window.Buffer) {
    window.Buffer = Buffer;
}

import { LAMPORTS_PER_SOL, PublicKey, SystemProgram, Transaction } from '@solana/web3.js';

export class PaymentError extends Error {
    constructor(message, { cancelled = false } = {}) {
        super(message);
        this.name = 'PaymentError';
        this.cancelled = cancelled;
    }
}

/** Phantom surfaces a user-rejected signature as code 4001. */
function isUserRejection(err) {
    return err && (err.code === 4001 || /user rejected|user denied/i.test(err.message || ''));
}

/**
 * Sends the per-guess SOL fee to the treasury and resolves to the transaction
 * signature, which the backend then verifies on-chain.
 */
export async function payGuessFee({ treasury, amountSol, from, fetchBlockhash }) {
    if (!window.solana || !window.solana.isPhantom) {
        throw new PaymentError('Phantom wallet not detected.');
    }
    if (!treasury) {
        throw new PaymentError('The treasury address is not configured. Contact the operator.');
    }

    let treasuryKey;
    let payerKey;
    try {
        treasuryKey = new PublicKey(treasury);
        payerKey = new PublicKey(from);
    } catch {
        throw new PaymentError('Invalid wallet or treasury address.');
    }

    const { blockhash, last_valid_block_height: lastValidBlockHeight } = await fetchBlockhash();

    const transaction = new Transaction();
    transaction.feePayer = payerKey;
    transaction.recentBlockhash = blockhash;
    if (lastValidBlockHeight) {
        transaction.lastValidBlockHeight = lastValidBlockHeight;
    }
    transaction.add(SystemProgram.transfer({
        fromPubkey: payerKey,
        toPubkey: treasuryKey,
        lamports: Math.round(amountSol * LAMPORTS_PER_SOL),
    }));

    try {
        const result = await window.solana.signAndSendTransaction(transaction);
        const signature = result && (result.signature || result);
        if (!signature) {
            throw new PaymentError('Wallet did not return a transaction signature.');
        }
        return signature;
    } catch (err) {
        if (isUserRejection(err)) {
            throw new PaymentError('Payment cancelled.', { cancelled: true });
        }
        throw new PaymentError(err.message || 'Payment failed.');
    }
}
