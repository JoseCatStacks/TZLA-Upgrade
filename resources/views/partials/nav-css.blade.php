<style>
  /* ─── NAV BAR ─────────────────────────────────────────────────────────── */
  .nav-bar {
    position: sticky;
    top: 0;
    z-index: 500;
    background: linear-gradient(180deg, rgba(26,19,10,0.97) 0%, rgba(42,30,14,0.97) 100%);
    border-bottom: 1px solid var(--copper);
    backdrop-filter: blur(6px);
    font-size: 1.1rem;
    position: relative; /* overridden below by sticky where needed */
  }

  .nav-bar {
    position: sticky;
    top: 0;
    z-index: 500;
  }

  .nav-bar::before {
    content: '';
    position: absolute;
    bottom: -1px;
    left: 0; right: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent, var(--gold-lt), var(--arc), var(--gold-lt), transparent);
    animation: nav-shimmer 4s ease-in-out infinite;
  }

  @keyframes nav-shimmer {
    0%, 100% { opacity: 0.4; }
    50%       { opacity: 1; }
  }

  .nav-inner {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 2.5rem;
    display: flex;
    align-items: center;
    height: var(--nav-h, 3.82rem);
    gap: 0;
  }

  .nav-brand {
    font-family: var(--ff-title);
    font-size: 1.4rem;
    font-weight: 700;
    color: var(--gold-lt);
    letter-spacing: 0.08em;
    text-decoration: none;
    white-space: nowrap;
    text-shadow: 0 0 18px rgba(200,146,42,0.6);
    transition: text-shadow 0.3s;
    margin-right: 2rem;
    flex-shrink: 0;
  }

  .nav-brand:hover {
    text-shadow: 0 0 28px rgba(200,146,42,0.9), 0 0 50px rgba(127,212,248,0.3);
  }

  .nav-divider {
    width: 1px;
    height: 28px;
    background: linear-gradient(180deg, transparent, var(--copper), transparent);
    flex-shrink: 0;
    margin-right: 1rem;
  }

  .nav-links {
    display: flex;
    gap: 0;
    list-style: none;
    align-items: center;
    justify-content: center;
    flex: 1;
  }

  .nav-links li a {
    font-family: var(--ff-caps);
    font-size: 0.95rem;
    letter-spacing: 0.14em;
    color: var(--parch-dark);
    text-decoration: none;
    padding: 0.6rem 1.4rem;
    position: relative;
    transition: color 0.25s;
    display: block;
  }

  .nav-links li a::after {
    content: '';
    position: absolute;
    bottom: 6px;
    left: 1.1rem; right: 1.1rem;
    height: 1px;
    background: var(--arc);
    transform: scaleX(0);
    transform-origin: center;
    transition: transform 0.3s cubic-bezier(0.4,0,0.2,1);
    box-shadow: 0 0 6px var(--arc);
  }

  .nav-links li a:hover,
  .nav-links li a.active {
    color: var(--arc-glow);
  }

  .nav-links li a:hover::after,
  .nav-links li a.active::after {
    transform: scaleX(1);
  }

  .nav-links li a.active {
    text-shadow: 0 0 14px rgba(127,212,248,0.4);
  }

  .nav-sep {
    color: var(--copper);
    font-size: 0.65rem;
    padding: 0 0.15rem;
    opacity: 0.6;
    user-select: none;
  }

  /* ─── BACK CRUMB (optional nav-back) ──────────────────────────────────── */
  .nav-back {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-family: var(--ff-caps);
    font-size: 0.8rem;
    letter-spacing: 0.14em;
    color: var(--copper-bright);
    text-decoration: none;
    border: 1px solid rgba(176,106,26,0.35);
    padding: 0.4rem 1rem;
    border-radius: 2px;
    transition: color 0.25s, border-color 0.25s, box-shadow 0.25s;
    white-space: nowrap;
    flex-shrink: 0;
    margin-left: auto;
  }

  .nav-back:hover {
    color: var(--arc-glow);
    border-color: var(--arc);
    box-shadow: 0 0 12px rgba(127,212,248,0.2);
  }

  /* ─── CONNECT BUTTON (staking) ────────────────────────────────────────── */
  .connect-btn {
    font-family: var(--ff-caps);
    font-size: 0.8rem;
    letter-spacing: 0.14em;
    color: var(--parch-dark);
    background: rgba(26,19,10,0.85);
    border: 1px solid var(--copper);
    padding: 0.5rem 1.2rem;
    border-radius: 2px;
    cursor: pointer;
    transition: color 0.25s, border-color 0.25s, box-shadow 0.25s;
    flex-shrink: 0;
    margin-left: 1rem;
  }

  .connect-btn:hover {
    color: var(--arc-glow);
    border-color: var(--arc);
    box-shadow: 0 0 16px rgba(127,212,248,0.2);
  }

  /* ─── HAMBURGER ───────────────────────────────────────────────────────── */
  .nav-toggle {
    display: none;
    flex-direction: column;
    justify-content: center;
    gap: 5px;
    background: none;
    border: none;
    cursor: pointer;
    padding: 0.5rem;
    flex-shrink: 0;
    margin-left: auto;
  }

  .nav-toggle span {
    display: block;
    width: 22px;
    height: 2px;
    background: var(--parch-dark);
    border-radius: 1px;
    transition: transform 0.3s, opacity 0.3s;
  }

  .nav-toggle[aria-expanded="true"] span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
  .nav-toggle[aria-expanded="true"] span:nth-child(2) { opacity: 0; }
  .nav-toggle[aria-expanded="true"] span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }

  /* ─── RESPONSIVE ──────────────────────────────────────────────────────── */
  @media (max-width: 900px) {
    .nav-inner { height: 3.5rem; }
  }

  @media (max-width: 700px) {
    .nav-inner {
      padding: 0 1.2rem;
      height: 3.529rem;
      position: relative;
    }

    .nav-toggle { display: flex; }
    .nav-divider { display: none; }
    .nav-sep     { display: none; }
    .nav-back    { font-size: 0.72rem; padding: 0.35rem 0.7rem; margin-left: 0.5rem; }

    .nav-links {
      display: none;
      position: absolute;
      top: 100%;
      left: 0; right: 0;
      flex-direction: column;
      background: linear-gradient(180deg, rgba(26,19,10,0.98) 0%, rgba(42,30,14,0.98) 100%);
      border-top: 1px solid var(--copper);
      border-bottom: 1px solid var(--copper);
      padding: 0.5rem 0;
      gap: 0;
      z-index: 499;
    }

    .nav-links.is-open { display: flex; }
    .nav-links li a   { padding: 0.85rem 2rem; font-size: 1.05rem; }
  }

  @media (max-width: 400px) {
    .nav-inner { height: 3.6rem; }
  }
</style>
