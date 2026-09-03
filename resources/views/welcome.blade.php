<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>TZLA</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;0,700;1,300;1,400;1,600&family=Cormorant+SC:wght@300;400;600&family=Spectral:ital,wght@0,300;0,400;0,600;1,300;1,400&display=swap" rel="stylesheet" />
  @include('partials.nav-css')
  <style>
    /* ─── TOKENS ──────────────────────────────────────────────────────── */
    :root {
      --parch:        #f2e8d0;
      --parch-dark:   #e8d9b4;
      --parch-deeper: #d4c090;
      --ink:          #1a130a;
      --ink-mid:      #3b2a18;
      --ink-light:    #6b4f30;
      --copper:       #b06a1a;
      --copper-bright:#d4882a;
      --verdigris:    #2a7c6b;
      --verdigris-lt: #3aa88f;
      --arc:          #7fd4f8;
      --arc-glow:     #b8ecff;
      --gold:         #c8922a;
      --gold-lt:      #f0c060;
      --shadow:       rgba(26, 19, 10, 0.18);
      --shadow-deep:  rgba(26, 19, 10, 0.35);
      --ff-title:     'Cormorant Garamond', serif;
      --ff-head:      'Cormorant SC', serif;
      --ff-body:      'Spectral', serif;
      --ff-caps:      'Cormorant SC', serif;
    }

    /* ─── RESET & BASE ────────────────────────────────────────────────── */
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

    /* ─── NOISE TEXTURE OVERLAY ───────────────────────────────────────── */
    body::before {
      content: '';
      position: fixed;
      inset: 0;
      pointer-events: none;
      z-index: 9999;
      opacity: 0.03;
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

    /* aged edge vignette */
    .parchment::after {
      content: '';
      position: fixed;
      inset: 0;
      pointer-events: none;
      background: radial-gradient(ellipse at 50% 50%, transparent 55%, rgba(26,19,10,0.55) 100%);
      z-index: 100;
    }

    /* ─── MAP GRID LINES (FAINT) ──────────────────────────────────────── */
    .map-grid {
      position: fixed;
      inset: 0;
      pointer-events: none;
      z-index: 0;
      opacity: 0.055;
      background-image:
        linear-gradient(var(--ink-mid) 1px, transparent 1px),
        linear-gradient(90deg, var(--ink-mid) 1px, transparent 1px);
      background-size: 72px 72px;
    }

    /* ─── ELECTRIC ARC SVG BACKGROUND ────────────────────────────────── */
    .arc-layer {
      position: fixed;
      inset: 0;
      pointer-events: none;
      z-index: 1;
      overflow: hidden;
    }

    .arc-layer svg {
      position: absolute;
      width: 100%;
      height: 100%;
    }

    /* ─── COMPASS ROSE WATERMARK ──────────────────────────────────────── */
    .compass-watermark {
      position: fixed;
      bottom: -140px;
      right: -140px;
      width: 520px;
      height: 520px;
      opacity: 0.06;
      pointer-events: none;
      z-index: 1;
    }

    /* ─── LAYOUT CONTAINER ────────────────────────────────────────────── */
    .site-wrapper {
      position: relative;
      z-index: 10;
      max-width: 1340px;
      margin: 0 auto;
      padding: 0 3rem;
    }


    /* ─── HERO ────────────────────────────────────────────────────────── */
    .hero {
      padding: 9rem 0 6rem;
      text-align: center;
      position: relative;
    }

    .hero-eyebrow {
      font-family: var(--ff-caps);
      font-size: 0.9rem;
      letter-spacing: 0.3em;
      color: var(--verdigris);
      margin-bottom: 2rem;
      opacity: 0;
      animation: fade-up 0.8s 0.2s ease forwards;
    }

    .hero-title {
      font-family: var(--ff-title);
      font-size: clamp(4rem, 9vw, 8.5rem);
      font-weight: 700;
      line-height: 1.02;
      color: var(--ink);
      margin-bottom: 0.6rem;
      opacity: 0;
      animation: fade-up 0.9s 0.4s ease forwards;
    }

    .hero-title .electric {
      color: var(--verdigris);
      position: relative;
      display: inline-block;
    }

    .hero-title .electric::after {
      content: '';
      position: absolute;
      bottom: -4px;
      left: 0; right: 0;
      height: 2px;
      background: linear-gradient(90deg, transparent, var(--arc), var(--verdigris-lt), var(--arc), transparent);
      animation: arc-pulse 2.5s ease-in-out infinite;
      box-shadow: 0 0 10px var(--arc), 0 0 20px var(--arc);
    }

    @keyframes arc-pulse {
      0%, 100% { opacity: 0.5; transform: scaleX(0.8); }
      50% { opacity: 1; transform: scaleX(1); }
    }

    .hero-subtitle {
      font-family: var(--ff-body);
      font-style: italic;
      font-weight: 400;
      font-size: 1.45rem;
      color: var(--ink-light);
      max-width: 640px;
      margin: 2rem auto 0;
      line-height: 1.8;
      opacity: 0;
      animation: fade-up 0.9s 0.6s ease forwards;
    }

    .hero-ornament {
      margin: 2.8rem auto 0;
      width: 320px;
      opacity: 0;
      animation: fade-in 1.2s 1s ease forwards;
    }

    @keyframes fade-up {
      from { opacity: 0; transform: translateY(22px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    @keyframes fade-in {
      from { opacity: 0; }
      to   { opacity: 1; }
    }

    /* ─── SECTION DIVIDER ─────────────────────────────────────────────── */
    .section-divider {
      display: flex;
      align-items: center;
      gap: 1.2rem;
      margin: 3.5rem 0;
    }

    .section-divider::before,
    .section-divider::after {
      content: '';
      flex: 1;
      height: 1px;
      background: linear-gradient(90deg, transparent, var(--copper));
    }

    .section-divider::after {
      background: linear-gradient(90deg, var(--copper), transparent);
    }

    .divider-icon {
      font-size: 1rem;
      color: var(--copper);
      flex-shrink: 0;
    }

    /* ─── SECTION HEADER ──────────────────────────────────────────────── */
    .section-header {
      text-align: center;
      margin-bottom: 3rem;
    }

    .section-label {
      font-family: var(--ff-caps);
      font-size: 0.88rem;
      letter-spacing: 0.28em;
      color: var(--copper);
      display: block;
      margin-bottom: 1rem;
    }

    .section-title {
      font-family: var(--ff-head);
      font-weight: 600;
      font-size: clamp(2.4rem, 4.5vw, 3.8rem);
      color: var(--ink);
      letter-spacing: 0.05em;
    }

    /* ─── FEATURE CARDS ───────────────────────────────────────────────── */
    .cards-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 1.8rem;
      margin-bottom: 5rem;
    }

    .card {
      background: linear-gradient(145deg, rgba(242,232,208,0.9), rgba(232,217,180,0.8));
      border: 1px solid rgba(176,106,26,0.35);
      border-radius: 2px;
      padding: 2.8rem;
      position: relative;
      overflow: hidden;
      transition: transform 0.3s, box-shadow 0.3s, border-color 0.3s;
      box-shadow: 0 4px 20px var(--shadow), inset 0 1px 0 rgba(240,192,96,0.2);
    }

    .card::before {
      content: '';
      position: absolute;
      top: 0; left: 0; right: 0;
      height: 2px;
      background: linear-gradient(90deg, transparent, var(--copper), transparent);
      opacity: 0;
      transition: opacity 0.3s;
    }

    .card::after {
      content: '';
      position: absolute;
      inset: 0;
      background: radial-gradient(ellipse at 80% 20%, rgba(127,212,248,0.06) 0%, transparent 60%);
      pointer-events: none;
    }

    .card:hover {
      transform: translateY(-4px);
      box-shadow: 0 12px 40px var(--shadow-deep), 0 0 30px rgba(127,212,248,0.08);
      border-color: rgba(176,106,26,0.7);
    }

    .card:hover::before { opacity: 1; }

    .card-icon {
      width: 54px;
      height: 54px;
      margin-bottom: 1.5rem;
      color: var(--verdigris);
      opacity: 0.85;
    }

    .card-title {
      font-family: var(--ff-head);
      font-size: 1.35rem;
      font-weight: 600;
      letter-spacing: 0.04em;
      color: var(--ink);
      margin-bottom: 0.9rem;
    }

    .card-body {
      font-size: 1.05rem;
      line-height: 1.85;
      color: var(--ink-mid);
      font-style: italic;
      font-weight: 400;
    }

    .card-number {
      position: absolute;
      top: 1.4rem;
      right: 1.8rem;
      font-family: var(--ff-title);
      font-size: 4rem;
      color: var(--copper);
      opacity: 0.08;
      line-height: 1;
      pointer-events: none;
      user-select: none;
    }

    /* ─── QUOTE BLOCK ─────────────────────────────────────────────────── */
    .quote-section {
      margin: 2rem 0 5rem;
      position: relative;
    }

    .quote-block {
      background: linear-gradient(135deg, rgba(26,19,10,0.94), rgba(42,30,14,0.96));
      border: 1px solid var(--copper);
      border-left: 4px solid var(--arc);
      padding: 3rem 3.5rem;
      border-radius: 2px;
      box-shadow: 0 8px 40px var(--shadow-deep), 0 0 60px rgba(127,212,248,0.04);
      position: relative;
      overflow: hidden;
    }

    .quote-block::before {
      content: '';
      position: absolute;
      inset: 0;
      background:
        radial-gradient(ellipse at 0% 50%, rgba(127,212,248,0.08) 0%, transparent 50%),
        radial-gradient(ellipse at 100% 50%, rgba(176,106,26,0.08) 0%, transparent 50%);
      pointer-events: none;
    }

    .quote-mark {
      font-family: var(--ff-title);
      font-size: 5rem;
      color: var(--arc);
      line-height: 0;
      display: block;
      margin-bottom: 1.5rem;
      opacity: 0.5;
    }

    .quote-text {
      font-family: var(--ff-body);
      font-style: italic;
      font-weight: 600;
      font-size: 1.75rem;
      line-height: 1.75;
      color: var(--parch);
      margin-bottom: 1.5rem;
    }

    .quote-attribution {
      font-family: var(--ff-caps);
      font-size: 0.8rem;
      letter-spacing: 0.18em;
      color: var(--copper-bright);
      display: flex;
      align-items: center;
      gap: 1rem;
    }

    .quote-attribution::before {
      content: '';
      display: block;
      width: 48px;
      height: 1px;
      background: var(--copper);
    }

    /* ─── CHRONICLES TABLE (INFO SECTION) ────────────────────────────── */
    .chronicles {
      margin-bottom: 5rem;
    }

    .chronicle-entry {
      display: grid;
      grid-template-columns: 160px 1fr;
      gap: 2rem;
      padding: 1.8rem 0;
      border-bottom: 1px solid rgba(176,106,26,0.2);
      align-items: start;
      transition: background 0.2s;
      position: relative;
    }

    .chronicle-entry:first-of-type { border-top: 1px solid rgba(176,106,26,0.2); }

    .chronicle-entry::after {
      content: '';
      position: absolute;
      left: -2.5rem;
      top: 0; bottom: 0;
      width: 2px;
      background: transparent;
      transition: background 0.2s;
    }

    .chronicle-entry:hover::after {
      background: linear-gradient(180deg, transparent, var(--arc), transparent);
    }

    .chronicle-date {
      font-family: var(--ff-caps);
      font-size: 0.88rem;
      letter-spacing: 0.14em;
      color: var(--copper);
      padding-top: 0.3rem;
    }

    .chronicle-content h3 {
      font-family: var(--ff-head);
      font-size: 1.45rem;
      font-weight: 600;
      color: var(--ink);
      margin-bottom: 0.6rem;
      letter-spacing: 0.03em;
    }

    .chronicle-content p {
      font-size: 1.05rem;
      line-height: 1.85;
      color: var(--ink-mid);
      font-style: italic;
      font-weight: 400;
    }

    .chronicle-tag {
      display: inline-block;
      margin-top: 0.6rem;
      font-family: var(--ff-caps);
      font-size: 0.7rem;
      letter-spacing: 0.16em;
      color: var(--verdigris);
      border: 1px solid rgba(42,124,107,0.4);
      padding: 0.2rem 0.7rem;
      border-radius: 1px;
    }

    /* ─── EXPEDITIONS GRID (LINK CARDS) ──────────────────────────────── */
    .expeditions-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 1.2rem;
      margin-bottom: 5rem;
    }

    .expedition-link {
      display: block;
      background: rgba(242,232,208,0.5);
      border: 1px solid rgba(176,106,26,0.3);
      padding: 1.6rem 1.8rem;
      text-decoration: none;
      color: inherit;
      position: relative;
      overflow: hidden;
      transition: all 0.3s;
      border-radius: 2px;
    }

    .expedition-link::before {
      content: '';
      position: absolute;
      inset: 0;
      background: linear-gradient(135deg, rgba(127,212,248,0.05), transparent);
      opacity: 0;
      transition: opacity 0.3s;
    }

    .expedition-link:hover {
      background: rgba(242,232,208,0.8);
      border-color: var(--copper-bright);
      transform: translateX(4px);
      box-shadow: -4px 0 0 var(--arc), 4px 4px 20px var(--shadow);
    }

    .expedition-link:hover::before { opacity: 1; }

    .expedition-domain {
      font-family: var(--ff-caps);
      font-size: 0.72rem;
      letter-spacing: 0.18em;
      color: var(--verdigris);
      margin-bottom: 0.5rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .expedition-domain::before {
      content: '';
      display: block;
      width: 6px;
      height: 6px;
      border-radius: 50%;
      background: var(--arc);
      box-shadow: 0 0 6px var(--arc);
      flex-shrink: 0;
    }

    .expedition-name {
      font-family: var(--ff-head);
      font-size: 1.25rem;
      font-weight: 600;
      color: var(--ink);
      margin-bottom: 0.5rem;
      letter-spacing: 0.02em;
    }

    .expedition-desc {
      font-size: 1rem;
      color: var(--ink-light);
      line-height: 1.75;
      font-style: italic;
      font-weight: 400;
    }

    .expedition-arrow {
      position: absolute;
      top: 50%;
      right: 1.4rem;
      transform: translateY(-50%) translateX(4px);
      color: var(--copper);
      opacity: 0;
      transition: all 0.3s;
      font-size: 1.1rem;
    }

    .expedition-link:hover .expedition-arrow {
      opacity: 1;
      transform: translateY(-50%) translateX(0);
    }

    /* ─── HERO ────────────────────────────────────────────────────────── */
    .site-wrapper {
      position: relative;
      z-index: 10;
      max-width: 1340px;
      margin: 0 auto;
      padding: 0 3rem;
    }

    .hero {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 2.5rem;
      padding: 10rem 0 8rem;
      text-align: center;
    }

    .hero-title {
      font-family: var(--ff-title);
      font-size: clamp(5rem, 14vw, 12rem);
      font-weight: 700;
      line-height: 1;
      color: var(--ink);
      letter-spacing: 0.06em;
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

    .footer-coord {
      font-family: var(--ff-caps);
      font-size: 0.75rem;
      letter-spacing: 0.18em;
      color: var(--copper);
    }

    .footer-note {
      font-family: var(--ff-body);
      font-style: italic;
      font-weight: 300;
      font-size: 0.95rem;
      color: var(--ink-light);
      max-width: 500px;
      line-height: 1.8;
    }

    /* ─── X BUTTON ───────────────────────────────────────────────────── */
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
      color: var(--arc-glow);
      border-color: var(--arc);
      box-shadow: 0 0 16px rgba(127,212,248,0.2);
    }

    /* ─── SPARK PARTICLES ─────────────────────────────────────────────── */
    .spark {
      position: fixed;
      width: 3px;
      height: 3px;
      border-radius: 50%;
      background: var(--arc);
      pointer-events: none;
      z-index: 9998;
      box-shadow: 0 0 6px var(--arc), 0 0 12px var(--arc);
      animation: spark-fade 0.8s ease forwards;
    }

    @keyframes spark-fade {
      0%   { transform: translate(0,0) scale(1); opacity: 1; }
      100% { transform: translate(var(--dx), var(--dy)) scale(0); opacity: 0; }
    }

    /* ─── SCROLLBAR ───────────────────────────────────────────────────── */
    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-track { background: var(--parch-dark); }
    ::-webkit-scrollbar-thumb { background: var(--copper); border-radius: 3px; }
    ::-webkit-scrollbar-thumb:hover { background: var(--copper-bright); }

    /* ─── SELECTION ───────────────────────────────────────────────────── */
    ::selection {
      background: rgba(127,212,248,0.3);
      color: var(--ink);
    }

    /* ─── RESPONSIVE ──────────────────────────────────────────────────── */
    @media (max-width: 900px) {
      html { font-size: 20px; }
      .hero { padding: 7rem 0 5rem; }
      .cards-grid { grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); }
    }

    @media (max-width: 700px) {
      html { font-size: 17px; }
      .site-wrapper { padding: 0 1.2rem; }
      .hero { padding: 5rem 0 3.5rem; gap: 1.8rem; }
      .hero-subtitle { font-size: 1.15rem; max-width: 100%; }
      .quote-block { padding: 1.8rem 1.5rem; }
      .quote-text { font-size: 1.4rem; }
      .chronicle-entry { grid-template-columns: 1fr; gap: 0.4rem; }
      .chronicle-entry::after { display: none; }
      .card { padding: 2rem 1.6rem; }
      .hero-ornament { width: 220px; }
    }

    @media (max-width: 400px) {
      html { font-size: 15px; }
      .hero { padding: 4rem 0 2.5rem; }
    }
  </style>
</head>
<body>
  <!-- ── MAP GRID ─────────────────────────────────────────────────── -->
  <div class="map-grid" aria-hidden="true"></div>

  <!-- ── ELECTRIC ARCS ────────────────────────────────────────────── -->
  <div class="arc-layer" aria-hidden="true">
    <svg viewBox="0 0 1440 900" preserveAspectRatio="xMidYMid slice" fill="none" xmlns="http://www.w3.org/2000/svg">
      <defs>
        <filter id="arc-glow">
          <feGaussianBlur stdDeviation="3" result="blur"/>
          <feComposite in="SourceGraphic" in2="blur" operator="over"/>
        </filter>
      </defs>
      <!-- faint arcs emanating from corners -->
      <path d="M0 900 Q 180 600 360 400 Q 480 260 600 180" stroke="rgba(127,212,248,0.12)" stroke-width="1" filter="url(#arc-glow)">
        <animate attributeName="stroke-dasharray" values="0,2000;2000,0;0,2000" dur="8s" repeatCount="indefinite"/>
      </path>
      <path d="M1440 0 Q 1200 200 1080 350 Q 960 480 840 600 Q 720 720 660 900" stroke="rgba(127,212,248,0.09)" stroke-width="1" filter="url(#arc-glow)">
        <animate attributeName="stroke-dasharray" values="0,2000;2000,0;0,2000" dur="11s" begin="2s" repeatCount="indefinite"/>
      </path>
      <path d="M720 0 Q 760 150 700 300 Q 660 400 680 500" stroke="rgba(42,168,143,0.1)" stroke-width="1" filter="url(#arc-glow)">
        <animate attributeName="stroke-dasharray" values="0,600;600,0;0,600" dur="6s" begin="1s" repeatCount="indefinite"/>
      </path>
    </svg>
  </div>

  <!-- ── COMPASS WATERMARK ─────────────────────────────────────────── -->
  <svg class="compass-watermark" aria-hidden="true" viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg">
    <circle cx="100" cy="100" r="96" stroke="#3b2a18" stroke-width="1.5"/>
    <circle cx="100" cy="100" r="80" stroke="#3b2a18" stroke-width="0.5"/>
    <circle cx="100" cy="100" r="60" stroke="#3b2a18" stroke-width="0.5"/>
    <line x1="100" y1="4" x2="100" y2="196" stroke="#3b2a18" stroke-width="0.5"/>
    <line x1="4" y1="100" x2="196" y2="100" stroke="#3b2a18" stroke-width="0.5"/>
    <line x1="32" y1="32" x2="168" y2="168" stroke="#3b2a18" stroke-width="0.3"/>
    <line x1="168" y1="32" x2="32" y2="168" stroke="#3b2a18" stroke-width="0.3"/>
    <!-- N pointer -->
    <polygon points="100,8 94,100 100,88 106,100" fill="#3b2a18"/>
    <!-- S pointer -->
    <polygon points="100,192 106,100 100,112 94,100" fill="#3b2a18" opacity="0.4"/>
    <!-- E pointer -->
    <polygon points="192,100 100,94 112,100 100,106" fill="#3b2a18" opacity="0.4"/>
    <!-- W pointer -->
    <polygon points="8,100 100,106 88,100 100,94" fill="#3b2a18" opacity="0.4"/>
    <circle cx="100" cy="100" r="5" fill="#3b2a18"/>
    <text x="100" y="22" text-anchor="middle" font-size="9" fill="#3b2a18" font-family="serif">N</text>
    <text x="100" y="186" text-anchor="middle" font-size="9" fill="#3b2a18" font-family="serif">S</text>
    <text x="183" y="104" text-anchor="middle" font-size="9" fill="#3b2a18" font-family="serif">E</text>
    <text x="17" y="104" text-anchor="middle" font-size="9" fill="#3b2a18" font-family="serif">W</text>
  </svg>

  <!-- ── PARCHMENT LAYER ───────────────────────────────────────────── -->
  <div class="parchment">

    <!-- ─ NAV ──────────────────────────────────────────────────────── -->
    @include('partials.nav')

    <!-- ─ MAIN ─────────────────────────────────────────────────────── -->
    <main>
      <div class="site-wrapper">
        <section class="hero">
          <h1 class="hero-title">TZLA</h1>
        </section>
      </div>
    </main>

    <!-- ─ FOOTER ────────────────────────────────────────────────────── -->
    @include('partials.footer')

  </div><!-- /parchment -->

  <script>
    /* ── SPARK PARTICLES ON CLICK ─────────────────────────────────── */
    document.addEventListener('click', (e) => {
      const count = 6 + Math.floor(Math.random() * 5);
      for (let i = 0; i < count; i++) {
        const el = document.createElement('div');
        el.className = 'spark';
        const angle = (Math.PI * 2 * i) / count + (Math.random() - 0.5) * 0.5;
        const dist  = 20 + Math.random() * 40;
        el.style.cssText = `
          left: ${e.clientX}px;
          top: ${e.clientY}px;
          --dx: ${Math.cos(angle) * dist}px;
          --dy: ${Math.sin(angle) * dist}px;
          animation-duration: ${0.5 + Math.random() * 0.5}s;
          width: ${1 + Math.random() * 3}px;
          height: ${1 + Math.random() * 3}px;
        `;
        document.body.appendChild(el);
        el.addEventListener('animationend', () => el.remove());
      }
    });

  </script>
</body>
</html>
