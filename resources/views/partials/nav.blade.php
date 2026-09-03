@php
  /* Auto-detect active link from the current route */
  $active = $active ?? null;
  if (!$active) {
    if (request()->routeIs('portal'))       $active = request()->route('tab');
  }

  /* Optional extras */
  $navBackUrl    = $navBackUrl    ?? null;
  $navBackLabel  = $navBackLabel  ?? 'Back';
  $navConnectBtn = $navConnectBtn ?? false;
@endphp

<nav class="nav-bar" role="navigation" aria-label="Primary navigation">
  <div class="nav-inner">

    <a href="{{ route('home') }}" class="nav-brand">TZLA</a>
    <div class="nav-divider" aria-hidden="true"></div>

    <ul class="nav-links" id="nav-links">
      <li><a href="{{ route('portal', 'treasure') }}"
             @class(['active' => $active === 'treasure'])
             @if($active === 'treasure') aria-current="page" @endif>Treasure</a></li>
      <span class="nav-sep" aria-hidden="true">✦</span>
      <li><a href="{{ route('portal', 'staking') }}"
             @class(['active' => $active === 'staking'])
             @if($active === 'staking') aria-current="page" @endif>Staking</a></li>
      <span class="nav-sep" aria-hidden="true">✦</span>
      <li><a href="{{ route('portal', 'nft') }}"
             @class(['active' => $active === 'nft'])
             @if($active === 'nft') aria-current="page" @endif>NFT</a></li>
      <span class="nav-sep" aria-hidden="true">✦</span>
      <li><a href="{{ route('portal', 'swap') }}"
             @class(['active' => $active === 'swap'])
             @if($active === 'swap') aria-current="page" @endif>Swap</a></li>
    </ul>

    @if($navConnectBtn)
      <button id="connectBtn" type="button" class="connect-btn" aria-label="Connect wallet">
        Connect Wallet
      </button>
    @endif

    @if($navBackUrl)
      <a href="{{ $navBackUrl }}" class="nav-back">
        <svg width="12" height="12" viewBox="0 0 12 12" fill="none" stroke="currentColor"
             stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M8 1L3 6l5 5"/>
        </svg>
        {{ $navBackLabel }}
      </a>
    @endif

    <button class="nav-toggle" id="nav-toggle"
            aria-label="Toggle navigation" aria-expanded="false" aria-controls="nav-links">
      <span></span><span></span><span></span>
    </button>

  </div>
</nav>

<script>
  (function () {
    var toggle = document.getElementById('nav-toggle');
    var links  = document.getElementById('nav-links');
    if (!toggle || !links) return;
    toggle.addEventListener('click', function () {
      var open = links.classList.toggle('is-open');
      toggle.setAttribute('aria-expanded', open);
    });
    document.addEventListener('click', function (e) {
      if (!e.target.closest('.nav-inner')) {
        links.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
      }
    });
  })();
</script>
