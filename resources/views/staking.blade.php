<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>TZLA — The Vault</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;0,700;1,300;1,400;1,600&family=Cormorant+SC:wght@300;400;600;700&family=Spectral:ital,wght@0,300;0,400;0,600;1,300;1,400&display=swap" rel="stylesheet" />
  @include('partials.nav-css')

  {{-- Inject on-chain config before the module boots --}}
  <script>
    window.STAKING_CONFIG = {
        rpc:            @json($rpc),
        programId:      @json($programId),
        stakeTokenMint: @json($stakeTokenMint),
        nftCollection:  @json($nftCollection),
        poolOwner:      @json($poolOwner),
    };

    window.STAKING_CARD_FRAME = "{{ asset('images/staking-card/card-frame.png') }}?v=2";
    window.STAKING_CARD_BACKGROUNDS = [
      @for ($i = 1; $i <= 17; $i++)
        "{{ asset("images/staking-card/characters/TZLA_{$i}.png") }}",
      @endfor
    ];
  </script>

  @vite(['resources/js/staking.ts'])

  <script>
    (function () {
      function isMobile() { return /android|iphone|ipad|ipod/i.test(navigator.userAgent); }
      function isIos() { return /iphone|ipad|ipod/i.test(navigator.userAgent); }
      function phantomUrl() {
        var u = encodeURIComponent(location.href), r = encodeURIComponent(location.origin);
        if (/android/i.test(navigator.userAgent)) {
          return 'intent://ul/browse/' + u + '?ref=' + r + '#Intent;scheme=https;host=phantom.app;package=app.phantom;end';
        }
        return 'https://phantom.app/ul/browse/' + u + '?ref=' + r;
      }
      function solflareUrl() {
        var u = encodeURIComponent(location.href), r = encodeURIComponent(location.origin);
        return 'https://solflare.com/ul/v1/browse/' + u + '?ref=' + r;
      }
      document.addEventListener('DOMContentLoaded', function () {
        var btn = document.getElementById('connectBtn');
        if (!btn || !isMobile()) return;
        btn.addEventListener('click', function () {
          setTimeout(function () {
            if (typeof window.connectOrDisconnect === 'function') return;
            if (window.phantom?.solana || window.solana?.isPhantom || window.solflare?.isSolflare) return;
            if (document.getElementById('walletModal')?.classList.contains('open')) return;
            if (isIos()) {
              var usePhantom = confirm('iPhone: open this page in Phantom?\\n\\nTap OK for Phantom, Cancel for Solflare.');
              location.assign(usePhantom ? phantomUrl() : solflareUrl());
            } else {
              location.assign(phantomUrl());
            }
          }, 1200);
        });
      });
    })();
  </script>

  <style>
    /* ─── TOKENS ──────────────────────────────────────────────────────── */
    :root {
      --parch:         #f2e8d0;
      --parch-dark:    #e8d9b4;
      --parch-deeper:  #d4c090;
      --ink:           #1a130a;
      --ink-mid:       #3b2a18;
      --ink-light:     #6b4f30;
      --copper:        #b06a1a;
      --copper-bright: #d4882a;
      --verdigris:     #2a7c6b;
      --verdigris-lt:  #3aa88f;
      --arc:           #7fd4f8;
      --arc-glow:      #b8ecff;
      --gold:          #c8922a;
      --gold-lt:       #f0c060;
      --shadow:        rgba(26,19,10,0.18);
      --shadow-deep:   rgba(26,19,10,0.35);
      --ff-title:      'Cormorant Garamond', serif;
      --ff-head:       'Cormorant SC', serif;
      --ff-body:       'Spectral', serif;
      --ff-caps:       'Cormorant SC', serif;
      --nav-h:         3.82rem;
    }

    /* ─── RESET ───────────────────────────────────────────────────────── */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    html { font-size: clamp(22px, 1.527vw, 30px); scroll-behavior: smooth; }

    body {
      background-color: #0d0a06;
      color: var(--ink);
      font-family: var(--ff-body);
      min-height: 100vh;
      overflow-x: hidden;
      cursor: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='20' height='20' viewBox='0 0 20 20'%3E%3Ccircle cx='10' cy='10' r='3' fill='none' stroke='%23b06a1a' stroke-width='1.5'/%3E%3Cline x1='10' y1='0' x2='10' y2='6' stroke='%23b06a1a' stroke-width='1'/%3E%3Cline x1='10' y1='14' x2='10' y2='20' stroke='%23b06a1a' stroke-width='1'/%3E%3Cline x1='0' y1='10' x2='6' y2='10' stroke='%23b06a1a' stroke-width='1'/%3E%3Cline x1='14' y1='10' x2='20' y2='10' stroke='%23b06a1a' stroke-width='1'/%3E%3C/svg%3E") 10 10, crosshair;
    }

    body::before {
      content: '';
      position: fixed; inset: 0;
      pointer-events: none; z-index: 9999; opacity: 0.03;
      background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
      background-size: 200px 200px;
    }

    /* ─── PARCHMENT WRAPPER ───────────────────────────────────────────── */
    .parchment {
      background:
        radial-gradient(ellipse at 15% 15%, rgba(210,180,130,0.18) 0%, transparent 55%),
        radial-gradient(ellipse at 85% 80%, rgba(180,140,80,0.14) 0%, transparent 50%),
        linear-gradient(160deg, #f5ebcf 0%, #eedd9f 35%, #f0e4b8 65%, #e8d49a 100%);
      min-height: 100vh;
      position: relative;
      overflow: hidden;
    }

    .parchment::after {
      content: '';
      position: fixed; inset: 0;
      pointer-events: none;
      background: radial-gradient(ellipse at 50% 50%, transparent 55%, rgba(26,19,10,0.55) 100%);
      z-index: 100;
    }

    /* ─── BACKGROUND LAYERS ───────────────────────────────────────────── */
    .map-grid {
      position: fixed; inset: 0;
      pointer-events: none; z-index: 0; opacity: 0.055;
      background-image:
        linear-gradient(var(--ink-mid) 1px, transparent 1px),
        linear-gradient(90deg, var(--ink-mid) 1px, transparent 1px);
      background-size: 72px 72px;
    }

    .arc-layer {
      position: fixed; inset: 0;
      pointer-events: none; z-index: 1; overflow: hidden;
    }

    .arc-layer svg { position: absolute; width: 100%; height: 100%; }

    .compass-watermark {
      position: fixed; bottom: -140px; right: -140px;
      width: 520px; height: 520px;
      opacity: 0.06; pointer-events: none; z-index: 1;
    }

    /* ─── NAVIGATION ──────────────────────────────────────────────────── */
    /* staking: nav specific overrides */
    .nav-bar { position: sticky; top: 0; }


    /* ─── MAIN LAYOUT ─────────────────────────────────────────────────── */
    .main-scroll { position: relative; z-index: 10; }

    .site-wrapper {
      max-width: 1100px; margin: 0 auto;
      padding: 3rem 3rem 5rem;
    }

    /* ─── PAGE HERO ───────────────────────────────────────────────────── */
    .page-hero {
      text-align: center; padding: 4rem 0 3rem;
    }

    .page-eyebrow {
      font-family: var(--ff-caps); font-size: 0.78rem; letter-spacing: 0.32em;
      color: var(--verdigris); display: block; margin-bottom: 1.2rem;
      opacity: 0; animation: fade-up 0.7s 0.1s ease forwards;
    }

    .page-kicker {
      font-family: var(--ff-caps); font-size: 1.05rem; letter-spacing: 0.32em;
      color: var(--verdigris); display: block; margin-bottom: 1.2rem;
      opacity: 0; animation: fade-up 0.7s 0.1s ease forwards;
    }

    .page-title {
      font-family: var(--ff-title); font-weight: 700;
      font-size: clamp(3.5rem, 8vw, 6.5rem); line-height: 1;
      color: var(--ink); letter-spacing: 0.04em;
      opacity: 0; animation: fade-up 0.8s 0.25s ease forwards;
    }

    .page-sub {
      font-family: var(--ff-body); font-style: italic; font-weight: 400;
      font-size: 1.1rem; color: var(--ink-light);
      margin-top: 1.2rem; line-height: 1.85;
      opacity: 0; animation: fade-up 0.8s 0.4s ease forwards;
    }

    .page-ornament {
      display: flex; align-items: center; gap: 1rem;
      justify-content: center; margin-top: 2rem;
      opacity: 0; animation: fade-in 1s 0.6s ease forwards;
    }

    .page-ornament span {
      display: block; width: 60px; height: 1px;
      background: linear-gradient(90deg, transparent, var(--copper));
    }

    .page-ornament span:last-child {
      background: linear-gradient(90deg, var(--copper), transparent);
    }

    .page-motto {
      font-family: var(--ff-caps); font-weight: 600;
      font-size: 0.92rem; letter-spacing: 0.34em; text-transform: uppercase;
      color: var(--copper); text-align: center; margin-top: 1.4rem;
      opacity: 0; animation: fade-up 0.8s 0.75s ease forwards;
    }

    @keyframes fade-up {
      from { opacity: 0; transform: translateY(18px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    @keyframes fade-in {
      from { opacity: 0; }
      to   { opacity: 1; }
    }

    /* ─── STAKE CARDS ─────────────────────────────────────────────────── */
    .stake-card {
      background: linear-gradient(145deg, rgba(242,232,208,0.92), rgba(232,217,180,0.82));
      border: 1px solid rgba(176,106,26,0.4);
      border-radius: 2px;
      padding: 2.2rem 2.4rem;
      position: relative; overflow: hidden;
      box-shadow: 0 4px 24px var(--shadow), inset 0 1px 0 rgba(240,192,96,0.2);
      margin-bottom: 1.6rem;
      transition: border-color 0.3s;
    }

    .stake-card::before {
      content: '';
      position: absolute; top: 0; left: 0; right: 0; height: 2px;
      background: linear-gradient(90deg, transparent, var(--copper), var(--gold-lt), var(--copper), transparent);
    }

    .stake-card::after {
      content: '';
      position: absolute; inset: 0;
      background: radial-gradient(ellipse at 85% 15%, rgba(127,212,248,0.05) 0%, transparent 55%);
      pointer-events: none;
    }

    /* ─── CARD HEADER ─────────────────────────────────────────────────── */
    .card-header {
      display: flex; align-items: center; justify-content: space-between;
      margin-bottom: 1.6rem; padding-bottom: 1rem;
      border-bottom: 1px solid rgba(176,106,26,0.2);
    }

    .card-header-title {
      font-family: var(--ff-head); font-size: 1.2rem; font-weight: 600;
      letter-spacing: 0.06em; color: var(--ink);
    }

    .section-label {
      font-family: var(--ff-caps); font-size: 0.72rem; letter-spacing: 0.28em;
      color: var(--copper); text-transform: uppercase;
    }

    .pool-status {
      font-family: var(--ff-caps); font-size: 0.68rem; letter-spacing: 0.16em;
      color: var(--ink-light);
    }

    .pool-status.active { color: var(--verdigris); }

    /* ─── STATS GRID ──────────────────────────────────────────────────── */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 0;
      margin-bottom: 1.6rem;
    }

    .stat-entry {
      padding: 1rem 1.2rem 1rem 1rem;
      border-right: 1px solid rgba(176,106,26,0.18);
    }

    .stat-entry:last-child { border-right: none; padding-right: 0; }
    .stat-entry:first-child { padding-left: 0; }

    .stat-label {
      font-family: var(--ff-caps); font-size: 0.65rem; letter-spacing: 0.22em;
      color: var(--ink-light); display: block; margin-bottom: 0.4rem;
    }

    .stat-value {
      font-family: var(--ff-title); font-size: 1.3rem; font-weight: 700;
      color: var(--ink); display: block; letter-spacing: 0.02em;
    }

    .stat-subvalue {
      display: block; margin-top: 0.15rem;
      font-family: var(--ff-caps); font-size: 0.68rem; letter-spacing: 0.1em;
      color: var(--verdigris); font-variant-numeric: tabular-nums;
    }

    .stat-value.copper { color: var(--copper); }
    .stat-value.verdigris { color: var(--verdigris); }
    .stat-value.arc { color: var(--verdigris); }

    /* ─── CAPACITY BAR ────────────────────────────────────────────────── */
    .capacity-section { margin-bottom: 1.4rem; }

    .capacity-track {
      height: 6px; border-radius: 3px;
      background: rgba(176,106,26,0.15);
      margin-top: 0.5rem; overflow: hidden;
      position: relative;
    }

    .capacity-fill {
      height: 100%; border-radius: 3px; width: 0%;
      background: linear-gradient(90deg, var(--copper), var(--gold-lt), var(--arc));
      transition: width 0.8s cubic-bezier(0.4,0,0.2,1);
      box-shadow: 0 0 8px rgba(127,212,248,0.25);
    }

    /* ─── POOL FOOTER ─────────────────────────────────────────────────── */
    .pool-meta {
      display: flex; gap: 2rem; align-items: center;
      padding-top: 1rem; border-top: 1px solid rgba(176,106,26,0.15);
      flex-wrap: wrap;
    }

    .pool-meta-item { display: flex; flex-direction: column; gap: 0.2rem; }

    .mono-small {
      font-family: 'Cormorant Garamond', monospace; font-size: 0.72rem;
      color: var(--ink-mid); letter-spacing: 0.04em;
    }

    /* ─── DUAL GRID ───────────────────────────────────────────────────── */
    .dual-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1.6rem;
      margin-bottom: 0;
    }

    /* ─── CHRONICLE LIST (position) ───────────────────────────────────── */
    .wallet-display {
      background: rgba(26,19,10,0.06);
      border: 1px solid rgba(176,106,26,0.25);
      border-radius: 2px; padding: 0.8rem 1rem;
      margin-bottom: 1.2rem;
    }

    .wallet-display.hidden { display: none; }

    .wallet-display .mono-small { font-size: 0.68rem; word-break: break-all; }

    .chronicle-list { display: flex; flex-direction: column; gap: 0; }

    .chronicle-row {
      display: flex; justify-content: space-between; align-items: center;
      padding: 0.75rem 0;
      border-bottom: 1px solid rgba(176,106,26,0.14);
    }

    .chronicle-row:last-child { border-bottom: none; }

    .chronicle-row > span:first-child {
      font-family: var(--ff-caps); font-size: 0.72rem; letter-spacing: 0.16em;
      color: var(--ink-light);
    }

    .chronicle-row > span:last-child,
    .chronicle-row > .stat-value {
      font-family: var(--ff-title); font-size: 1rem; font-weight: 600;
      color: var(--ink); letter-spacing: 0.02em;
    }

    .chronicle-row.small > span:last-child {
      font-size: 0.7rem; font-family: 'Cormorant Garamond', monospace;
      color: var(--ink-light);
    }

    /* ─── BOOST BADGE ─────────────────────────────────────────────────── */
    .boost-badge {
      font-family: var(--ff-caps); font-size: 0.65rem; letter-spacing: 0.14em;
      padding: 0.25rem 0.6rem; border-radius: 1px;
      border: 1px solid transparent; display: inline-block;
    }

    .boost-active {
      color: var(--verdigris); border-color: rgba(42,124,107,0.4);
      background: rgba(42,124,107,0.08);
    }

    .boost-inactive {
      color: var(--ink-light); border-color: rgba(176,106,26,0.2);
      background: rgba(176,106,26,0.05);
    }

    /* ─── REFRESH BUTTON ──────────────────────────────────────────────── */
    .refresh-btn {
      margin-top: 1.4rem; width: 100%;
      font-family: var(--ff-caps); font-size: 0.72rem; letter-spacing: 0.2em;
      color: var(--ink-light); background: transparent;
      border: 1px solid rgba(176,106,26,0.3); border-radius: 2px;
      padding: 0.65rem 1rem; cursor: pointer;
      transition: color 0.25s, border-color 0.25s, box-shadow 0.25s;
    }

    .refresh-btn:hover {
      color: var(--copper-bright); border-color: var(--copper-bright);
      box-shadow: 0 0 12px rgba(176,106,26,0.18);
    }

    .claim-btn {
      width: 100%;
      margin-top: 0.75rem;
      padding: 0.9rem 1.5rem;
      font-family: var(--ff-caps);
      font-size: 0.8rem;
      letter-spacing: 0.22em;
      color: var(--ink);
      background: linear-gradient(135deg, rgba(42,124,107,0.22), rgba(26,100,86,0.14));
      border: 1px solid var(--verdigris);
      border-radius: 2px;
      cursor: pointer;
      transition: color 0.25s, border-color 0.25s, box-shadow 0.25s, background 0.25s;
    }

    .claim-btn:hover {
      color: var(--ink);
      border-color: var(--verdigris-lt);
      background: linear-gradient(135deg, rgba(42,124,107,0.32), rgba(26,100,86,0.22));
      box-shadow: 0 0 16px rgba(42,124,107,0.2);
    }

    /* ─── EARNINGS SUMMARY TILE ───────────────────────────────────────── */
    .earnings-summary-empty {
      font-family: var(--ff-body);
      font-style: italic;
      font-size: 0.9rem;
      color: var(--ink-light);
      padding: 1.5rem 0.25rem;
      text-align: center;
    }

    .earnings-summary-body {
      display: none;
    }

    .earnings-summary-body.is-visible {
      display: block;
    }

    .earnings-summary-preview-wrap {
      position: relative;
      width: 100%;
      border-radius: 2px;
      overflow: hidden;
      border: 1px solid rgba(176,106,26,0.3);
      background: rgba(232,217,180,0.45);
      aspect-ratio: 1200 / 675;
    }

    .earnings-summary-preview-wrap img {
      display: none;
      width: 100%;
      height: 100%;
      object-fit: contain;
    }

    .earnings-summary-preview-wrap img.is-visible {
      display: block;
    }

    .earnings-summary-loading {
      position: absolute;
      inset: 0;
      display: none;
      align-items: center;
      justify-content: center;
      background: rgba(242,232,208,0.92);
      font-family: var(--ff-body);
      font-style: italic;
      color: var(--ink-light);
      font-size: 0.9rem;
    }

    .earnings-summary-loading.is-visible {
      display: flex;
    }

    .earnings-summary-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 0.6rem;
      margin-top: 0.85rem;
    }

    .earnings-summary-actions .card-btn {
      flex: 1 1 auto;
      justify-content: center;
      min-width: 6.5rem;
    }

    /* ─── STAKING CARD MODAL ──────────────────────────────────────────── */
    .card-modal-overlay {
      position: fixed; inset: 0; z-index: 10000;
      display: none; align-items: center; justify-content: center;
      padding: 1rem;
    }

    .card-modal-overlay.open { display: flex !important; }

    body.wallet-modal-open { overflow: hidden; }

    .card-modal-backdrop {
      position: absolute; inset: 0;
      background: rgba(26,19,10,0.5);
      backdrop-filter: blur(4px);
    }

    .card-modal-panel {
      position: relative; z-index: 1;
      width: 100%; max-width: 920px;
      background: linear-gradient(145deg, rgba(242,232,208,0.98), rgba(232,217,180,0.95));
      border: 1px solid rgba(176,106,26,0.45);
      border-radius: 2px;
      padding: 1.25rem 1.5rem 1rem;
      box-shadow: 0 4px 24px var(--shadow), inset 0 1px 0 rgba(240,192,96,0.2);
      max-height: 95vh;
      overflow-y: auto;
    }

    .card-modal-panel::before {
      content: '';
      position: absolute; top: 0; left: 0; right: 0; height: 2px;
      background: linear-gradient(90deg, transparent, var(--copper), var(--gold-lt), var(--copper), transparent);
    }

    .card-modal-header {
      display: flex; align-items: center; justify-content: space-between;
      margin-bottom: 1rem;
      padding-bottom: 0.75rem;
      border-bottom: 1px solid rgba(176,106,26,0.2);
    }

    .card-modal-title {
      font-family: var(--ff-head);
      font-size: 1.1rem; font-weight: 600;
      letter-spacing: 0.06em; color: var(--ink);
    }

    .card-modal-close {
      background: none; border: none; cursor: pointer;
      font-family: var(--ff-head);
      color: var(--ink-light); font-size: 1.5rem; line-height: 1; padding: 0.25rem;
      transition: color 0.25s;
    }

    .card-modal-close:hover { color: var(--copper-bright); }

    .card-preview-wrap {
      position: relative;
      width: 100%;
      max-width: 640px;
      margin: 0 auto;
      border-radius: 2px;
      overflow: hidden;
      border: 1px solid rgba(176,106,26,0.3);
      background: rgba(232,217,180,0.45);
      aspect-ratio: 1200 / 675;
    }

    .card-preview-wrap img,
    .card-preview-wrap canvas {
      display: block; width: 100%; height: 100%; object-fit: contain;
    }

    .card-modal-loading {
      position: absolute; inset: 0;
      display: none; align-items: center; justify-content: center;
      background: rgba(242,232,208,0.92);
      font-family: var(--ff-body); font-style: italic;
      color: var(--ink-light); font-size: 0.9rem;
    }

    .card-bg-picker {
      display: flex; flex-direction: column; gap: 0.35rem; margin-top: 0.85rem;
    }

    .card-picker-label {
      font-family: var(--ff-caps);
      font-size: 0.68rem; font-weight: 600;
      color: var(--copper); letter-spacing: 0.2em;
      text-transform: uppercase;
    }

    .card-picker-hint {
      font-family: var(--ff-body); font-style: italic;
      font-size: 0.75rem; color: var(--ink-light);
    }

    .card-bg-thumbs-row {
      display: flex; gap: 0.45rem;
      flex-wrap: nowrap;
      align-items: stretch;
      overflow-x: auto;
      padding-bottom: 0.15rem;
    }

    .card-bg-thumb {
      width: 46px; height: 69px;
      flex-shrink: 0;
      border-radius: 2px; border: 2px solid rgba(176,106,26,0.35);
      background-color: rgba(232,217,180,0.55);
      background-size: contain;
      background-position: center bottom;
      background-repeat: no-repeat;
      cursor: pointer; padding: 0;
      transition: border-color 0.25s, box-shadow 0.25s;
    }

    .card-bg-thumb.active,
    .card-bg-thumb:hover {
      border-color: var(--copper-bright);
      box-shadow: 0 0 10px rgba(176,106,26,0.2);
    }

    .card-modal-actions {
      display: flex; gap: 0.6rem; margin-top: 0.85rem;
      justify-content: flex-end;
    }

    .card-btn {
      display: inline-flex; align-items: center; gap: 0.4rem;
      font-family: var(--ff-caps);
      font-size: 0.72rem; letter-spacing: 0.18em;
      padding: 0.65rem 1.1rem; border-radius: 2px;
      cursor: pointer;
      border: 1px solid rgba(176,106,26,0.35);
      background: transparent; color: var(--ink);
      transition: color 0.25s, border-color 0.25s, box-shadow 0.25s, background 0.25s;
    }

    .card-btn:hover {
      color: var(--copper-bright); border-color: var(--copper-bright);
      box-shadow: 0 0 12px rgba(176,106,26,0.18);
      background: rgba(176,106,26,0.06);
    }

    /* ─── TIDE TABLES (yield rate ladder) ─────────────────────────────── */
    .rate-ladder { display: flex; flex-direction: column; }

    .rate-row {
      padding: 0.85rem 0;
      border-bottom: 1px solid rgba(176,106,26,0.14);
    }
    .rate-row:first-child { padding-top: 0; }
    .rate-row:last-child { border-bottom: none; }

    .rate-meta { display: flex; justify-content: space-between; align-items: baseline; }

    .rate-name {
      font-family: var(--ff-caps); font-size: 0.72rem; letter-spacing: 0.16em;
      color: var(--ink-light); display: inline-flex; align-items: center; gap: 0.45rem;
    }

    .rate-glyph { color: var(--gold); font-size: 0.62rem; letter-spacing: -0.12em; }

    .rate-pct {
      font-family: var(--ff-title); font-size: 1.2rem; font-weight: 700;
      letter-spacing: 0.02em; font-variant-numeric: tabular-nums;
    }
    .rate-pct.copper    { color: var(--copper); }
    .rate-pct.gold      { color: var(--gold); }
    .rate-pct.verdigris { color: var(--verdigris); }

    .tide-card { display: flex; flex-direction: column; }

    .nft-cta {
      display: block; width: 100%; margin-top: auto;
      padding: 0.9rem 1.5rem; text-align: center; text-decoration: none;
      font-family: var(--ff-caps); font-size: 0.8rem; letter-spacing: 0.22em;
      color: var(--ink);
      background: linear-gradient(135deg, rgba(42,124,107,0.16), rgba(127,212,248,0.1));
      border: 1px solid var(--verdigris); border-radius: 2px;
      transition: color 0.25s, border-color 0.25s, box-shadow 0.25s, background 0.25s;
    }

    .nft-cta:hover {
      border-color: var(--verdigris-lt);
      background: linear-gradient(135deg, rgba(42,124,107,0.26), rgba(127,212,248,0.18));
      box-shadow: 0 0 16px rgba(42,124,107,0.22), 0 4px 12px var(--shadow);
    }

    .rate-foot {
      margin-top: 1.5rem; padding-top: 1rem;
      border-top: 1px solid rgba(176,106,26,0.15);
      font-family: var(--ff-body); font-style: italic; font-size: 0.78rem;
      color: var(--ink-light); line-height: 1.5;
    }


    /* ─── TAB BAR ─────────────────────────────────────────────────────── */
    .tab-bar {
      display: flex; gap: 0; margin-bottom: 1.4rem;
      border: 1px solid rgba(176,106,26,0.3); border-radius: 2px; overflow: hidden;
    }

    .tab {
      flex: 1; padding: 0.6rem 0;
      font-family: var(--ff-caps); font-size: 0.72rem; letter-spacing: 0.18em;
      background: transparent; border: none; cursor: pointer;
      color: var(--ink-light);
      border-right: 1px solid rgba(176,106,26,0.2);
      transition: background 0.2s, color 0.2s;
    }

    .tab:last-child { border-right: none; }

    .tab:hover { color: var(--ink); background: rgba(176,106,26,0.06); }

    .tab.active-tab {
      background: linear-gradient(180deg, rgba(176,106,26,0.12), rgba(176,106,26,0.06));
      color: var(--copper);
    }

    /* ─── ACTION PANELS ───────────────────────────────────────────────── */
    .actions-card { display: flex; flex-direction: column; }
    .actions-card .action-panel:not(.hidden) { flex: 1 1 auto; }
    .actions-card .stake-btn,
    .actions-card .unstake-btn { margin-top: auto; }
    .action-panel { display: flex; flex-direction: column; gap: 1rem; }
    .action-panel.hidden { display: none; }

    .input-group { display: flex; flex-direction: column; gap: 0.35rem; }

    .input-header {
      display: flex; justify-content: space-between; align-items: center;
    }

    .max-btn {
      font-family: var(--ff-caps); font-size: 0.62rem; letter-spacing: 0.14em;
      color: var(--verdigris); background: none; border: none; cursor: pointer;
      padding: 0; transition: color 0.2s;
    }

    .max-btn:hover { color: var(--verdigris-lt); }

    .action-input {
      width: 100%;
      background: rgba(26,19,10,0.06);
      border: 1px solid rgba(176,106,26,0.35);
      border-radius: 2px; padding: 0.7rem 1rem;
      font-family: var(--ff-title); font-size: 1.05rem; font-weight: 600;
      color: var(--ink); outline: none;
      transition: border-color 0.2s, box-shadow 0.2s;
    }

    .action-input::placeholder {
      color: rgba(59,42,24,0.35); font-weight: 300; font-style: italic;
    }

    .action-input:focus {
      border-color: var(--copper-bright);
      box-shadow: 0 0 0 2px rgba(176,106,26,0.15);
    }

    .action-input.small-text {
      font-family: 'Cormorant Garamond', monospace;
      font-size: 0.72rem; font-weight: 400; letter-spacing: 0.03em;
    }

    /* ─── ACTION BUTTONS ──────────────────────────────────────────────── */
    .stake-btn {
      width: 100%; padding: 0.9rem 1.5rem;
      font-family: var(--ff-caps); font-size: 0.8rem; letter-spacing: 0.22em;
      color: var(--ink);
      background: linear-gradient(135deg, rgba(176,106,26,0.18), rgba(200,146,42,0.12));
      border: 1px solid var(--copper);
      border-radius: 2px; cursor: pointer;
      transition: color 0.25s, border-color 0.25s, box-shadow 0.25s, background 0.25s;
      position: relative; overflow: hidden;
    }

    .stake-btn::before {
      content: '';
      position: absolute; top: 0; left: 0; right: 0; height: 1px;
      background: linear-gradient(90deg, transparent, var(--copper-bright), transparent);
      opacity: 0; transition: opacity 0.3s;
    }

    .stake-btn:hover {
      color: var(--ink); border-color: var(--copper-bright);
      background: linear-gradient(135deg, rgba(176,106,26,0.28), rgba(200,146,42,0.2));
      box-shadow: 0 0 16px rgba(176,106,26,0.2), 0 4px 12px var(--shadow);
    }

    .stake-btn:hover::before { opacity: 1; }

    .unstake-btn {
      width: 100%; padding: 0.9rem 1.5rem;
      font-family: var(--ff-caps); font-size: 0.8rem; letter-spacing: 0.22em;
      color: var(--ink);
      background: linear-gradient(135deg, rgba(42,124,107,0.18), rgba(26,100,86,0.12));
      border: 1px solid var(--verdigris);
      border-radius: 2px; cursor: pointer;
      transition: color 0.25s, border-color 0.25s, box-shadow 0.25s, background 0.25s;
    }

    .unstake-btn:hover {
      color: var(--ink); border-color: var(--verdigris-lt);
      background: linear-gradient(135deg, rgba(42,124,107,0.28), rgba(26,100,86,0.2));
      box-shadow: 0 0 16px rgba(42,124,107,0.2);
    }

    .action-note {
      font-family: var(--ff-body); font-style: italic; font-weight: 400;
      font-size: 0.8rem; line-height: 1.8; color: var(--ink-light);
    }

    /* ─── DEV DETAILS ─────────────────────────────────────────────────── */
    .dev-card { padding: 1.4rem 2rem; }

    .dev-card details > summary {
      font-family: var(--ff-caps); font-size: 0.68rem; letter-spacing: 0.2em;
      color: var(--ink-light); cursor: pointer; user-select: none;
      list-style: none; transition: color 0.2s;
    }

    .dev-card details > summary::before {
      content: '⊕  '; color: var(--copper); font-size: 0.65rem;
    }

    .dev-card details[open] > summary::before { content: '⊖  '; }

    .dev-card details > summary:hover { color: var(--copper); }

    .dev-grid {
      display: grid; grid-template-columns: repeat(2, 1fr);
      gap: 0.8rem; margin-top: 1rem;
    }

    .dev-entry {
      background: rgba(26,19,10,0.06); border: 1px solid rgba(176,106,26,0.18);
      border-radius: 2px; padding: 0.7rem 0.9rem;
      display: flex; flex-direction: column; gap: 0.25rem;
    }

    .dev-label {
      font-family: var(--ff-caps); font-size: 0.6rem; letter-spacing: 0.2em;
      color: var(--ink-light);
    }

    .dev-value {
      font-family: 'Cormorant Garamond', monospace; font-size: 0.68rem;
      color: var(--ink-mid); word-break: break-all; letter-spacing: 0.02em;
    }

    .dev-value.verdigris { color: var(--verdigris); }

    /* ─── TOAST ───────────────────────────────────────────────────────── */
    .toast {
      position: fixed; left: 1rem; right: 1rem; bottom: calc(1.2rem + env(safe-area-inset-bottom, 0px));
      top: auto; z-index: 9990;
      padding: 0.85rem 1.2rem; border-radius: 2px;
      font-family: var(--ff-caps); font-size: 0.78rem; letter-spacing: 0.12em;
      border: 1px solid; max-width: 420px; margin: 0 auto; line-height: 1.5;
      transform: translateY(0); transition: all 0.3s;
    }

    .toast-hidden { display: none; }

    .toast-success {
      background: rgba(42,124,107,0.9);
      border-color: var(--verdigris-lt); color: var(--parch);
    }

    .toast-error {
      background: rgba(120,40,40,0.92);
      border-color: #8b3a3a; color: var(--parch);
    }

    .toast-info {
      background: rgba(26,19,10,0.92);
      border-color: var(--copper); color: var(--parch-dark);
    }

    .wallet-modal-panel { max-width: 420px; width: calc(100% - 2rem); }
    .wallet-modal-list {
      display: flex;
      flex-direction: column;
      gap: 0.55rem;
    }
    .wallet-row {
      display: flex;
      align-items: center;
      gap: 0.85rem;
      width: 100%;
      text-align: left;
      padding: 0.85rem 1rem;
      border: 1px solid rgba(176,106,26,0.35);
      border-radius: 2px;
      background: rgba(26,19,10,0.05);
      color: var(--ink);
      cursor: pointer;
      font-family: var(--ff-caps);
      letter-spacing: 0.08em;
    }
    .wallet-row:hover {
      border-color: var(--copper-bright);
      box-shadow: 0 0 12px rgba(176,106,26,0.18);
    }
    .wallet-row-primary {
      text-decoration: none;
      border-color: var(--copper-bright);
      background: rgba(176,106,26,0.12);
    }
    .wallet-modal-hint {
      font-family: var(--ff-body);
      font-size: 0.82rem;
      font-style: italic;
      color: var(--ink-mid);
      margin-bottom: 0.75rem;
      line-height: 1.45;
      display: none;
    }
    .wallet-row-icon {
      width: 28px; height: 28px; border-radius: 6px; flex-shrink: 0; object-fit: cover;
    }
    .wallet-row-name { flex: 1; font-size: 0.95rem; }
    .wallet-row-state {
      font-size: 0.65rem;
      letter-spacing: 0.14em;
      color: var(--verdigris);
    }
    .wallet-empty {
      font-family: var(--ff-body);
      font-style: italic;
      color: var(--ink-light);
      padding: 0.8rem 0.2rem;
    }

    @media (max-width: 700px) {
      .wallet-modal-panel {
        max-height: min(70vh, 520px);
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
      }
      .wallet-row { min-height: 52px; }
    }

    /* ─── LOADING OVERLAY ─────────────────────────────────────────────── */
    .loading-overlay {
      position: fixed; inset: 0; z-index: 9000;
      display: flex; align-items: center; justify-content: center;
      background: rgba(13,10,6,0.7); backdrop-filter: blur(4px);
    }

    .loading-inner {
      background: linear-gradient(145deg, rgba(242,232,208,0.95), rgba(232,217,180,0.9));
      border: 1px solid var(--copper);
      border-radius: 2px; padding: 2.4rem 3rem;
      display: flex; flex-direction: column; align-items: center; gap: 1rem;
      box-shadow: 0 8px 40px var(--shadow-deep);
    }

    .loading-inner p {
      font-family: var(--ff-body); font-style: italic; font-size: 0.95rem;
      color: var(--ink-mid);
    }

    .spin {
      width: 40px; height: 40px;
      color: var(--copper);
      animation: spin-anim 0.9s linear infinite;
    }

    @keyframes spin-anim {
      from { transform: rotate(0deg); }
      to   { transform: rotate(360deg); }
    }

    /* ─── SPARK PARTICLES ─────────────────────────────────────────────── */
    .spark {
      position: fixed; width: 3px; height: 3px; border-radius: 50%;
      background: var(--arc); pointer-events: none; z-index: 9998;
      box-shadow: 0 0 6px var(--arc), 0 0 12px var(--arc);
      animation: spark-fade 0.8s ease forwards;
    }

    @keyframes spark-fade {
      0%   { transform: translate(0,0) scale(1); opacity: 1; }
      100% { transform: translate(var(--dx), var(--dy)) scale(0); opacity: 0; }
    }

    /* ─── NFT BOOST CALLOUT ─────────────────────────────────────────── */
    .nft-boost-callout {
      display: inline-flex; align-items: center; gap: 0.6rem;
    }

    .nft-boost-icon {
      font-size: 1rem; line-height: 1;
      filter: drop-shadow(0 0 6px rgba(58,168,143,0.7));
      flex-shrink: 0;
    }

    .nft-boost-text {
      font-family: var(--ff-head); font-size: 0.85rem; font-weight: 600;
      letter-spacing: 0.07em; color: var(--verdigris);
    }

    .nft-boost-text em {
      font-style: normal; font-weight: 700;
      color: var(--verdigris-lt);
      text-shadow: 0 0 12px rgba(58,168,143,0.4);
    }

    /* ─── NFT STATUS PILL ────────────────────────────────────────────── */
    .nft-status {
      font-family: var(--ff-caps); font-size: 0.68rem; letter-spacing: 0.18em;
      padding: 0.55rem 1rem; border: 1px solid; border-radius: 2px;
    }

    .nft-found {
      color: var(--verdigris); border-color: rgba(42,124,107,0.35);
      background: rgba(42,124,107,0.06);
    }

    .nft-none {
      color: var(--ink-light); border-color: rgba(176,106,26,0.25);
      background: rgba(176,106,26,0.04);
    }

    /* ─── PREDICTED YIELD BOX ───────────────────────────────────────── */
    .predicted-yield-box {
      border: 1px solid rgba(42,124,107,0.35);
      border-radius: 2px;
      background: rgba(42,124,107,0.05);
      padding: 0.9rem 1rem 0.4rem;
    }

    .py-header {
      display: flex; align-items: center; gap: 0.5rem;
      margin-bottom: 0.5rem;
    }

    .py-live-dot {
      width: 7px; height: 7px; border-radius: 50%;
      background: var(--verdigris-lt);
      box-shadow: 0 0 6px var(--verdigris-lt);
      animation: pulse-dot 2s ease-in-out infinite;
      flex-shrink: 0;
    }

    @keyframes pulse-dot {
      0%, 100% { opacity: 1; }
      50%       { opacity: 0.3; }
    }

    .py-rows { display: flex; flex-direction: column; }

    /* ─── SCROLLBAR ───────────────────────────────────────────────────── */
    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-track { background: var(--parch-dark); }
    ::-webkit-scrollbar-thumb { background: var(--copper); border-radius: 3px; }
    ::-webkit-scrollbar-thumb:hover { background: var(--copper-bright); }

    ::selection { background: rgba(176,106,26,0.25); color: var(--ink); }

    /* ─── RESPONSIVE ──────────────────────────────────────────────────── */
    @media (max-width: 900px) {
      html { font-size: 20px; }
      :root { --nav-h: 70px; }
      .stats-grid { grid-template-columns: repeat(2, 1fr); }
      .dual-grid  { grid-template-columns: 1fr; }
    }

    @media (max-width: 700px) {
      html { font-size: 16px; }
      :root { --nav-h: 60px; }
      .site-wrapper { padding: 1.5rem 1rem 3rem; }
      .stake-card { padding: 1.2rem 1rem; margin-bottom: 1rem; }
      .page-hero { padding: 2rem 0 1.5rem; }
      .page-title { font-size: clamp(2.8rem, 10vw, 4.5rem); }
      .page-sub { font-size: 0.95rem; }
      .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 0;
        border: 1px solid rgba(176,106,26,0.15);
        border-radius: 2px;
        overflow: hidden;
      }
      .stat-entry {
        padding: 0.75rem 0.8rem;
        border-bottom: 1px solid rgba(176,106,26,0.13);
      }
      .stat-entry:nth-child(odd) { border-right: 1px solid rgba(176,106,26,0.13); }
      .stat-entry:nth-child(even) { border-right: none; }
      .stat-entry:last-child:nth-child(odd) { border-right: none; }
      .stat-value { font-size: 1.1rem; }
      .capacity-section { margin-bottom: 1rem; }
      .pool-meta { flex-direction: column; gap: 0.6rem; }
      .pool-meta-item { gap: 0.15rem; }
      .mono-small {
        font-size: 0.62rem;
        word-break: break-all;
        overflow-wrap: break-word;
        max-width: 100%;
      }
      .card-header { margin-bottom: 1rem; padding-bottom: 0.75rem; }
      .card-header-title { font-size: 1rem; }
      .chronicle-row { padding: 0.6rem 0; }
      .chronicle-row > span:first-child { font-size: 0.68rem; }
      .tab { font-size: 0.68rem; letter-spacing: 0.12em; }
      .stake-btn, .unstake-btn {
        padding: 0.8rem 1rem;
        font-size: 0.72rem;
        letter-spacing: 0.16em;
      }
      .action-input { font-size: 0.95rem; padding: 0.6rem 0.8rem; }
      .dual-grid { gap: 1rem; }
      .dev-grid { grid-template-columns: 1fr; }
      .toast { right: 0.8rem; left: 0.8rem; max-width: none; }
    }

    @media (max-width: 480px) {
      .stats-grid { grid-template-columns: 1fr 1fr; }
      .stat-entry { padding: 0.65rem 0.7rem; }
    }

    @media (max-width: 400px) {
      html { font-size: 15px; }
      :root { --nav-h: 54px; }
      .site-wrapper { padding: 1.2rem 0.8rem 2.5rem; }
      .stake-card { padding: 1rem 0.85rem; }
    }

    /* ─── FOOTER ──────────────────────────────────────────────────────── */
    .site-footer {
      background: linear-gradient(180deg, transparent, rgba(26,19,10,0.06));
      border-top: 1px solid rgba(176,106,26,0.25);
      padding: 3rem 0;
      margin-top: 2rem;
    }

    .footer-inner {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 1.2rem;
      text-align: center;
    }

    .footer-brand {
      font-family: var(--ff-title);
      font-size: 1.7rem;
      font-weight: 700;
      color: var(--ink-mid);
      letter-spacing: 0.08em;
    }

    .x-button {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      font-family: var(--ff-caps);
      font-size: 0.85rem;
      letter-spacing: 0.14em;
      color: var(--parch-dark);
      background: rgba(26,19,10,0.85);
      border: 1px solid var(--copper);
      padding: 0.6rem 1.4rem;
      text-decoration: none;
      border-radius: 2px;
      transition: color 0.25s, border-color 0.25s, box-shadow 0.25s;
    }

    .x-button:hover {
      color: var(--gold-lt);
      border-color: var(--gold-lt);
      box-shadow: 0 0 16px rgba(200,146,42,0.25);
    }
  </style>
</head>
<body>

  <!-- ── MAP GRID ───────────────────────────────────────────────────── -->
  <div class="map-grid" aria-hidden="true"></div>

  <!-- ── ELECTRIC ARCS ──────────────────────────────────────────────── -->
  <div class="arc-layer" aria-hidden="true">
    <svg viewBox="0 0 1440 900" preserveAspectRatio="xMidYMid slice" fill="none" xmlns="http://www.w3.org/2000/svg">
      <defs>
        <filter id="arc-glow-f">
          <feGaussianBlur stdDeviation="3" result="blur"/>
          <feComposite in="SourceGraphic" in2="blur" operator="over"/>
        </filter>
      </defs>
      <path d="M0 900 Q 180 600 360 400 Q 480 260 600 180" stroke="rgba(127,212,248,0.12)" stroke-width="1" filter="url(#arc-glow-f)">
        <animate attributeName="stroke-dasharray" values="0,2000;2000,0;0,2000" dur="8s" repeatCount="indefinite"/>
      </path>
      <path d="M1440 0 Q 1200 200 1080 350 Q 960 480 840 600 Q 720 720 660 900" stroke="rgba(127,212,248,0.09)" stroke-width="1" filter="url(#arc-glow-f)">
        <animate attributeName="stroke-dasharray" values="0,2000;2000,0;0,2000" dur="11s" begin="2s" repeatCount="indefinite"/>
      </path>
    </svg>
  </div>

  <!-- ── COMPASS WATERMARK ────────────────────────────────────────────── -->
  <svg class="compass-watermark" aria-hidden="true" viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg">
    <circle cx="100" cy="100" r="96" stroke="#3b2a18" stroke-width="1.5"/>
    <circle cx="100" cy="100" r="80" stroke="#3b2a18" stroke-width="0.5"/>
    <circle cx="100" cy="100" r="60" stroke="#3b2a18" stroke-width="0.5"/>
    <line x1="100" y1="4" x2="100" y2="196" stroke="#3b2a18" stroke-width="0.5"/>
    <line x1="4" y1="100" x2="196" y2="100" stroke="#3b2a18" stroke-width="0.5"/>
    <line x1="32" y1="32" x2="168" y2="168" stroke="#3b2a18" stroke-width="0.3"/>
    <line x1="168" y1="32" x2="32" y2="168" stroke="#3b2a18" stroke-width="0.3"/>
    <polygon points="100,8 94,100 100,88 106,100" fill="#3b2a18"/>
    <polygon points="100,192 106,100 100,112 94,100" fill="#3b2a18" opacity="0.4"/>
    <polygon points="192,100 100,94 112,100 100,106" fill="#3b2a18" opacity="0.4"/>
    <polygon points="8,100 100,106 88,100 100,94" fill="#3b2a18" opacity="0.4"/>
    <circle cx="100" cy="100" r="5" fill="#3b2a18"/>
    <text x="100" y="22" text-anchor="middle" font-size="9" fill="#3b2a18" font-family="serif">N</text>
    <text x="100" y="186" text-anchor="middle" font-size="9" fill="#3b2a18" font-family="serif">S</text>
    <text x="183" y="104" text-anchor="middle" font-size="9" fill="#3b2a18" font-family="serif">E</text>
    <text x="17" y="104" text-anchor="middle" font-size="9" fill="#3b2a18" font-family="serif">W</text>
  </svg>

  <!-- ── PARCHMENT WRAPPER ─────────────────────────────────────────────── -->
  <div class="parchment">

    <!-- ─ NAV ─────────────────────────────────────────────────────────── -->
    @include('partials.nav', ['navConnectBtn' => true])

    <!-- ─ MAIN ───────────────────────────────────────────────────────── -->
    <main class="main-scroll">
      <div class="site-wrapper">

        <!-- Page Hero -->
        <section class="page-hero" aria-label="Page header">
          <span class="page-kicker">The Vault</span>
          <h1 class="page-title">Staking</h1>
          <p class="page-sub">Commit your TZLA to the vault and earn proportional rewards.</p>
          <div class="page-ornament" aria-hidden="true">
            <span></span>
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
              <circle cx="8" cy="8" r="7" stroke="#b06a1a" stroke-width="1"/>
              <line x1="8" y1="1" x2="8" y2="15" stroke="#b06a1a" stroke-width="0.75"/>
              <line x1="1" y1="8" x2="15" y2="8" stroke="#b06a1a" stroke-width="0.75"/>
              <polygon points="8,2 7,8 8,7 9,8" fill="#b06a1a"/>
            </svg>
            <span></span>
          </div>
          <p class="page-motto">DeFi For The People</p>
        </section>

        <!-- Pool Chronicle -->
        <section class="stake-card" aria-label="Pool overview">
          <div class="card-header">
            <span class="card-header-title">Pool Chronicle</span>
            <span id="poolStatus" class="pool-status">Loading…</span>
          </div>

          <div class="stats-grid">
            <div class="stat-entry">
              <span class="stat-label">Total Staked</span>
              <span class="stat-value copper" id="poolTotalStaked">–</span>
            </div>
            <div class="stat-entry">
              <span class="stat-label">Stake Cap</span>
              <span class="stat-value" id="poolStakeCap">–</span>
            </div>
            <div class="stat-entry">
              <span class="stat-label">TZLA Distributed</span>
              <span class="stat-value verdigris" id="poolDistributed">–</span>
              <span class="stat-subvalue" id="poolDistributedUsd"></span>
            </div>
          </div>

          <div class="capacity-section">
            <span class="stat-label">Pool Capacity</span>
            <div class="capacity-track" role="progressbar" aria-label="Pool capacity">
              <div id="capacityBar" class="capacity-fill"></div>
            </div>
          </div>

          <div class="pool-meta">
            <div class="pool-meta-item">
              <span class="stat-label">Stake Token Mint</span>
              <span class="mono-small" id="poolMint">–</span>
            </div>
            <div class="pool-meta-item">
              <span class="stat-label">NFT Collection</span>
              <span class="mono-small" id="poolNftCollection">–</span>
            </div>
            <div class="pool-meta-item">
              <span class="stat-label">NFT Boost</span>
              <span class="mono-small" style="color:var(--verdigris)">⚡ 2× for TZLA NFT holders</span>
            </div>
          </div>
        </section>

        <!-- Rates + Actions -->
        <div class="dual-grid">

          <!-- Tide Tables — yield rate by NFT tier -->
          <section class="stake-card tide-card" aria-label="Yield rate by NFT tier">
            <div class="card-header">
              <span class="card-header-title">Tide Tables</span>
              <span class="section-label">Daily Yield</span>
            </div>

            <div class="rate-ladder">
              <div class="rate-row">
                <div class="rate-meta">
                  <span class="rate-name">Normal Rate</span>
                  <span class="rate-pct copper">0.069%</span>
                </div>
              </div>

              <div class="rate-row">
                <div class="rate-meta">
                  <span class="rate-name"><span class="rate-glyph">◈</span>1 TZLA NFT</span>
                  <span class="rate-pct gold">0.111%</span>
                </div>
              </div>

              <div class="rate-row">
                <div class="rate-meta">
                  <span class="rate-name"><span class="rate-glyph">◈◈</span>2 TZLA NFT</span>
                  <span class="rate-pct gold">0.222%</span>
                </div>
              </div>

              <div class="rate-row">
                <div class="rate-meta">
                  <span class="rate-name"><span class="rate-glyph">◈×10</span>10+ TZLA NFT</span>
                  <span class="rate-pct gold">0.330%</span>
                </div>
              </div>

              <div class="rate-row">
                <div class="rate-meta">
                  <span class="rate-name"><span class="rate-glyph">★</span>Golden Ticket</span>
                  <span class="rate-pct verdigris">0.369%</span>
                </div>
              </div>
            </div>

            <a href="{{ route('portal', 'nft') }}" class="nft-cta">Buy TZLA NFTs</a>
          </section>

          <!-- Actions -->
          <section class="stake-card actions-card" aria-label="Staking actions">
            <div class="card-header">
              <span class="card-header-title">Voyage Controls</span>
            </div>

            <div class="tab-bar" role="tablist">
              <button onclick="showTab('stake')" id="tabStake"
                      class="tab active-tab" role="tab" aria-selected="true">Stake</button>
              <button onclick="showTab('unstake')" id="tabUnstake"
                      class="tab" role="tab" aria-selected="false">Unstake</button>
            </div>

            <!-- Stake panel -->
            <div id="panelStake" class="action-panel" role="tabpanel">
              <div class="input-group">
                <div class="input-header">
                  <label class="stat-label" for="stakeAmount">Amount (TZLA tokens)</label>
                  <button onclick="setMaxStake()" class="max-btn">Max</button>
                </div>
                <input id="stakeAmount" type="number" min="0" step="any" placeholder="0.000"
                       class="action-input" autocomplete="off" />
              </div>

              <div id="nftBoostStatus" style="display:none" class="nft-status nft-none"></div>

              <p class="action-note" style="color:#8a8a8a;">0.00369 SOL staking fee</p>
              <button onclick="stakeTokens()" class="stake-btn">Cast Anchor</button>
            </div>

            <!-- Unstake panel -->
            <div id="panelUnstake" class="action-panel hidden" role="tabpanel">
              <div class="input-group">
                <div class="input-header">
                  <label class="stat-label" for="unstakeAmount">Amount (TZLA tokens)</label>
                  <button onclick="setMaxUnstake()" class="max-btn">Max</button>
                </div>
                <input id="unstakeAmount" type="number" min="0" step="any" placeholder="0.000"
                       class="action-input" autocomplete="off" />
              </div>

              <div id="unstakeBoostStatus" style="display:none" class="nft-status nft-none"></div>

              <!-- Predicted yield box — shown when user has an active stake -->
              <div id="predictedYieldBox" class="predicted-yield-box" style="display:none">
                <div class="py-header">
                  <span class="stat-label">Predicted Yield</span>
                  <span class="py-live-dot" title="Live calculation"></span>
                </div>
                <div class="py-rows">
                  <div class="chronicle-row small">
                    <span>Staked since</span>
                    <span id="pyStakedSince">–</span>
                  </div>
                  <div class="chronicle-row small">
                    <span>Time staked</span>
                    <span id="pyElapsed">–</span>
                  </div>
                  <div class="chronicle-row">
                    <span>Claimable rewards</span>
                    <span class="stat-value verdigris" id="pyYield">–</span>
                  </div>
                </div>
              </div>

              <button type="button" onclick="claimRewards()" class="claim-btn">Claim Rewards</button>
              <button onclick="unstakeTokens()" class="unstake-btn">Weigh Anchor</button>
            </div>

          </section>

        </div>{{-- /dual-grid --}}

        <!-- Earnings Summary -->
        <section class="stake-card earnings-summary-card" aria-label="Earnings summary card">
            <div class="card-header">
              <span class="card-header-title">Earnings Summary</span>
            </div>

            <p id="earningsSummaryEmpty" class="earnings-summary-empty">
              Connect your wallet to generate a shareable earnings card.
            </p>

            <div id="earningsSummaryBody" class="earnings-summary-body">
              <div class="earnings-summary-preview-wrap">
                <img id="earningsSummaryPreview" alt="Staking earnings summary card" />
                <div id="earningsSummaryLoading" class="earnings-summary-loading">Generating card…</div>
              </div>

              <div class="earnings-summary-actions">
                <button type="button" class="card-btn" onclick="copyCard()">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                  Copy
                </button>
                <button type="button" class="card-btn" onclick="saveCard()">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                  Save
                </button>
                <button type="button" class="card-btn" onclick="openStakingCardModal()">
                  Change Character
                </button>
              </div>
            </div>
        </section>

        <!-- Your Position -->
        <section class="stake-card" aria-label="Your staking position">
            <div class="card-header">
              <span class="card-header-title">Expedition Log</span>
            </div>

            <div id="walletSection" class="wallet-display hidden">
              <span class="stat-label">Connected Wallet</span>
              <p class="mono-small" id="walletAddr">–</p>
            </div>

            <div class="chronicle-list">
              <!-- <div class="chronicle-row">
                <span>Token Balance</span>
                <span id="userTokenBalance">–</span>
              </div> -->
              <div class="chronicle-row">
                <span>TZLA NFTs</span>
                <span class="stat-value arc" id="userNftCount">–</span>
              </div>
              <div class="chronicle-row">
                <span>Staked</span>
                <span id="userStaked">–</span>
              </div>
              <!-- <div class="chronicle-row">
                <span>NFT Boost</span>
                <span id="nftBoostBadge" class="boost-badge boost-inactive">No stake yet</span>
              </div> -->
              <div class="chronicle-row">
                <span>Pending Rewards</span>
                <span class="stat-value verdigris" id="userPendingRewards">–</span>
              </div>
              <div class="chronicle-row">
                <span>Est. Yield</span>
                <span id="userDPY">–</span>
              </div>
            </div>

            <button type="button" onclick="claimRewards()" class="claim-btn">Claim Rewards</button>

            <button onclick="refreshStats()" class="refresh-btn">
              Refresh Position
            </button>
        </section>


      </div>{{-- /site-wrapper --}}
    </main>

    @include('partials.footer')

  </div>{{-- /parchment --}}

  <!-- Staking Card Modal -->
  <div id="stakingCardModal" class="card-modal-overlay" aria-hidden="true" role="dialog" aria-labelledby="cardModalTitle">
    <div class="card-modal-backdrop" onclick="closeStakingCardModal()"></div>
    <div class="card-modal-panel">
      <div class="card-modal-header">
        <h2 id="cardModalTitle" class="card-modal-title">Choose Character</h2>
        <button type="button" class="card-modal-close" onclick="closeStakingCardModal()" aria-label="Close">&times;</button>
      </div>

      <div class="card-preview-wrap">
        <canvas id="stakingCardCanvas" width="1200" height="675" style="display:none"></canvas>
        <img id="stakingCardPreview" alt="Staking rewards card preview" style="display:none">
        <div id="cardModalLoading" class="card-modal-loading">Generating card…</div>
      </div>

      <div id="cardBgPicker" class="card-bg-picker"></div>

      <div class="card-modal-actions">
        <button type="button" class="card-btn" onclick="copyCard()">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
          Copy
        </button>
        <button type="button" class="card-btn" onclick="saveCard()">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
          Save
        </button>
      </div>
    </div>
  </div>

  <div id="walletModal" class="card-modal-overlay" aria-hidden="true" role="dialog" aria-labelledby="walletModalTitle">
    <div class="card-modal-backdrop" onclick="closeWalletModal()"></div>
    <div class="card-modal-panel wallet-modal-panel">
      <div class="card-modal-header">
        <h2 id="walletModalTitle" class="card-modal-title">Connect Wallet</h2>
        <button type="button" class="card-modal-close" onclick="closeWalletModal()" aria-label="Close">&times;</button>
      </div>
      <div id="walletModalHint" class="wallet-modal-hint"></div>
      <div id="walletModalList" class="wallet-modal-list"></div>
    </div>
  </div>

  <!-- Toast -->
  <div id="toast" class="toast toast-hidden" role="alert" aria-live="polite">
    <span id="toastMsg"></span>
  </div>

  <!-- Loading Overlay -->
  <div id="loadingOverlay" style="display:none" class="loading-overlay" aria-label="Awaiting wallet approval">
    <div class="loading-inner">
      <svg class="spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" stroke-dasharray="40 20"/>
      </svg>
      <p>Awaiting wallet approval…</p>
    </div>
  </div>

  <script>
    /* ── TAB SWITCHING ────────────────────────────────────────────────── */
    function showTab(name) {
      const panels = ['Stake', 'Unstake'];
      panels.forEach(p => {
        const panel = document.getElementById('panel' + p);
        const tab   = document.getElementById('tab'   + p);
        const isActive = p.toLowerCase() === name;
        if (panel) panel.classList.toggle('hidden', !isActive);
        if (tab) {
          tab.classList.toggle('active-tab', isActive);
          tab.setAttribute('aria-selected', isActive);
        }
      });
      if (name === 'unstake' && typeof window.refreshUnstakeYield === 'function') {
        window.refreshUnstakeYield();
      }
    }

    /* ── SPARK PARTICLES ON CLICK ─────────────────────────────────────── */
    document.addEventListener('click', (e) => {
      if (e.target.closest('input, button.nav-toggle, .tab, .action-input')) return;
      const count = 5 + Math.floor(Math.random() * 4);
      for (let i = 0; i < count; i++) {
        const el = document.createElement('div');
        el.className = 'spark';
        const angle = (Math.PI * 2 * i) / count + (Math.random() - 0.5) * 0.5;
        const dist  = 18 + Math.random() * 36;
        el.style.cssText = `
          left: ${e.clientX}px; top: ${e.clientY}px;
          --dx: ${Math.cos(angle) * dist}px;
          --dy: ${Math.sin(angle) * dist}px;
          animation-duration: ${0.5 + Math.random() * 0.4}s;
          width: ${1 + Math.random() * 2.5}px; height: ${1 + Math.random() * 2.5}px;
        `;
        document.body.appendChild(el);
        el.addEventListener('animationend', () => el.remove());
      }
    });


    /* ── POOL STATUS CLASS ────────────────────────────────────────────── */
    const poolStatusEl = document.getElementById('poolStatus');
    const observer = new MutationObserver(() => {
      if (poolStatusEl && poolStatusEl.textContent === 'Active') {
        poolStatusEl.classList.add('active');
      }
    });
    if (poolStatusEl) observer.observe(poolStatusEl, { childList: true, characterData: true, subtree: true });
  </script>
</body>
</html>
