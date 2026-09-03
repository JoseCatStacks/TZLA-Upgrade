// ── Shareable earnings card ──────────────────────────────────────────────────
// Renders a 1200×675 "Earnings Summary" PNG on a canvas: parchment frame, a
// selectable character, and the wallet's real weekly/monthly rewards from
// /api/staking/rewards/{wallet}. Exposes the inline tile refresh/clear hooks
// to staking.ts and the modal/copy/save handlers to the Blade view.

type ToastFn = (msg: string, type?: 'success' | 'error' | 'info') => void;

interface EarningsData {
    wallet: string;
    weekTzla: number;
    monthTzla: number;
    weekUsd: number | null;
    monthUsd: number | null;
    websiteUrl: string;
}

const CARD_W = 1200;
const CARD_H = 675;

const COLORS = {
    ink: '#2a1f14',
    inkMuted: '#5c4a38',
    green: '#2d7a4f',
};

const LAYOUT = {
    charTopInset: 12,
    charBottomInset: 0,
    contentX: 442 + 36,
    contentW: 600,
};

const DEFAULT_CHARACTER_INDEX = 14; // TZLA_15.png

let getWallet: () => string | null = () => null;
let toast: ToastFn = () => {};

let earningsData: EarningsData | null = null;
let currentCharacterUrl: string | null = null;
let currentCharacterIndex = DEFAULT_CHARACTER_INDEX;
let frameImageCache: (HTMLImageElement & { _src?: string }) | null = null;
let canvas: HTMLCanvasElement | null = null;
let ctx: CanvasRenderingContext2D | null = null;

function $(id: string): HTMLElement | null {
    return document.getElementById(id);
}

function characterUrls(): string[] {
    return ((window as any).STAKING_CARD_BACKGROUNDS as string[] | undefined) ?? [];
}

function fmt(n: number): string {
    return Number(n).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function loadImage(url: string): Promise<HTMLImageElement> {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.crossOrigin = 'anonymous';
        img.onload = () => resolve(img);
        img.onerror = () => reject(new Error(`Failed to load image: ${url}`));
        img.src = url;
    });
}

async function ensureFonts(): Promise<void> {
    if (!document.fonts) return;
    await Promise.all([
        document.fonts.load('700 62px "Cormorant SC"'),
        document.fonts.load('600 32px "Cormorant SC"'),
        document.fonts.load('700 60px "Cormorant SC"'),
        document.fonts.load('600 28px "Cormorant SC"'),
        document.fonts.load('600 24px "Cormorant SC"'),
        document.fonts.load('400 24px "Spectral"'),
    ]).catch(() => {});
}

async function getFrameImage(): Promise<HTMLImageElement> {
    const url = ((window as any).STAKING_CARD_FRAME as string | undefined)
        ?? '/images/staking-card/card-frame.png';
    if (!frameImageCache || frameImageCache._src !== url) {
        frameImageCache = await loadImage(url);
        frameImageCache._src = url;
    }
    return frameImageCache;
}

// ── Canvas drawing ────────────────────────────────────────────────────────────

function drawCharacterPanel(c: CanvasRenderingContext2D, img: HTMLImageElement | null): void {
    if (!img) return;
    const zoneH = CARD_H - LAYOUT.charTopInset - LAYOUT.charBottomInset;
    const scale = zoneH / img.height;
    const sw = img.width * scale;
    const sh = img.height * scale;
    c.drawImage(img, 0, CARD_H - LAYOUT.charBottomInset - sh, sw, sh);
}

function drawHeader(c: CanvasRenderingContext2D): void {
    const { contentX, contentW } = LAYOUT;

    c.textAlign = 'center';
    c.fillStyle = COLORS.ink;
    c.font = '700 62px "Cormorant SC", Georgia, serif';
    c.fillText('EARNINGS SUMMARY', contentX + contentW / 2, 94);

    c.font = 'italic 400 24px "Spectral", Georgia, serif';
    c.fillStyle = COLORS.inkMuted;
    c.fillText('Your staking earnings at a glance', contentX + contentW / 2, 128);

    const lineY = 110;
    c.strokeStyle = 'rgba(92,61,30,0.35)';
    c.lineWidth = 1;
    c.beginPath();
    c.moveTo(contentX + 12, lineY);
    c.lineTo(contentX + contentW / 2 - 220, lineY);
    c.moveTo(contentX + contentW / 2 + 220, lineY);
    c.lineTo(contentX + contentW - 12, lineY);
    c.stroke();

    c.textAlign = 'left';
}

function drawEarningsBlock(
    c: CanvasRenderingContext2D,
    opts: { x: number; y: number; w: number; title: string; tzla: number; usd: number | null },
): void {
    const { x, y, w, title, tzla, usd } = opts;

    c.textAlign = 'center';
    c.fillStyle = COLORS.ink;
    c.font = '600 32px "Cormorant SC", Georgia, serif';
    c.fillText(title, x + w / 2, y);

    c.fillStyle = COLORS.green;
    c.font = '700 60px "Cormorant SC", Georgia, serif';
    c.fillText(`+${fmt(tzla)} TZLA`, x + w / 2, y + 66);

    // USD line only when the price oracle returned a value.
    if (usd !== null) {
        c.fillStyle = COLORS.inkMuted;
        c.font = '600 28px "Cormorant SC", Georgia, serif';
        c.fillText(`≈ $${fmt(usd)} USD`, x + w / 2, y + 108);
    }
}

function drawFooterUrl(c: CanvasRenderingContext2D, url: string): void {
    const { contentX, contentW } = LAYOUT;
    const clean = (url || 'tzlaonsol.xyz/portal/staking').replace(/^https?:\/\//, '').toUpperCase();

    c.fillStyle = COLORS.ink;
    c.font = '700 22px "Cormorant SC", Georgia, serif';
    c.textAlign = 'center';
    c.fillText(clean, contentX + contentW / 2, CARD_H - 40);
    c.textAlign = 'left';
}

async function renderCard(data: EarningsData, characterUrl: string | null): Promise<void> {
    if (!ensureCanvas() || !ctx || !canvas) return;

    await ensureFonts();
    ctx.clearRect(0, 0, CARD_W, CARD_H);

    const frameImg = await getFrameImage();
    ctx.drawImage(frameImg, 0, 0, CARD_W, CARD_H);

    let characterImg: HTMLImageElement | null = null;
    if (characterUrl) {
        try {
            characterImg = await loadImage(characterUrl);
        } catch (e) {
            console.warn(e);
        }
    }

    drawHeader(ctx);

    const { contentX, contentW } = LAYOUT;
    const statsTop = 158;
    const statsBottom = CARD_H - 88;
    const blockH = 108;
    const gap = (statsBottom - statsTop - blockH * 2) / 3;
    const weeklyY = statsTop + gap;
    const monthlyY = weeklyY + blockH + gap;
    const dividerY = weeklyY + blockH + gap / 2;

    drawEarningsBlock(ctx, {
        x: contentX, y: weeklyY, w: contentW,
        title: 'WEEKLY EARNINGS', tzla: data.weekTzla, usd: data.weekUsd,
    });

    ctx.strokeStyle = 'rgba(92,61,30,0.3)';
    ctx.lineWidth = 1;
    ctx.beginPath();
    ctx.moveTo(contentX + 48, dividerY);
    ctx.lineTo(contentX + contentW - 48, dividerY);
    ctx.stroke();

    drawEarningsBlock(ctx, {
        x: contentX, y: monthlyY, w: contentW,
        title: 'MONTHLY EARNINGS', tzla: data.monthTzla, usd: data.monthUsd,
    });

    drawFooterUrl(ctx, data.websiteUrl);

    // Draw character last so it layers on top and can extend into the stats area.
    drawCharacterPanel(ctx, characterImg);

    const dataUrl = canvas.toDataURL('image/png');
    ['stakingCardPreview', 'earningsSummaryPreview'].forEach(id => {
        const preview = $(id) as HTMLImageElement | null;
        if (preview) {
            preview.src = dataUrl;
            preview.style.display = 'block';
            preview.classList.add('is-visible');
        }
    });
    canvas.style.display = 'none';
}

// ── Earnings data ─────────────────────────────────────────────────────────────

async function fetchEarnings(wallet: string): Promise<EarningsData> {
    const res = await fetch(`/api/staking/rewards/${encodeURIComponent(wallet)}`, {
        headers: { Accept: 'application/json' },
    });
    if (!res.ok) throw new Error('Failed to load earnings');
    const json = await res.json();

    return {
        wallet,
        weekTzla:   Number(json?.weekly?.reward_tzla ?? 0),
        monthTzla:  Number(json?.monthly?.reward_tzla ?? 0),
        weekUsd:    json?.weekly?.reward_usd ?? null,
        monthUsd:   json?.monthly?.reward_usd ?? null,
        websiteUrl: 'tzlaonsol.xyz/portal/staking',
    };
}

// ── Character picker ──────────────────────────────────────────────────────────

function isModalOpen(): boolean {
    const modal = $('stakingCardModal');
    return !!modal && modal.classList.contains('open');
}

function updateCharacterThumbActive(): void {
    document.querySelectorAll('.card-bg-thumb').forEach((el, i) => {
        el.classList.toggle('active', i === currentCharacterIndex);
    });
}

function selectCharacterByIndex(index: number): void {
    const characters = characterUrls();
    if (!characters.length) return;

    currentCharacterIndex = ((index % characters.length) + characters.length) % characters.length;
    currentCharacterUrl = characters[currentCharacterIndex];
    updateCharacterThumbActive();

    if (earningsData) {
        renderCard(earningsData, currentCharacterUrl);
    }
}

function getActiveCharacterUrl(): string {
    const characters = characterUrls();
    if (characters.length) {
        return characters[currentCharacterIndex] || characters[0];
    }
    return currentCharacterUrl || '';
}

function buildCharacterPicker(): void {
    const container = $('cardBgPicker');
    if (!container) return;
    container.innerHTML = '';

    const label = document.createElement('span');
    label.className = 'card-picker-label';
    label.textContent = 'Character';
    container.appendChild(label);

    const hint = document.createElement('span');
    hint.className = 'card-picker-hint';
    hint.textContent = 'Use ← → arrow keys to browse';
    container.appendChild(hint);

    const thumbs = document.createElement('div');
    thumbs.className = 'card-bg-thumbs-row';
    container.appendChild(thumbs);

    characterUrls().forEach((url, i) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'card-bg-thumb' + (i === currentCharacterIndex ? ' active' : '');
        btn.style.backgroundImage = `url(${url})`;
        btn.setAttribute('aria-label', `Character ${i + 1}`);
        btn.addEventListener('click', () => selectCharacterByIndex(i));
        thumbs.appendChild(btn);
    });
}

// ── Inline tile state ─────────────────────────────────────────────────────────

function setEarningsSummaryLoading(isLoading: boolean): void {
    $('earningsSummaryLoading')?.classList.toggle('is-visible', isLoading);
}

function setEarningsSummaryConnected(isConnected: boolean): void {
    const empty = $('earningsSummaryEmpty');
    const body = $('earningsSummaryBody');
    if (empty) empty.style.display = isConnected ? 'none' : 'block';
    if (body) body.classList.toggle('is-visible', isConnected);
    if (!isConnected) {
        const preview = $('earningsSummaryPreview') as HTMLImageElement | null;
        if (preview) {
            preview.removeAttribute('src');
            preview.style.display = 'none';
            preview.classList.remove('is-visible');
        }
        setEarningsSummaryLoading(false);
        earningsData = null;
    }
}

function ensureCanvas(): boolean {
    if (!canvas) {
        canvas = $('stakingCardCanvas') as HTMLCanvasElement | null;
        if (canvas) {
            canvas.width = CARD_W;
            canvas.height = CARD_H;
            ctx = canvas.getContext('2d');
        }
    }
    return !!ctx;
}

function ensureDefaultCharacter(): void {
    const characters = characterUrls();
    if (!characters.length) return;
    if (currentCharacterUrl && characters.indexOf(currentCharacterUrl) >= 0) {
        currentCharacterIndex = characters.indexOf(currentCharacterUrl);
        return;
    }
    currentCharacterIndex = Math.min(DEFAULT_CHARACTER_INDEX, characters.length - 1);
    currentCharacterUrl = characters[currentCharacterIndex];
}

async function ensureEarningsCard(): Promise<EarningsData | null> {
    const wallet = getWallet();

    if (!wallet) {
        setEarningsSummaryConnected(false);
        return null;
    }

    setEarningsSummaryConnected(true);
    setEarningsSummaryLoading(true);

    try {
        ensureCanvas();
        ensureDefaultCharacter();
        if (!earningsData || earningsData.wallet !== wallet) {
            earningsData = await fetchEarnings(wallet);
        }
        await renderCard(earningsData, getActiveCharacterUrl());
        return earningsData;
    } catch (e: any) {
        toast(e?.message || 'Failed to generate card', 'error');
        setEarningsSummaryConnected(false);
        return null;
    } finally {
        setEarningsSummaryLoading(false);
    }
}

// ── Hooks for staking.ts ──────────────────────────────────────────────────────

export function refreshEarningsSummaryCard(force = false): Promise<EarningsData | null> {
    if (force) earningsData = null;
    return ensureEarningsCard();
}

export function clearEarningsSummaryCard(): void {
    setEarningsSummaryConnected(false);
    if (isModalOpen()) closeStakingCardModal();
}

// ── Modal / actions (wired to Blade onclick handlers) ────────────────────────

async function openStakingCardModal(): Promise<void> {
    const wallet = getWallet();
    if (!wallet) {
        toast('Connect wallet first', 'error');
        return;
    }

    const modal = $('stakingCardModal');
    const loading = $('cardModalLoading');
    if (!modal) return;

    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';

    if (loading) loading.style.display = 'flex';
    const preview = $('stakingCardPreview');
    if (preview) preview.style.display = 'none';

    try {
        ensureCanvas();
        ensureDefaultCharacter();
        if (!earningsData || earningsData.wallet !== wallet) {
            earningsData = await fetchEarnings(wallet);
        }
        buildCharacterPicker();
        await renderCard(earningsData, getActiveCharacterUrl());
    } catch (e: any) {
        toast(e?.message || 'Failed to generate card', 'error');
        closeStakingCardModal();
    } finally {
        if (loading) loading.style.display = 'none';
    }
}

function closeStakingCardModal(): void {
    const modal = $('stakingCardModal');
    if (modal) {
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
    }
    document.body.style.overflow = '';
}

async function copyCard(): Promise<void> {
    if (!getWallet()) {
        toast('Connect wallet first', 'error');
        return;
    }
    if (!canvas || !earningsData) {
        await ensureEarningsCard();
    }
    if (!canvas) return;
    try {
        const blob = await new Promise<Blob | null>(resolve => {
            canvas!.toBlob(resolve, 'image/png');
        });
        if (!blob) throw new Error('Could not create image');
        await navigator.clipboard.write([new ClipboardItem({ 'image/png': blob })]);
        toast('Copied to clipboard', 'success');
    } catch (e) {
        console.error(e);
        toast('Copy not supported — use Save instead', 'error');
    }
}

async function saveCard(): Promise<void> {
    if (!getWallet()) {
        toast('Connect wallet first', 'error');
        return;
    }
    if (!canvas || !earningsData) {
        await ensureEarningsCard();
    }
    if (!canvas) return;
    canvas.toBlob(blob => {
        if (!blob) return;
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'tzla-staking-earnings.png';
        a.click();
        URL.revokeObjectURL(url);
        toast('Image saved', 'success');
    }, 'image/png');
}

// ── Init ──────────────────────────────────────────────────────────────────────

export function initStakingCard(walletGetter: () => string | null, toastFn: ToastFn): void {
    getWallet = walletGetter;
    toast = toastFn;

    (window as any).openStakingCardModal = openStakingCardModal;
    (window as any).closeStakingCardModal = closeStakingCardModal;
    (window as any).copyCard = copyCard;
    (window as any).saveCard = saveCard;

    ensureCanvas();
    setEarningsSummaryConnected(false);

    const modal = $('stakingCardModal');
    modal?.addEventListener('click', e => {
        const target = e.target as HTMLElement;
        if (target === modal || target.classList.contains('card-modal-backdrop')) {
            closeStakingCardModal();
        }
    });

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            closeStakingCardModal();
            return;
        }
        if (!isModalOpen()) return;
        if (e.key === 'ArrowLeft') {
            e.preventDefault();
            selectCharacterByIndex(currentCharacterIndex - 1);
        } else if (e.key === 'ArrowRight') {
            e.preventDefault();
            selectCharacterByIndex(currentCharacterIndex + 1);
        }
    });

    ensureDefaultCharacter();

    if (getWallet()) {
        ensureEarningsCard();
    }
}
