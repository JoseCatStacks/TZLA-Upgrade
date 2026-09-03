@php
  /* Tradeable TZLA mint on Jupiter — the public contract address. */
  $coinAddress = config('oracle.price.mint');
@endphp

<style>
  /* ─── COPYABLE CONTRACT ADDRESS ───────────────────────────────────── */
  .ca-copy {
    display: inline-flex;
    align-items: center;
    gap: 0.6rem;
    max-width: min(92vw, 26rem);
    font-family: var(--ff-caps, 'Cormorant SC', serif);
    background: rgba(26,19,10,0.05);
    border: 1px solid rgba(176,106,26,0.35);
    border-radius: 3px;
    padding: 0.55rem 0.9rem;
    cursor: pointer;
    color: var(--ink-mid, #3b2a18);
    transition: border-color 0.25s, box-shadow 0.25s, background 0.25s;
    -webkit-tap-highlight-color: transparent;
    user-select: none;
  }
  .ca-copy:hover,
  .ca-copy:focus-visible {
    border-color: var(--copper, #b06a1a);
    background: rgba(176,106,26,0.08);
    box-shadow: 0 0 14px rgba(176,106,26,0.15);
    outline: none;
  }
  .ca-copy .ca-tag {
    font-size: 0.7rem;
    letter-spacing: 0.18em;
    color: var(--copper, #b06a1a);
    flex: none;
  }
  .ca-copy .ca-addr {
    font-family: ui-monospace, 'SF Mono', 'Menlo', monospace;
    font-size: 0.82rem;
    letter-spacing: 0.01em;
    color: var(--ink, #1a130a);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    min-width: 0;
  }
  .ca-copy .ca-icon {
    flex: none;
    display: inline-flex;
    color: var(--copper, #b06a1a);
    transition: color 0.2s;
  }
  .ca-copy .ca-icon .icon-check { display: none; }
  .ca-copy.is-copied {
    border-color: var(--arc, #7fd4f8);
    background: rgba(127,212,248,0.10);
  }
  .ca-copy.is-copied .ca-icon { color: var(--arc, #2aa88f); }
  .ca-copy.is-copied .ca-icon .icon-copy  { display: none; }
  .ca-copy.is-copied .ca-icon .icon-check { display: inline; }
</style>

<footer class="site-footer" id="coordinates" aria-label="Site footer">
  <div class="site-wrapper">
    <div class="footer-inner">
      <p class="footer-brand">TZLA</p>

      @if($coinAddress)
        <button type="button" class="ca-copy" id="caCopy"
                data-ca="{{ $coinAddress }}"
                aria-label="Copy TZLA contract address to clipboard">
          <span class="ca-tag">CA</span>
          <span class="ca-addr">{{ $coinAddress }}</span>
          <span class="ca-icon" aria-hidden="true">
            <svg class="icon-copy" width="16" height="16" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
              <rect x="9" y="9" width="11" height="11" rx="2"/>
              <path d="M5 15V5a2 2 0 0 1 2-2h10"/>
            </svg>
            <svg class="icon-check" width="16" height="16" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M4 12.5 9 17.5 20 6.5"/>
            </svg>
          </span>
        </button>
      @endif

      <a href="https://x.com/tzlaonsol" target="_blank" rel="noopener noreferrer" class="x-button">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.744l7.73-8.835L1.254 2.25H8.08l4.259 5.63 5.905-5.63Zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
        Follow on X
      </a>
    </div>
  </div>
</footer>

<script>
  (function () {
    var btn = document.getElementById('caCopy');
    if (!btn) return;

    var resetTimer = null;

    function fallbackCopy(text) {
      var ta = document.createElement('textarea');
      ta.value = text;
      ta.setAttribute('readonly', '');
      ta.style.position = 'absolute';
      ta.style.left = '-9999px';
      ta.style.top = '0';
      document.body.appendChild(ta);
      ta.focus();
      ta.select();
      ta.setSelectionRange(0, text.length); /* iOS needs an explicit range */
      var ok = false;
      try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
      document.body.removeChild(ta);
      return ok;
    }

    function showCopied() {
      btn.classList.add('is-copied');
      var addr = btn.querySelector('.ca-addr');
      var original = addr ? addr.textContent : null;
      if (addr) addr.textContent = 'Copied to clipboard';
      clearTimeout(resetTimer);
      resetTimer = setTimeout(function () {
        btn.classList.remove('is-copied');
        if (addr && original !== null) addr.textContent = original;
      }, 1800);
    }

    btn.addEventListener('click', function () {
      var text = btn.getAttribute('data-ca');
      if (!text) return;

      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(showCopied, function () {
          if (fallbackCopy(text)) showCopied();
        });
      } else if (fallbackCopy(text)) {
        showCopied();
      }
    });
  })();
</script>
