import { Buffer } from 'buffer';

// @solana/web3.js still expects a Node-style Buffer global.
if (typeof window !== 'undefined' && !window.Buffer) {
    window.Buffer = Buffer;
}

import { LAMPORTS_PER_SOL, PublicKey, SystemProgram, Transaction } from '@solana/web3.js';

export class PaymentError extends Error {
    constructor(message, { cancelled = false, signature = null } = {}) {
        super(message);
        this.name = 'PaymentError';
        this.cancelled = cancelled;
        this.signature = signature;
    }
}

/** Most wallets surface a user-rejected signature as code 4001. */
function isUserRejection(err) {
    return err && (err.code === 4001 || /user rejected|user denied|cancelled|canceled/i.test(err.message || ''));
}

const BASE58 = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

function toBase58(bytes) {
    const arr = bytes instanceof Uint8Array ? bytes : new Uint8Array(bytes);
    let zeros = 0;
    while (zeros < arr.length && arr[zeros] === 0) zeros++;
    const digits = [0];
    for (let i = zeros; i < arr.length; i++) {
        let carry = arr[i];
        for (let j = 0; j < digits.length; j++) {
            carry += digits[j] << 8;
            digits[j] = carry % 58;
            carry = (carry / 58) | 0;
        }
        while (carry > 0) { digits.push(carry % 58); carry = (carry / 58) | 0; }
    }
    let out = '1'.repeat(zeros);
    for (let k = digits.length - 1; k >= 0; k--) out += BASE58[digits[k]];
    return out;
}

/** Normalize wallet adapter signature return values to a base58 string. */
export function normalizeSignature(sig) {
    if (!sig) return null;
    if (typeof sig === 'string') {
        // Uint8Array accidentally stringified becomes "1,2,3,..."
        if (sig.includes(',')) {
            const parts = sig.split(',').map((n) => Number(n));
            if (parts.every((n) => Number.isInteger(n) && n >= 0 && n <= 255)) {
                return toBase58(Uint8Array.from(parts));
            }
        }
        return sig;
    }
    if (sig instanceof Uint8Array) return toBase58(sig);
    if (Array.isArray(sig)) return toBase58(Uint8Array.from(sig));
    if (sig.signature) return normalizeSignature(sig.signature);
    return null;
}

/**
 * Sends the per-guess SOL fee to the treasury and resolves to the transaction
 * signature, which the backend then verifies on-chain.
 *
 * Signs in the wallet, then broadcasts through our Helius-backed API so a
 * flaky public RPC cannot throw after SOL has already left the wallet.
 *
 * @param {{ treasury: string, amountSol: number, from: string, fetchBlockhash: Function, broadcastTransaction: Function, adapter: import('@solana/wallet-adapter-base').Adapter }} opts
 */
export async function payGuessFee({
    treasury,
    amountSol,
    from,
    fetchBlockhash,
    broadcastTransaction,
    adapter,
}) {
    if (!adapter?.connected) {
        throw new PaymentError('Connect a wallet before paying the guess fee.');
    }
    if (typeof adapter.signTransaction !== 'function') {
        throw new PaymentError('Connected wallet cannot sign transactions. Try Phantom, Solflare, or Jupiter.');
    }
    if (!treasury) {
        throw new PaymentError('The treasury address is not configured. Contact the operator.');
    }
    if (amountSol === null || amountSol === undefined || Number(amountSol) <= 0) {
        throw new PaymentError('Invalid fee amount.');
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
        lamports: Math.round(Number(amountSol) * LAMPORTS_PER_SOL),
    }));

    let signed;
    try {
        signed = await adapter.signTransaction(transaction);
    } catch (err) {
        if (isUserRejection(err)) {
            throw new PaymentError('Payment cancelled.', { cancelled: true });
        }
        throw new PaymentError(err.message || 'Wallet failed to sign the payment.');
    }

    const wire = signed.serialize();
    const base64 = Buffer.from(wire).toString('base64');

    try {
        const result = await broadcastTransaction(base64);
        const signature = normalizeSignature(result?.signature ?? result);
        if (!signature) {
            throw new PaymentError('Broadcast succeeded but no signature was returned. Check your wallet activity before retrying.');
        }
        return signature;
    } catch (err) {
        if (err instanceof PaymentError) throw err;
        if (err?.status === 502 || err?.code === 'broadcast_failed') {
            throw new PaymentError(
                err.message || 'Could not broadcast payment. If SOL left your wallet, do not pay again — contact support with the explorer link.',
            );
        }
        throw new PaymentError(err.message || 'Payment broadcast failed.');
    }
}
