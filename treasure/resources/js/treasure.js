import { payGuessFee, PaymentError } from './payment.js';

const state = {
    wallet: null,
    attemptsPerWord: 0,
    weeks: [],
    config: null,
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
                <span class="wallet-att">${state.attemptsPerWord} tries/word</span>
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
            <button type="button" class="wallet-connect" data-action="connect" aria-label="Connect Phantom"><img src="/storage/connectbtn.png" alt="Connect Phantom" /></button>
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
    else if (w.total_words > 0 && w.solved_word_count >= w.total_words) el.classList.add('is-complete');
    else if (w.solved_word_count > 0) el.classList.add('is-partial');
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
        state.attemptsPerWord = data.attempts_per_word || 0;
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

/** SOL owed for the connected wallet's tier. */
function currentFeeSol() {
    if (!state.config) return null;
    const fees = state.config.fees || {};
    if (state.wallet && (state.wallet.golden_ticket_count || 0) > 0) {
        return fees.golden_ticket_sol;
    }
    return state.config.your_fee_sol ?? fees.standard_sol;
}

async function refreshWeeks() {
    try {
        const data = await api('GET', '/api/weeks');
        state.weeks = data.weeks || [];
    } catch { state.weeks = []; }
    renderWeekTints();
}

async function connectPhantom() {
    if (!window.solana || !window.solana.isPhantom) {
        alert('Phantom wallet not detected. Install it from phantom.app then reload.');
        window.open('https://phantom.app/', '_blank');
        return;
    }
    try {
        const resp = await window.solana.connect();
        const address = resp.publicKey.toString();
        const { nonce, message } = await api('POST', '/api/auth/nonce', { address });
        const encoded = new TextEncoder().encode(message);
        const signed = await window.solana.signMessage(encoded, 'utf8');
        const sigBase58 = toBase58(signed.signature);
        const verify = await api('POST', '/api/auth/verify', {
            address, nonce, signature: sigBase58,
        });
        state.wallet = verify.wallet;
        state.attemptsPerWord = verify.attempts_per_word || 0;
        renderConnect();
        // Fee tier depends on the connected wallet's holdings.
        await Promise.all([refreshConfig(), refreshWeeks()]);
    } catch (e) {
        console.error(e);
        alert('Wallet connect failed: ' + (e.message || e));
    }
}

async function disconnectWallet() {
    try { await api('POST', '/api/auth/logout'); } catch {}
    try { window.solana && window.solana.disconnect && await window.solana.disconnect(); } catch {}
    state.wallet = null; state.attemptsPerWord = 0;
    renderConnect();
    await refreshWeeks();
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
    const rows = week.words.map(word => wordRow(week.number, word, connected, eligible)).join('');

    const fee = currentFeeSol();
    const paymentsOn = state.config ? state.config.payments_enabled !== false : true;
    const feeNote = (eligible && fee !== null && paymentsOn)
        ? `<div class="tzla-note">Each guess costs <strong>${formatSol(fee)} SOL</strong>, paid to the treasury from your wallet. You approve every payment in Phantom.</div>`
        : '';

    const ineligibleNote = (connected && !eligible)
        ? '<div class="tzla-note">This wallet is not eligible to play. Hold TZLA, an NFT, or a Golden Ticket.</div>'
        : '';

    const profile = eligible ? `
        <div class="tzla-profile">
            <input type="text" id="tzla-username" maxlength="64" placeholder="Display name (optional)"
                   value="${escape(state.wallet?.username || '')}" autocomplete="off" />
            <input type="text" id="tzla-payout" maxlength="64" placeholder="Payout wallet (optional)"
                   value="${escape(state.wallet?.payout_address || '')}" autocomplete="off" />
        </div>` : '';

    body.innerHTML = `
        ${week.reward_description ? `<div class="tzla-reward">Reward: <em>${escape(week.reward_description)}</em></div>` : ''}
        ${!connected ? '<div class="tzla-note">Connect a Phantom wallet holding TZLA to submit guesses.</div>' : ''}
        ${ineligibleNote}
        ${feeNote}
        ${profile}
        <div class="tzla-words">${rows}</div>
        ${week.week_complete ? '<div class="tzla-done">🏴‍☠️ Week completed. Reward payout is on its way.</div>' : ''}
    `;
    $$('form.tzla-word', body).forEach(f => f.addEventListener('submit', onGuessSubmit));
}

function wordRow(weekNumber, word, connected, eligible = true) {
    const disabled = word.is_solved || !connected || !eligible || word.attempts_left <= 0;
    const status = word.is_solved
        ? `<span class="tzla-ok">Solved: ${escape(word.solved_answer)}</span>`
        : (connected
            ? `<span class="tzla-tries">${word.attempts_left}/${word.attempts_allowed} tries left</span>`
            : `<span class="tzla-tries">—</span>`);
    return `
        <form class="tzla-word" data-week="${weekNumber}" data-pos="${word.position}">
            <div class="tzla-word-head">
                <span class="tzla-word-n">Word ${word.position}</span>
                ${status}
            </div>
            <div class="tzla-hint">${word.hint ? escape(word.hint) : '<em>No hint provided.</em>'}</div>
            <div class="tzla-guess-row">
                <input type="text" name="guess" placeholder="Yer answer…" ${disabled ? 'disabled' : ''} autocomplete="off" />
                <button type="submit" aria-label="Submit" ${disabled ? 'disabled' : ''}><img src="/storage/submitbtn.png" alt="Submit" /></button>
            </div>
            <div class="tzla-word-status"></div>
        </form>`;
}

const sleep = ms => new Promise(r => setTimeout(r, ms));

function formatSol(amount) {
    if (amount === null || amount === undefined) return '';
    return String(parseFloat(Number(amount).toFixed(9)));
}

/** Optional payout details captured once per popup and sent with each guess. */
function profileFields() {
    const username = ($('#tzla-username')?.value || '').trim();
    const payout = ($('#tzla-payout')?.value || '').trim();
    const out = {};
    if (username) out.username = username;
    if (payout) out.payout_address = payout;
    return out;
}

/**
 * Pays the per-guess fee and returns the transaction signature. In local
 * development the backend runs the stub verifier, so no real transfer is made.
 */
async function obtainFeeSignature(status) {
    if (!state.config) await refreshConfig();

    if (state.config && state.config.payments_enabled === false) {
        return `dev-${Date.now()}-${Math.random().toString(36).slice(2)}`;
    }
    if (!state.config) {
        throw new PaymentError('Could not load payment settings. Try reloading.');
    }

    const amountSol = currentFeeSol();
    status.className = 'tzla-word-status';
    status.textContent = `Approve ${formatSol(amountSol)} SOL in Phantom…`;

    return payGuessFee({
        treasury: state.config.treasury_address,
        amountSol,
        from: state.wallet.address,
        fetchBlockhash: () => api('GET', '/api/solana/blockhash'),
    });
}

/**
 * A freshly sent transaction may not be confirmed by the time the backend looks
 * it up, so retry that specific failure. Retrying is safe: the signature is only
 * consumed once verification actually succeeds.
 */
async function submitGuessWithRetry(weekNumber, position, guess, feeSignature, status) {
    const payload = { guess, fee_signature: feeSignature, ...profileFields() };
    const maxAttempts = 6;

    for (let attempt = 1; attempt <= maxAttempts; attempt++) {
        try {
            return await api('POST', `/api/weeks/${weekNumber}/words/${position}/guess`, payload);
        } catch (err) {
            const awaitingConfirmation = err.status === 402 && err.code === 'invalid_fee_payment';
            if (!awaitingConfirmation || attempt === maxAttempts) throw err;

            status.className = 'tzla-word-status';
            status.textContent = `Waiting for payment confirmation… (${attempt}/${maxAttempts})`;
            await sleep(2500);
        }
    }
}

async function onGuessSubmit(e) {
    e.preventDefault();
    const form = e.currentTarget;
    const weekNumber = parseInt(form.dataset.week, 10);
    const position = parseInt(form.dataset.pos, 10);
    const input = form.querySelector('input[name=guess]');
    const button = form.querySelector('button');
    const status = form.querySelector('.tzla-word-status');
    const guess = (input.value || '').trim();
    if (!guess) return;

    if (!state.wallet) {
        status.className = 'tzla-word-status tzla-bad';
        status.textContent = 'Connect wallet first.';
        return;
    }
    if (state.wallet.can_play === false) {
        status.className = 'tzla-word-status tzla-bad';
        status.textContent = 'This wallet does not hold enough TZLA, an NFT, or a Golden Ticket.';
        return;
    }

    input.disabled = true;
    button.disabled = true;

    try {
        const feeSignature = await obtainFeeSignature(status);
        const r = await submitGuessWithRetry(weekNumber, position, guess, feeSignature, status);

        if (r.is_correct) {
            status.className = 'tzla-word-status tzla-ok';
            status.textContent = 'Correct! Locked in.';
            await refreshWeeks();
            if (r.week_complete) showToast('Week complete!');
            loadWeekIntoPopup(weekNumber);
            return;
        }

        status.className = 'tzla-word-status tzla-bad';
        status.textContent = `Nay. ${r.attempts_left} tries left.`;
        const tries = form.querySelector('.tzla-tries');
        if (tries) tries.textContent = `${r.attempts_left}/${r.attempts_allowed} tries left`;

        if (r.attempts_left > 0) {
            input.disabled = false;
            button.disabled = false;
            input.value = '';
            input.focus();
        }
    } catch (err) {
        status.className = 'tzla-word-status tzla-bad';
        status.textContent = guessErrorMessage(err);

        // Anything other than a spent attempt leaves the player free to retry.
        const attemptConsumed = err.code === 'no_attempts_left' || err.code === 'already_solved';
        if (!attemptConsumed) {
            input.disabled = false;
            button.disabled = false;
        }
        if (err.code === 'already_solved' || err.code === 'no_attempts_left') {
            loadWeekIntoPopup(weekNumber);
        }
    }
}

function guessErrorMessage(err) {
    if (err instanceof PaymentError) {
        return err.cancelled ? 'Payment cancelled — no guess submitted.' : err.message;
    }
    if (err.status === 401) return 'Connect wallet first.';
    if (err.status === 429) return err.message || 'Too many guesses. Slow down.';
    if (err.code === 'fee_signature_already_used') {
        return 'That payment was already used. Each guess needs its own payment.';
    }
    if (err.code === 'invalid_fee_payment') {
        return 'Payment could not be confirmed on-chain. If SOL left your wallet, wait a moment and try again.';
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
        if (t.dataset.action === 'connect')     connectPhantom();
        if (t.dataset.action === 'disconnect')  disconnectWallet();
        if (t.dataset.action === 'close-popup') $('#tzla-popup').close();
        if (t.dataset.action === 'open-htp')     openHowToPlay();
        if (t.dataset.action === 'close-htp')    $('#tzla-htp').close();
        if (t.dataset.action === 'open-prizes')  openPrizes();
        if (t.dataset.action === 'close-prizes') $('#tzla-prizes').close();
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
    await refreshMe();
    await Promise.all([refreshConfig(), refreshWeeks()]);
});
