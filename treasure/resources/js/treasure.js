import { payGuessFee, PaymentError, normalizeSignature } from './payment.js';
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
} from './wallets.ts';
import {
    isIos,
    isMobileDevice,
    isRedirectableAdapter,
    mobileWalletHint,
    needsWalletAppBrowser,
    redirectAdapterToApp,
    waitForInjectedWallet,
    walletAppLinks,
} from './mobile-wallet.ts';

const state = {
    wallet: null,
    attemptsPerWord: 0,
    weeks: [],
    config: null,
    /** @type {import('@solana/wallet-adapter-base').Adapter | null} */
    adapter: null,
};

const $ = (sel, root = document) => root.querySelector(sel);
const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

function csrfToken() {
    const m = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return m ? decodeURIComponent(m[1]) : '';
}

async function api(method, path, body) {
    const opts = {
        method,
        credentials: 'same-origin',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    };
    const token = csrfToken();
    if (token) opts.headers['X-XSRF-TOKEN'] = token;
    if (body !== undefined) opts.body = JSON.stringify(body);
    const res = await fetch(path, opts);
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        // Prefer the human-readable message the API provides; `error` is a code.
        const message = data.message
            || (data.errors && Object.values(data.errors).flat()[0])
            || data.error
            || res.statusText;
        throw Object.assign(new Error(message), { status: res.status, code: data.error, data });
    }
    return data;
}

function shortAddr(addr) {
    return addr && addr.length > 10 ? `${addr.slice(0, 4)}…${addr.slice(-4)}` : addr || '';
}

function renderConnect() {
    const box = $('#tzla-connect');
    if (!box) return;
    if (state.wallet) {
        box.innerHTML = `
            <div class="wallet-pill">
                <span class="wallet-dot" title="TZLA holder: ${state.wallet.holds_tzla ? 'yes' : 'no'}"></span>
                <span class="wallet-addr">${shortAddr(state.wallet.address)}</span>
                <span class="wallet-att">${state.attemptsPerWord} tries/week</span>
                <button type="button" class="wallet-disc" data-action="disconnect">×</button>
            </div>
            <button type="button" class="htp-btn" data-action="open-htp" aria-label="How to Play">
                <img src="/storage/weekpaper.png" alt="" />
                <span>How to Play</span>
            </button>
            <button type="button" class="htp-btn" data-action="open-prizes" aria-label="Prizes">
                <img src="/storage/weekpaper.png" alt="" />
                <span>Prizes</span>
            </button>`;
    } else {
        box.innerHTML = `
            <button type="button" class="wallet-connect" data-action="connect" aria-label="Connect Wallet"><img src="/storage/connectbtn.png" alt="Connect Wallet" /></button>
            <button type="button" class="htp-btn" data-action="open-prizes" aria-label="Prizes">
                <img src="/storage/weekpaper.png" alt="" />
                <span>Prizes</span>
            </button>`;
    }
}

function openHowToPlay() {
    const dlg = $('#tzla-htp');
    if (!dlg) return;

    const w = state.wallet;
    const hasGolden = w && (w.golden_ticket_count || 0) > 0;
    const hasNft    = w && (w.nft_count || 0) > 0;
    const hasTzla   = w && w.holds_tzla;
    const canPlay   = w && (w.can_play ?? (hasGolden || hasNft || hasTzla));

    $$('.htp-tier', dlg).forEach(tier => {
        tier.classList.remove('eligible', 'ineligible');
        const badge = tier.querySelector('.htp-eligible-badge');
        if (badge) badge.remove();
    });

    function mark(selector, isEligible) {
        const el = $(`[data-tier="${selector}"]`, dlg);
        if (!el) return;
        if (isEligible) {
            el.classList.add('eligible');
            el.insertAdjacentHTML('beforeend', '<span class="htp-eligible-badge">✓ Qualified</span>');
        } else {
            el.classList.add('ineligible');
        }
    }

    if (w) {
        mark('golden', hasGolden);
        mark('nft',    hasNft);
        mark('token',  hasTzla);
        const note = $('#htp-not-eligible', dlg);
        if (note) note.style.display = canPlay ? 'none' : '';
    }

    dlg.showModal();
}

let prizesWeeksData = [];

async function openPrizes() {
    const dlg = $('#tzla-prizes');
    if (!dlg) return;
    dlg.showModal();

    const list = $('#prizes-list', dlg);
    list.innerHTML = '<p class="prizes-empty">Loading the ledger…</p>';

    try {
        const data = await api('GET', '/api/weeks');
        const weeks = (data.weeks || []).filter(w => w.is_active);
        prizesWeeksData = weeks;

        if (weeks.length === 0) {
            list.innerHTML = '<p class="prizes-empty">No weeks have been revealed yet.</p>';
            return;
        }

        list.innerHTML = weeks.map(w => {
            const reward = w.reward_description
                ? `<em>${escape(w.reward_description)}</em>`
                : '<span style="opacity:.55">To be announced</span>';
            const badge = w.reward_claimed
                ? '<span class="prizes-badge claimed">✓ Claimed</span>'
                : '<span class="prizes-badge unclaimed">Unclaimed</span>';
            const title = w.title ? `<div class="prizes-week-title">${escape(w.title)}</div>` : '';
            return `
                <div class="prizes-week" data-week="${w.number}" style="cursor:pointer">
                    <span class="prizes-week-num">${w.number}</span>
                    <div class="prizes-week-body">
                        ${title}
                        <div class="prizes-week-reward">${reward}</div>
                    </div>
                    ${badge}
                </div>`;
        }).join('');
    } catch {
        list.innerHTML = '<p class="prizes-empty" style="color:var(--blood)">Could not load prizes.</p>';
    }
}

function openRewardDetail(weekNumber) {
    const w = prizesWeeksData.find(x => x.number === weekNumber);
    if (!w) return;
    const dlg = $('#tzla-reward-detail');
    if (!dlg) return;

    const content = $('#reward-detail-content', dlg);
    const reward = w.reward_description
        ? `<em>${escape(w.reward_description)}</em>`
        : '<span style="opacity:.55">To be announced</span>';
    const badge = w.reward_claimed
        ? '<span class="prizes-badge claimed">✓ Claimed</span>'
        : '<span class="prizes-badge unclaimed">Unclaimed</span>';

    content.innerHTML = `
        <div class="reward-detail-week-num">Week ${w.number}</div>
        ${w.title ? `<div class="prizes-week-title reward-detail-title">${escape(w.title)}</div>` : ''}
        <div class="prizes-week-reward reward-detail-reward">${reward}</div>
        <div class="reward-detail-badge">${badge}</div>
    `;

    dlg.showModal();
}

function applyTint(el, w) {
    el.classList.remove('is-locked', 'is-partial', 'is-complete');
    if (!w) return;
    if (!w.is_unlocked) el.classList.add('is-locked');
    else if (w.week_complete || (w.total_words > 0 && w.solved_word_count >= w.total_words)) el.classList.add('is-complete');
}

function renderWeekTints() {
    $$('.weekpaper[data-week]').forEach(el => {
        const wn = parseInt(el.dataset.week, 10);
        applyTint(el, state.weeks.find(w => w.number === wn));
    });
}

async function refreshMe() {
    try {
        const data = await api('GET', '/api/auth/me');
        state.wallet = data.wallet;
        state.attemptsPerWord = data.attempts_per_week || data.attempts_per_word || 0;
    } catch { state.wallet = null; }
    renderConnect();
}

async function refreshConfig() {
    try {
        state.config = await api('GET', '/api/game-config');
    } catch {
        state.config = null;
    }
}

/** SOL owed for the connected wallet's tier (from game-config). */
function currentFeeSol() {
    if (!state.config) return null;
    return state.config.your_fee_sol
        ?? state.config.fees?.standard_sol
        ?? null;
}

async function refreshWeeks() {
    try {
        const data = await api('GET', '/api/weeks');
        state.weeks = data.weeks || [];
    } catch { state.weeks = []; }
    renderWeekTints();
}

function bindAdapter(adapter) {
    state.adapter?.off('disconnect', onAdapterDisconnect);
    state.adapter = adapter;
    adapter.on('disconnect', onAdapterDisconnect);
}

function onAdapterDisconnect() {
    state.adapter = null;
}

function fillWalletModalList() {
    const list = $('#walletModalList');
    const hint = $('#walletModalHint');
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

        const status = document.createElement('span');
        status.className = 'wallet-row-state';
        status.textContent = walletStatusLabel(adapter);
        row.appendChild(status);

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
            else alert(`Install ${adapter.name}, then refresh this page.`);
        });
        list.appendChild(row);
    }
}

function openWalletModal() {
    const modal = $('#walletModal');
    const list = $('#walletModalList');
    if (!modal || !list) {
        alert('Wallet picker is missing. Refresh the page.');
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

function closeWalletModal() {
    const modal = $('#walletModal');
    if (!modal) return;
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('wallet-modal-open');
}

async function connectAdapter(adapter) {
    closeWalletModal();
    try {
        bindAdapter(adapter);
        await adapter.connect();
        const publicKey = adapter.publicKey;
        if (!publicKey) throw new Error(`${adapter.name} did not return a public key.`);
        rememberWallet(adapter.name);
        await authenticateWallet(publicKey.toBase58(), adapter);
    } catch (e) {
        adapter.off('disconnect', onAdapterDisconnect);
        if (state.adapter === adapter) state.adapter = null;
        const msg = String(e?.message ?? e);
        if (/reject|cancel/i.test(msg)) {
            showToast('Connection cancelled.');
        } else {
            console.error(e);
            alert('Wallet connect failed: ' + msg);
        }
    }
}

/**
 * Prove wallet ownership to the backend (SIWS-style nonce + signature).
 * @param {string} address
 * @param {import('@solana/wallet-adapter-base').Adapter} adapter
 */
async function authenticateWallet(address, adapter) {
    if (typeof adapter.signMessage !== 'function') {
        throw new Error(`${adapter.name} cannot sign messages. Try Phantom or Solflare.`);
    }

    const { nonce, message } = await api('POST', '/api/auth/nonce', { address });
    const encoded = new TextEncoder().encode(message);
    const signatureBytes = await adapter.signMessage(encoded);
    const sigBase58 = toBase58(signatureBytes);
    const verify = await api('POST', '/api/auth/verify', {
        address, nonce, signature: sigBase58,
    });
    state.wallet = verify.wallet;
    state.attemptsPerWord = verify.attempts_per_week || verify.attempts_per_word || 0;
    renderConnect();
    await Promise.all([refreshConfig(), refreshWeeks()]);
}

function startConnect() {
    openWalletModal();
}

async function disconnectWallet() {
    try { await api('POST', '/api/auth/logout'); } catch {}
    forgetWallet();
    const adapter = state.adapter;
    try { await adapter?.disconnect(); } catch {}
    onAdapterDisconnect();
    state.wallet = null;
    state.attemptsPerWord = 0;
    renderConnect();
    await refreshWeeks();
}

/** Reattach the previously chosen wallet after reload (silent — no unlock prompts). */
async function restoreAdapterSession() {
    const saved = lastWalletName();
    if (!saved) return;

    const adapter = listWalletAdapters().find((item) => item.name === saved && isInstalled(item));
    if (!adapter) return;

    try {
        bindAdapter(adapter);
        await adapter.autoConnect();
        const pk = adapter.publicKey?.toBase58();
        if (!pk) {
            adapter.off('disconnect', onAdapterDisconnect);
            if (state.adapter === adapter) state.adapter = null;
            return;
        }
        // Session wallet and extension wallet must match; otherwise leave signing disconnected.
        if (state.wallet?.address && pk !== state.wallet.address) {
            await adapter.disconnect().catch(() => {});
            onAdapterDisconnect();
            return;
        }
    } catch {
        adapter.off('disconnect', onAdapterDisconnect);
        if (state.adapter === adapter) state.adapter = null;
    }
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

function openPopupForWeek(weekNumber) {
    const dlg = $('#tzla-popup');
    if (!dlg) return;
    const week = state.weeks.find(w => w.number === weekNumber);
    if (!week || !week.is_active) return;
    if (!week.is_unlocked) {
        showToast(`Week ${weekNumber} unlocks ${new Date(week.starts_at).toLocaleString()}`);
        return;
    }
    // Force scrollside animation to replay from scratch each open
    const scrollside = $('#tzla-scrollside');
    if (scrollside) {
        scrollside.style.animation = 'none';
        void scrollside.offsetHeight; // trigger reflow
        scrollside.style.animation = '';
    }
    dlg.dataset.week = String(weekNumber);
    dlg.showModal();
    loadWeekIntoPopup(weekNumber);
}

function showToast(text) {
    const t = $('#tzla-toast');
    if (!t) return;
    t.textContent = text;
    t.classList.add('show');
    clearTimeout(showToast._t);
    showToast._t = setTimeout(() => t.classList.remove('show'), 3200);
}

async function loadWeekIntoPopup(weekNumber) {
    const body = $('#tzla-popup-body');
    body.innerHTML = '<p class="tzla-loading">Unrolling the parchment…</p>';
    try {
        const w = await api('GET', `/api/weeks/${weekNumber}`);
        renderWeekInPopup(w);
    } catch (e) {
        if (e.status === 403) body.innerHTML = `<p class="tzla-err">Week locked.</p>`;
        else body.innerHTML = `<p class="tzla-err">${e.message}</p>`;
    }
}

function renderWeekInPopup(week) {
    const body = $('#tzla-popup-body');
    const connected = week.wallet_connected;
    const eligible = connected && state.wallet && state.wallet.can_play !== false;
    const complete = !!week.week_complete;
    const attemptsLeft = week.attempts_left ?? 0;
    const canSubmit = eligible && !complete && attemptsLeft > 0;

    const fee = currentFeeSol();
    const paymentsOn = state.config ? state.config.payments_enabled !== false : true;
    const tier = state.config?.your_fee_tier ? ` (${state.config.your_fee_tier})` : '';
    const feeNote = (canSubmit && fee !== null && paymentsOn)
        ? `<div class="tzla-note">Each attempt costs <strong>${formatSol(fee)} SOL</strong>${escape(tier)}. Fill all ${week.total_words || week.words.length} answers, then submit once. Ye only learn how many hit — not which.</div>`
        : '';

    const ineligibleNote = (connected && !eligible)
        ? '<div class="tzla-note">This wallet is not eligible to play. Hold TZLA, stake TZLA, hold an NFT, or a Golden Ticket.</div>'
        : '';

    const attemptsNote = connected && eligible && !complete
        ? `<div class="tzla-note">${attemptsLeft}/${week.attempts_allowed ?? 0} attempts left this week.</div>`
        : '';

    const profile = canSubmit ? `
        <div class="tzla-profile">
            <input type="text" id="tzla-username" maxlength="64" placeholder="Display name (optional)"
                   value="${escape(state.wallet?.username || '')}" autocomplete="off" />
            <input type="text" id="tzla-payout" maxlength="64" placeholder="Payout wallet (optional)"
                   value="${escape(state.wallet?.payout_address || '')}" autocomplete="off" />
        </div>` : '';

    const rows = (week.words || []).map(word => {
        if (complete && word.solved_answer) {
            return `
                <div class="tzla-word">
                    <div class="tzla-word-head">
                        <span class="tzla-word-n">Word ${word.position}</span>
                        <span class="tzla-ok">Solved: ${escape(word.solved_answer)}</span>
                    </div>
                    <div class="tzla-hint">${word.hint ? escape(word.hint) : '<em>No hint provided.</em>'}</div>
                </div>`;
        }
        return `
            <div class="tzla-word" data-pos="${word.position}">
                <div class="tzla-word-head">
                    <span class="tzla-word-n">Word ${word.position}</span>
                </div>
                <div class="tzla-hint">${word.hint ? escape(word.hint) : '<em>No hint provided.</em>'}</div>
                <div class="tzla-guess-row">
                    <input type="text" name="answer-${word.position}" data-pos="${word.position}"
                           placeholder="Yer answer…" ${canSubmit ? '' : 'disabled'} autocomplete="off" />
                </div>
            </div>`;
    }).join('');

    body.innerHTML = `
        ${week.reward_description ? `<div class="tzla-reward">Reward: <em>${escape(week.reward_description)}</em></div>` : ''}
        ${!connected ? '<div class="tzla-note">Connect a Solana wallet to submit a bundle.</div>' : ''}
        ${ineligibleNote}
        ${attemptsNote}
        ${feeNote}
        ${profile}
        <form class="tzla-bundle" data-week="${week.number}">
            <div class="tzla-words">${rows}</div>
            ${canSubmit ? `
                <div class="tzla-guess-row" style="justify-content:center;margin-top:.8em">
                    <button type="submit" aria-label="Submit bundle"><img src="/storage/submitbtn.png" alt="Submit" /></button>
                </div>
                <div class="tzla-word-status" id="tzla-bundle-status"></div>
            ` : ''}
        </form>
        ${complete ? '<div class="tzla-done">🏴‍☠️ Week cleared. Yer name is on the bounty board — prize paid by hand.</div>' : ''}
    `;

    const form = $('form.tzla-bundle', body);
    if (form) {
        form.addEventListener('submit', onBundleSubmit);
        $$('input[data-pos]', form).forEach(input => {
            input.addEventListener('input', () => updateBundleSubmitEnabled(form));
        });
        updateBundleSubmitEnabled(form);
    }
}

function updateBundleSubmitEnabled(form) {
    const btn = form.querySelector('button[type=submit]');
    if (!btn) return;
    const inputs = $$('input[data-pos]', form);
    const allFilled = inputs.length > 0 && inputs.every(i => (i.value || '').trim() !== '');
    btn.disabled = !allFilled;
}

const sleep = ms => new Promise(r => setTimeout(r, ms));

function formatSol(amount) {
    if (amount === null || amount === undefined) return '';
    return String(parseFloat(Number(amount).toFixed(9)));
}

function profileFields() {
    const username = ($('#tzla-username')?.value || '').trim();
    const payout = ($('#tzla-payout')?.value || '').trim();
    const out = {};
    if (username) out.username = username;
    if (payout) out.payout_address = payout;
    return out;
}

/**
 * Pays the per-bundle fee. Reuses a pending signature if a prior submit failed
 * after the wallet already paid.
 */
async function obtainFeeSignature(status, weekNumber) {
    if (!state.config) await refreshConfig();

    if (state.config && state.config.payments_enabled === false) {
        return `dev-${Date.now()}-${Math.random().toString(36).slice(2)}`;
    }
    if (!state.config) {
        throw new PaymentError('Could not load payment settings. Try reloading.');
    }

    const pending = readPendingFee(weekNumber, 0);
    if (pending) {
        status.className = 'tzla-word-status';
        status.textContent = 'Reusing your previous payment (no new charge)…';
        return pending;
    }

    const amountSol = currentFeeSol();
    status.className = 'tzla-word-status';
    status.textContent = `Approve ${formatSol(amountSol)} SOL in your wallet…`;

    if (!state.adapter?.connected) {
        throw new PaymentError('Wallet disconnected. Connect again, then retry.');
    }

    const signature = await payGuessFee({
        treasury: state.config.treasury_address,
        amountSol,
        from: state.wallet.address,
        fetchBlockhash: () => api('GET', '/api/solana/blockhash'),
        broadcastTransaction: (transaction) => api('POST', '/api/solana/send', { transaction }),
        adapter: state.adapter,
    });

    const normalized = normalizeSignature(signature);
    if (!normalized) {
        throw new PaymentError('Payment returned an unusable signature. Check your wallet before retrying.');
    }

    writePendingFee(weekNumber, 0, normalized);
    return normalized;
}

const PENDING_FEE_KEY = 'tzla:pending-fee';

function readPendingFee(weekNumber, position) {
    try {
        const raw = sessionStorage.getItem(PENDING_FEE_KEY);
        if (!raw) return null;
        const data = JSON.parse(raw);
        if (!data?.signature || data.week !== weekNumber || data.position !== position) return null;
        // Fee verifier rejects txs older than ~1 hour; stop reusing before that.
        if (Date.now() - (data.ts || 0) > 50 * 60 * 1000) {
            sessionStorage.removeItem(PENDING_FEE_KEY);
            return null;
        }
        return data.signature;
    } catch {
        return null;
    }
}

function writePendingFee(weekNumber, position, signature) {
    try {
        sessionStorage.setItem(PENDING_FEE_KEY, JSON.stringify({
            week: weekNumber,
            position,
            signature,
            ts: Date.now(),
        }));
    } catch {
        // private mode
    }
}

function clearPendingFee() {
    try { sessionStorage.removeItem(PENDING_FEE_KEY); } catch {}
}

/**
 * A freshly sent transaction may not be confirmed by the time the backend looks
 * it up, so retry that specific failure. Retrying is safe: the signature is only
 * consumed once verification actually succeeds.
 */
async function submitBundleWithRetry(weekNumber, answers, feeSignature, status) {
    const payload = { answers, fee_signature: feeSignature, ...profileFields() };
    const maxAttempts = 6;

    for (let attempt = 1; attempt <= maxAttempts; attempt++) {
        try {
            return await api('POST', `/api/weeks/${weekNumber}/bundle`, payload);
        } catch (err) {
            const awaitingConfirmation = err.status === 402 && err.code === 'invalid_fee_payment';
            if (!awaitingConfirmation || attempt === maxAttempts) throw err;

            status.className = 'tzla-word-status';
            status.textContent = `Waiting for payment confirmation… (${attempt}/${maxAttempts})`;
            await sleep(2500);
        }
    }
}

async function onBundleSubmit(e) {
    e.preventDefault();
    const form = e.currentTarget;
    const weekNumber = parseInt(form.dataset.week, 10);
    const button = form.querySelector('button[type=submit]');
    const status = $('#tzla-bundle-status') || form.querySelector('.tzla-word-status');
    const inputs = $$('input[data-pos]', form);
    const answers = inputs.map(i => (i.value || '').trim());

    if (!answers.length || answers.some(a => !a)) {
        if (status) {
            status.className = 'tzla-word-status tzla-bad';
            status.textContent = 'Fill every answer before submitting.';
        }
        return;
    }

    if (!state.wallet) {
        status.className = 'tzla-word-status tzla-bad';
        status.textContent = 'Connect wallet first.';
        return;
    }
    if (state.wallet.can_play === false) {
        status.className = 'tzla-word-status tzla-bad';
        status.textContent = 'This wallet is not eligible to play.';
        return;
    }

    inputs.forEach(i => { i.disabled = true; });
    if (button) button.disabled = true;

    try {
        const feeSignature = await obtainFeeSignature(status, weekNumber);
        const r = await submitBundleWithRetry(weekNumber, answers, feeSignature, status);
        clearPendingFee();

        if (r.is_complete) {
            status.className = 'tzla-word-status tzla-ok';
            status.textContent = `All ${r.correct_count}/${r.total_words} correct! Yer on the bounty board.`;
            showToast('Week cleared!');
            await refreshWeeks();
            loadWeekIntoPopup(weekNumber);
            return;
        }

        status.className = 'tzla-word-status tzla-bad';
        status.textContent = `${r.correct_count}/${r.total_words} correct. ${r.attempts_left} attempts left.`;
        if (r.attempts_left > 0) {
            inputs.forEach(i => { i.disabled = false; });
            updateBundleSubmitEnabled(form);
        }
        await refreshWeeks();
    } catch (err) {
        status.className = 'tzla-word-status tzla-bad';
        status.textContent = guessErrorMessage(err);

        if (err.code === 'fee_signature_already_used') clearPendingFee();
        const fatal = err.code === 'no_attempts_left' || err.code === 'already_completed';
        if (!fatal) {
            inputs.forEach(i => { i.disabled = false; });
            updateBundleSubmitEnabled(form);
        }
        if (fatal) {
            clearPendingFee();
            loadWeekIntoPopup(weekNumber);
        }
    }
}

function guessErrorMessage(err) {
    if (err instanceof PaymentError) {
        return err.cancelled ? 'Payment cancelled — no attempt submitted.' : err.message;
    }
    if (err.status === 401) return 'Connect wallet first.';
    if (err.status === 429) return err.message || 'Too many submissions. Slow down.';
    if (err.code === 'fee_signature_already_used') {
        return 'That payment was already used. Each attempt needs its own payment.';
    }
    if (err.code === 'invalid_fee_payment') {
        return 'Payment not confirmed yet. Tap submit again — we will reuse the same payment, not charge twice.';
    }
    if (err.code === 'incomplete_answers') {
        return err.message || 'Fill every answer before submitting.';
    }
    return err.message || 'Submission failed.';
}

function escape(s) {
    return String(s).replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
}

function wireEvents() {
    document.addEventListener('click', ev => {
        const t = ev.target.closest('[data-action]');
        if (!t) return;
        if (t.dataset.action === 'connect')     startConnect();
        if (t.dataset.action === 'disconnect')  disconnectWallet();
        if (t.dataset.action === 'close-popup') $('#tzla-popup').close();
        if (t.dataset.action === 'open-htp')     openHowToPlay();
        if (t.dataset.action === 'close-htp')    $('#tzla-htp').close();
        if (t.dataset.action === 'open-prizes')  openPrizes();
        if (t.dataset.action === 'close-prizes') $('#tzla-prizes').close();
        if (t.dataset.action === 'close-wallet-modal') closeWalletModal();
    });

    const walletBackdrop = document.querySelector('#walletModal .wallet-modal-backdrop');
    if (walletBackdrop) {
        walletBackdrop.addEventListener('click', () => closeWalletModal());
    }

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeWalletModal();
    });

    const htpDlg = $('#tzla-htp');
    if (htpDlg) {
        htpDlg.addEventListener('click', ev => {
            if (!ev.target.closest('.htp-content')) htpDlg.close();
        });
    }

    const prizesDlg = $('#tzla-prizes');
    if (prizesDlg) {
        prizesDlg.addEventListener('click', ev => {
            const weekEl = ev.target.closest('.prizes-week[data-week]');
            if (weekEl) {
                openRewardDetail(parseInt(weekEl.dataset.week, 10));
                return;
            }
            if (!ev.target.closest('.prizes-content')) prizesDlg.close();
        });
    }

    const rewardDetailDlg = $('#tzla-reward-detail');
    if (rewardDetailDlg) {
        rewardDetailDlg.addEventListener('click', ev => {
            if (!ev.target.closest('.prizes-content')) rewardDetailDlg.close();
        });
    }

    $$('.weekpaper[data-week]').forEach(el => {
        el.addEventListener('click', () => {
            const w = parseInt(el.dataset.week, 10);
            if (w) openPopupForWeek(w);
        });
    });

    const dlg = $('#tzla-popup');
    if (dlg) {
        dlg.addEventListener('click', ev => {
            if (!ev.target.closest('.tzla-popup-hit')) dlg.close();
        });
    }
}

document.addEventListener('DOMContentLoaded', async () => {
    wireEvents();
    renderConnect();
    await Promise.all([
        refreshMe(),
        waitForInjectedWallet(isIos() ? 5000 : isMobileDevice() ? 3000 : 500),
        waitForWallets(1500),
    ]);
    await Promise.all([refreshConfig(), refreshWeeks()]);
    await restoreAdapterSession();
});
