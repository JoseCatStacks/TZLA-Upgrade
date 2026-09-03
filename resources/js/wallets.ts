import { getWallets } from '@wallet-standard/app';
import { WalletReadyState, type Adapter } from '@solana/wallet-adapter-base';
import {
    StandardWalletAdapter,
    isWalletAdapterCompatibleWallet,
} from '@solana/wallet-standard-wallet-adapter-base';
import { PhantomWalletAdapter } from '@solana/wallet-adapter-phantom';
import { SolflareWalletAdapter } from '@solana/wallet-adapter-solflare';
import { initMobileWallets } from './mobile-wallet';

const LAST_WALLET_KEY = 'tzla:last-wallet';

if (typeof window !== 'undefined') {
    initMobileWallets();
}

function fallbackAdapters(): Adapter[] {
    return [new PhantomWalletAdapter(), new SolflareWalletAdapter()];
}

export function listWalletAdapters(): Adapter[] {
    const { get } = getWallets();
    const standard: Adapter[] = get()
        .filter(isWalletAdapterCompatibleWallet)
        .map((wallet) => new StandardWalletAdapter({ wallet }));

    const byName = new Map<string, Adapter>();
    for (const adapter of fallbackAdapters()) {
        byName.set(adapter.name, adapter);
    }
    for (const adapter of standard) {
        byName.set(adapter.name, adapter);
    }

    return [...byName.values()].sort((a, b) => {
        const rank = (state: WalletReadyState): number => {
            if (state === WalletReadyState.Installed) return 0;
            if (state === WalletReadyState.Loadable) return 1;
            return 2;
        };
        return rank(a.readyState) - rank(b.readyState) || a.name.localeCompare(b.name);
    });
}

export function rememberWallet(name: string): void {
    try {
        localStorage.setItem(LAST_WALLET_KEY, name);
    } catch {
        // ignore private-mode storage failures
    }
}

export function forgetWallet(): void {
    try {
        localStorage.removeItem(LAST_WALLET_KEY);
    } catch {
        // ignore
    }
}

export function lastWalletName(): string | null {
    try {
        return localStorage.getItem(LAST_WALLET_KEY);
    } catch {
        return null;
    }
}

export function isInstalled(adapter: Adapter): boolean {
    return adapter.readyState === WalletReadyState.Installed;
}

export function canConnect(adapter: Adapter): boolean {
    return adapter.readyState === WalletReadyState.Installed
        || adapter.readyState === WalletReadyState.Loadable;
}

export function walletStatusLabel(adapter: Adapter): string {
    if (adapter.readyState === WalletReadyState.Installed) return 'Detected';
    if (adapter.readyState === WalletReadyState.Loadable) return 'Available';
    return 'Install';
}

/** Wallet Standard wallets often register a beat after DOMContentLoaded. */
export function waitForWallets(ms = 1200): Promise<void> {
    return new Promise((resolve) => {
        const { get, on } = getWallets();
        if (get().some(isWalletAdapterCompatibleWallet)) {
            resolve();
            return;
        }

        let settled = false;
        const finish = (): void => {
            if (settled) return;
            settled = true;
            resolve();
        };

        const timeout = window.setTimeout(finish, ms);
        try {
            const unsubscribe = on('register', () => {
                window.clearTimeout(timeout);
                try { unsubscribe(); } catch { /* ignore */ }
                finish();
            });
        } catch {
            finish();
        }
    });
}

export function installUrl(name: string): string | null {
    const key = name.toLowerCase();
    if (key.includes('phantom')) return 'https://phantom.app/download';
    if (key.includes('solflare')) return 'https://solflare.com/download';
    if (key.includes('backpack')) return 'https://backpack.app/download';
    return null;
}
