import { WalletReadyState, type Adapter } from '@solana/wallet-adapter-base';
import {
    createDefaultAuthorizationCache,
    createDefaultChainSelector,
    createDefaultWalletNotFoundHandler,
    registerMwa,
} from '@solana-mobile/wallet-standard-mobile';

type SolanaProvider = {
    isPhantom?: boolean;
    isSolflare?: boolean;
    isConnected?: boolean;
    publicKey?: { toBytes(): Uint8Array };
    connect?(opts?: { onlyIfTrusted?: boolean }): Promise<{ publicKey: { toBytes(): Uint8Array } }>;
};

export type WalletAppLink = {
    id: 'phantom' | 'solflare';
    name: string;
    label: string;
    url: string;
    icon: string;
};

function win(): Window & {
    phantom?: { solana?: SolanaProvider };
    solana?: SolanaProvider;
    solflare?: SolanaProvider;
} {
    return window;
}

const PHANTOM_ICON =
    'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIxMDgiIGhlaWdodD0iMTA4IiB2aWV3Qm94PSIwIDAgMTA4IDEwOCIgZmlsbD0ibm9uZSI+PHJlY3Qgd2lkdGg9IjEwOCIgaGVpZ2h0PSIxMDgiIHJ4PSIyNiIgZmlsbD0iI0FCOUZGMiIvPjwvc3ZnPg==';

const SOLFLARE_ICON =
    'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz48c3ZnIGlkPSJTIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA1MCA1MCI+PGRlZnM+PHN0eWxlPi5jbHMtMXtmaWxsOiMwMjA1MGE7c3Ryb2tlOiNmZmVmNDY7c3Ryb2tlLW1pdGVybGltaXQ6MTA7c3Ryb2tlLXdpZHRoOi41cHg7fS5jbHMtMntmaWxsOiNmZmVmNDY7fTwvc3R5bGU+PC9kZWZzPjxyZWN0IGNsYXNzPSJjbHMtMiIgeD0iMCIgd2lkdGg9IjUwIiBoZWlnaHQ9IjUwIiByeD0iMTIiIHJ5PSIxMiIvPjxwYXRoIGNsYXNzPSJjbHMtMSIgZD0iTTI0LjIzLDI2LjQybDIuNDYtMi4zOCw0LjU5LDEuNWMzLjAxLDEsNC41MSwyLjg0LDQuNTEsNS40MywwLDEuOTYtLjc1LDMuMjYtMi4yNSw0LjkzbC0uNDYuNS4xNy0xLjE3Yy42Ny00LjI2LS41OC02LjA5LTQuNzItNy40M2wtNC4zLTEuMzhoMFpNMTguMDUsMTEuODVsMTIuNTIsNC4xNy0yLjcxLDIuNTktNi41MS0yLjE3Yy0yLjI1LS43NS0zLjAxLTEuOTYtMy4zLTQuNTF2LS4wOGgwWk0xNy4zLDMzLjA2bDIuODQtMi43MSw1LjM0LDEuNzVjMi44LjkyLDMuNzYsMi4xMywzLjQ2LDUuMThsLTExLjY1LTQuMjJoMFpNMTMuNzEsMjAuOTVjMC0uNzkuNDItMS41NCwxLjEzLTIuMTcuNzUsMS4wOSwyLjA1LDIuMDUsNC4wOSwyLjcxbDQuNDIsMS40Ni0yLjQ2LDIuMzgtNC4zNC0xLjQyYy0yLS42Ny0yLjg0LTEuNjctMi44NC0yLjk2TTI2LjgyLDQyLjg3YzkuMTgtNi4wOSwxNC4xMS0xMC4yMywxNC4xMS0xNS4zMiwwLTMuMzgtMi01LjI2LTYuNDMtNi43MmwtMy4zNC0xLjEzLDkuMTQtOC43Ny0xLjg0LTEuOTYtMi43MSwyLjM4LTEyLjgxLTQuMjJjLTMuOTcsMS4yOS04Ljk3LDUuMDktOC45Nyw4Ljg5LDAsLjQyLjA0LjgzLjE3LDEuMjktMy4zLDEuODgtNC42MywzLjYzLTQuNjMsNS44LDAsMi4wNSwxLjA5LDQuMDksNC41NSw1LjIybDIuNzUuOTItOS41Miw5LjE0LDEuODQsMS45NiwyLjk2LTIuNzEsMTQuNzMsNS4yMmgwWiIvPjwvc3ZnPg==';

let mwaRegistered = false;

/** Android Chrome: register Mobile Wallet Adapter (Phantom, Solflare, etc.). */
export function initMobileWallets(): void {
    if (mwaRegistered || typeof window === 'undefined') return;
    mwaRegistered = true;

    try {
        registerMwa({
            appIdentity: {
                name: 'TZLA Staking',
                uri: window.location.origin,
                icon: `${window.location.origin}/favicon.ico`,
            },
            authorizationCache: createDefaultAuthorizationCache(),
            chains: ['solana:mainnet'],
            chainSelector: createDefaultChainSelector(),
            onWalletNotFound: createDefaultWalletNotFoundHandler(),
        });
    } catch {
        // MWA is Android-only; ignore on iOS/desktop.
    }
}

export function isMobileDevice(): boolean {
    if (typeof navigator === 'undefined') return false;
    return /android|iphone|ipad|ipod/i.test(navigator.userAgent);
}

export function isAndroid(): boolean {
    if (typeof navigator === 'undefined') return false;
    return /android/i.test(navigator.userAgent);
}

export function isIos(): boolean {
    if (typeof navigator === 'undefined') return false;
    const ua = navigator.userAgent.toLowerCase();
    return ua.includes('iphone') || ua.includes('ipad') || ua.includes('ipod');
}

export function getInjectedProvider(): SolanaProvider | null {
    const w = win();
    if (w.phantom?.solana?.isPhantom) return w.phantom.solana;
    if (w.solana?.isPhantom) return w.solana;
    if (w.solflare?.isSolflare) return w.solflare;
    return null;
}

export function hasInjectedWallet(): boolean {
    return getInjectedProvider() !== null;
}

/** Wait for Phantom/Solflare to inject (slow in in-app browsers). */
export function waitForInjectedWallet(ms = 4000): Promise<void> {
    return new Promise((resolve) => {
        if (hasInjectedWallet()) {
            resolve();
            return;
        }

        const deadline = Date.now() + ms;
        const tick = (): void => {
            if (hasInjectedWallet() || Date.now() >= deadline) {
                resolve();
                return;
            }
            window.setTimeout(tick, 150);
        };
        tick();
    });
}

function browseTarget(targetUrl = window.location.href): { url: string; ref: string } {
    return {
        url: encodeURIComponent(targetUrl),
        ref: encodeURIComponent(window.location.origin),
    };
}

/** Opens this page inside Phantom's in-app browser (required on iPhone Safari). */
export function phantomBrowseUrl(targetUrl = window.location.href): string {
    const { url, ref } = browseTarget(targetUrl);

    if (isAndroid()) {
        return `intent://ul/browse/${url}?ref=${ref}#Intent;scheme=https;host=phantom.app;package=app.phantom;end`;
    }

    return `https://phantom.app/ul/browse/${url}?ref=${ref}`;
}

/** Opens this page inside Solflare's in-app browser (iPhone + Android). */
export function solflareBrowseUrl(targetUrl = window.location.href): string {
    const { url, ref } = browseTarget(targetUrl);
    return `https://solflare.com/ul/v1/browse/${url}?ref=${ref}`;
}

export function walletAppBrowseUrl(walletId: 'phantom' | 'solflare', targetUrl = window.location.href): string {
    return walletId === 'phantom' ? phantomBrowseUrl(targetUrl) : solflareBrowseUrl(targetUrl);
}

export function openInPhantomBrowser(): void {
    window.location.assign(phantomBrowseUrl());
}

export function openInSolflareBrowser(): void {
    window.location.assign(solflareBrowseUrl());
}

/** iPhone/Android Safari/Chrome: no extension — must open inside a wallet app. */
export function needsWalletAppBrowser(): boolean {
    return isMobileDevice() && !hasInjectedWallet();
}

/** @deprecated use needsWalletAppBrowser */
export function needsPhantomBrowser(): boolean {
    return needsWalletAppBrowser();
}

/** Wallet apps to offer when mobile browser cannot inject a provider. */
export function walletAppLinks(): WalletAppLink[] {
    const target = window.location.href;
    const links: WalletAppLink[] = [
        {
            id: 'phantom',
            name: 'Phantom',
            label: isIos() ? 'Open in Phantom (iPhone)' : 'Open in Phantom',
            url: phantomBrowseUrl(target),
            icon: PHANTOM_ICON,
        },
        {
            id: 'solflare',
            name: 'Solflare',
            label: isIos() ? 'Open in Solflare (iPhone)' : 'Open in Solflare',
            url: solflareBrowseUrl(target),
            icon: SOLFLARE_ICON,
        },
    ];

    return links;
}

export function mobileWalletHint(): string {
    if (!needsWalletAppBrowser()) return '';

    if (isIos()) {
        return 'On iPhone, Safari cannot connect wallets directly. Tap a wallet below to open this page inside the app, then tap Connect Wallet again.';
    }

    return 'On mobile, open this page inside your wallet app, or pick a wallet below.';
}

export function isRedirectableAdapter(adapter: Adapter): boolean {
    if (adapter.readyState !== WalletReadyState.Loadable) return false;
    const name = adapter.name.toLowerCase();
    return name.includes('phantom') || (name.includes('solflare') && isIos());
}

export function redirectAdapterToApp(adapter: Adapter): void {
    const name = adapter.name.toLowerCase();
    if (name.includes('phantom')) {
        openInPhantomBrowser();
        return;
    }
    if (name.includes('solflare')) {
        openInSolflareBrowser();
    }
}
