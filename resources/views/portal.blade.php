<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>TZLA — {{ $title }}</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;0,700;1,300;1,400;1,600&family=Cormorant+SC:wght@300;400;600&family=Spectral:ital,wght@0,300;0,400;0,600;1,300;1,400&display=swap" rel="stylesheet" />
  @include('partials.nav-css')
  <style>
    :root {
      --parch:        #f2e8d0;
      --parch-dark:   #e8d9b4;
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
      --nav-h:        3.82rem;
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    html, body {
      height: 100%;
      font-size: clamp(22px, 1.527vw, 30px);
      overflow: hidden;
    }

    body {
      background-color: #0d0a06;
      color: var(--ink);
      font-family: var(--ff-body);
      display: flex;
      flex-direction: column;
      cursor: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='20' height='20' viewBox='0 0 20 20'%3E%3Ccircle cx='10' cy='10' r='3' fill='none' stroke='%23b06a1a' stroke-width='1.5'/%3E%3Cline x1='10' y1='0' x2='10' y2='6' stroke='%23b06a1a' stroke-width='1'/%3E%3Cline x1='10' y1='14' x2='10' y2='20' stroke='%23b06a1a' stroke-width='1'/%3E%3Cline x1='0' y1='10' x2='6' y2='10' stroke='%23b06a1a' stroke-width='1'/%3E%3Cline x1='14' y1='10' x2='20' y2='10' stroke='%23b06a1a' stroke-width='1'/%3E%3C/svg%3E") 10 10, crosshair;
    }

    /* portal: nav is a flex child */
    .nav-bar { position: relative; flex-shrink: 0; height: var(--nav-h, 84px); }

    /* ─── IFRAME CONTAINER ────────────────────────────────────────────── */
    .portal-frame {
      flex: 1;
      position: relative;
      overflow: hidden;
    }

    .portal-frame iframe {
      width: 100%;
      height: 100%;
      border: none;
      display: block;
    }

    /* ─── COMING SOON PLACEHOLDER ─────────────────────────────────────── */
    .coming-soon {
      flex: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      background:
        radial-gradient(ellipse at 15% 15%, rgba(210,180,130,0.18) 0%, transparent 55%),
        radial-gradient(ellipse at 85% 80%, rgba(180,140,80,0.14) 0%, transparent 50%),
        linear-gradient(160deg, #f5ebcf 0%, #eedd9f 35%, #f0e4b8 65%, #e8d49a 100%);
      position: relative;
      overflow: hidden;
    }

    .coming-soon::before {
      content: '';
      position: absolute;
      inset: 0;
      pointer-events: none;
      background-image:
        linear-gradient(var(--ink-mid) 1px, transparent 1px),
        linear-gradient(90deg, var(--ink-mid) 1px, transparent 1px);
      background-size: 72px 72px;
      opacity: 0.055;
    }

    .coming-soon::after {
      content: '';
      position: absolute;
      inset: 0;
      pointer-events: none;
      background: radial-gradient(ellipse at 50% 50%, transparent 40%, rgba(26,19,10,0.45) 100%);
    }

    .coming-soon-inner {
      position: relative;
      z-index: 10;
      text-align: center;
      padding: 2rem;
    }

    .coming-soon-label {
      font-family: var(--ff-caps);
      font-size: 0.8rem;
      letter-spacing: 0.32em;
      color: var(--verdigris);
      display: block;
      margin-bottom: 1.4rem;
      opacity: 0;
      animation: fade-up 0.7s 0.2s ease forwards;
    }

    .coming-soon-title {
      font-family: var(--ff-title);
      font-size: clamp(3rem, 8vw, 6rem);
      font-weight: 700;
      color: var(--ink);
      letter-spacing: 0.04em;
      line-height: 1.05;
      margin-bottom: 1.2rem;
      opacity: 0;
      animation: fade-up 0.8s 0.35s ease forwards;
    }

    .coming-soon-sub {
      font-family: var(--ff-body);
      font-style: italic;
      font-size: 1.15rem;
      color: var(--ink-light);
      max-width: 420px;
      line-height: 1.8;
      opacity: 0;
      animation: fade-up 0.8s 0.5s ease forwards;
    }

    .coming-soon-ornament {
      margin: 2rem auto 0;
      display: flex;
      align-items: center;
      gap: 1rem;
      justify-content: center;
      opacity: 0;
      animation: fade-in 1s 0.7s ease forwards;
    }

    .coming-soon-ornament span {
      display: block;
      width: 60px;
      height: 1px;
      background: linear-gradient(90deg, transparent, var(--copper));
    }

    .coming-soon-ornament span:last-child {
      background: linear-gradient(90deg, var(--copper), transparent);
    }

    .coming-soon-ornament svg { color: var(--copper); opacity: 0.7; }

    @keyframes fade-up {
      from { opacity: 0; transform: translateY(18px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    @keyframes fade-in {
      from { opacity: 0; }
      to   { opacity: 1; }
    }

    /* ─── EXTERNAL LAUNCH PAGE ───────────────────────────────────────── */
    .external-launch {
      flex: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      background:
        radial-gradient(ellipse at 15% 15%, rgba(210,180,130,0.18) 0%, transparent 55%),
        radial-gradient(ellipse at 85% 80%, rgba(180,140,80,0.14) 0%, transparent 50%),
        linear-gradient(160deg, #f5ebcf 0%, #eedd9f 35%, #f0e4b8 65%, #e8d49a 100%);
      position: relative;
      overflow: hidden;
      padding: 2rem;
      text-align: center;
    }

    .external-launch::before {
      content: '';
      position: absolute; inset: 0; pointer-events: none;
      background-image:
        linear-gradient(var(--ink-mid) 1px, transparent 1px),
        linear-gradient(90deg, var(--ink-mid) 1px, transparent 1px);
      background-size: 72px 72px;
      opacity: 0.055;
    }

    .external-launch::after {
      content: '';
      position: absolute; inset: 0; pointer-events: none;
      background: radial-gradient(ellipse at 50% 50%, transparent 40%, rgba(26,19,10,0.45) 100%);
    }

    .external-card {
      position: relative;
      z-index: 10;
      background: linear-gradient(145deg, rgba(242,232,208,0.92), rgba(232,217,180,0.88));
      border: 1px solid rgba(176,106,26,0.4);
      border-radius: 2px;
      padding: 3.5rem 4rem;
      max-width: 540px;
      width: 100%;
      box-shadow: 0 12px 48px var(--shadow-deep), inset 0 1px 0 rgba(240,192,96,0.25);
      opacity: 0;
      animation: fade-up 0.7s 0.15s ease forwards;
    }

    .external-card::before {
      content: '';
      position: absolute; top: 0; left: 0; right: 0;
      height: 2px;
      background: linear-gradient(90deg, transparent, var(--arc), var(--copper), var(--arc), transparent);
      opacity: 0.7;
    }

    .external-icon {
      width: 52px; height: 52px;
      margin: 0 auto 1.6rem;
      color: var(--verdigris);
      opacity: 0.8;
    }

    .external-label {
      font-family: var(--ff-caps);
      font-size: 0.8rem;
      letter-spacing: 0.28em;
      color: var(--verdigris);
      display: block;
      margin-bottom: 0.9rem;
    }

    .external-title {
      font-family: var(--ff-title);
      font-size: clamp(2.2rem, 6vw, 4rem);
      font-weight: 700;
      color: var(--ink);
      letter-spacing: 0.04em;
      line-height: 1.05;
      margin-bottom: 1rem;
    }

    .external-desc {
      font-family: var(--ff-body);
      font-style: italic;
      font-size: 1.05rem;
      color: var(--ink-light);
      line-height: 1.8;
      margin-bottom: 2.2rem;
    }

    .external-btn {
      display: inline-flex;
      align-items: center;
      gap: 0.7rem;
      font-family: var(--ff-caps);
      font-size: 0.9rem;
      letter-spacing: 0.18em;
      color: var(--parch-dark);
      background: rgba(26,19,10,0.9);
      border: 1px solid var(--copper);
      padding: 0.9rem 2.4rem;
      text-decoration: none;
      border-radius: 2px;
      transition: color 0.25s, border-color 0.25s, box-shadow 0.25s, transform 0.2s;
    }

    .external-btn:hover {
      color: var(--arc-glow);
      border-color: var(--arc);
      box-shadow: 0 0 24px rgba(127,212,248,0.3);
      transform: translateY(-2px);
    }

    .external-btn svg { flex-shrink: 0; }

    .external-notice {
      margin-top: 1.2rem;
      font-family: var(--ff-caps);
      font-size: 0.68rem;
      letter-spacing: 0.14em;
      color: var(--ink-light);
      opacity: 0.7;
    }

    @media (max-width: 700px) {
      .external-card { padding: 2.4rem 1.8rem; }
    }

    /* ─── SCROLLBAR ───────────────────────────────────────────────────── */
    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-track { background: var(--parch-dark); }
    ::-webkit-scrollbar-thumb { background: var(--copper); border-radius: 3px; }
    ::-webkit-scrollbar-thumb:hover { background: var(--copper-bright); }

    ::selection { background: rgba(127,212,248,0.3); color: var(--ink); }

    @media (max-width: 900px) { html { font-size: 20px; } }
    @media (max-width: 700px)  { html { font-size: 17px; } }
    @media (max-width: 400px)  { html { font-size: 15px; } }

  </style>
</head>
<body>

  @include('partials.nav')
  <!-- ─── CONTENT ──────────────────────────────────────────────────────── -->
  @if ($url && $embed)
    {{-- Embeddable iframe --}}
    <div class="portal-frame">
      <iframe
        src="{{ $url }}"
        title="{{ $title }}"
        allow="clipboard-read; clipboard-write; wallet; web-share"
        referrerpolicy="no-referrer-when-downgrade"
        loading="lazy"
      ></iframe>
    </div>

  @elseif ($url && !$embed)
    {{-- External site that blocks iframes — launch page --}}
    <div class="external-launch">
      <div class="external-card">
        <svg class="external-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <circle cx="12" cy="12" r="10"/>
          <line x1="2" y1="12" x2="22" y2="12"/>
          <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
        </svg>

        <span class="external-label">External Destination</span>
        <h1 class="external-title">{{ $title }}</h1>

        @if ($desc)
          <p class="external-desc">{{ $desc }}</p>
        @else
          <p class="external-desc">This destination opens on an external platform. Click below to launch it in a new tab — your TZLA navigation stays here.</p>
        @endif

        <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="external-btn">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
               stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
            <polyline points="15 3 21 3 21 9"/>
            <line x1="10" y1="14" x2="21" y2="3"/>
          </svg>
          Launch {{ $title }}
        </a>
      </div>
    </div>

  @else
    {{-- Coming soon --}}
    <div class="coming-soon">
      <div class="coming-soon-inner">
        <span class="coming-soon-label">Coming Soon</span>
        <h1 class="coming-soon-title">{{ $title }}</h1>
        <p class="coming-soon-sub">This expedition is being charted. Return when the tides are right.</p>
        <div class="coming-soon-ornament">
          <span></span>
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1"/>
            <line x1="8" y1="1" x2="8" y2="15" stroke="currentColor" stroke-width="0.75"/>
            <line x1="1" y1="8" x2="15" y2="8" stroke="currentColor" stroke-width="0.75"/>
            <polygon points="8,2 7,8 8,7 9,8" fill="currentColor"/>
          </svg>
          <span></span>
        </div>
      </div>
    </div>
  @endif


</body>
</html>
