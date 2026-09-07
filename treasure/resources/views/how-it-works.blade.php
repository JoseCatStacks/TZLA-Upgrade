<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TZLA — How It Works</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Pirata+One&family=IM+Fell+English:ital@0;1&family=IM+Fell+DW+Pica:ital@0;1&display=swap" rel="stylesheet">
    @vite('resources/js/app.js')
    <style>
        :root{
            --ink:#2a1a08;
            --ink-soft:#4a2f14;
            --parchment:#e8d5a3;
            --gold:#c69b3d;
            --gold-bright:#f4c66a;
            --flame:#ffb347;
            --blood:#7a1a0d;
            --monero:#ff6b1a;
        }

        *{box-sizing:border-box;margin:0;padding:0}
        html,body{height:100%;overflow:hidden}

        body{
            font-family:'IM Fell DW Pica', Georgia, serif;
            color:var(--ink);
            background:#0a0503;
            position:relative;
            min-height:100vh;
            cursor:default;
        }

        /* ==== SHACK (identical to home) ==== */
        .shack{
            position:fixed;inset:0;z-index:0;
            background:url("{{ asset('storage/wallpaper2.jpg') }}") center center / cover no-repeat, #1a0d05;
        }
        .shadowcast{
            position:fixed;inset:0;pointer-events:none;z-index:58;
            mix-blend-mode:multiply;
            background:
                radial-gradient(ellipse 130% 120% at 17% 34%,
                    rgba(255,248,225,1) 0%,
                    rgba(245,220,175,1) 20%,
                    rgba(215,180,130,1) 40%,
                    rgba(175,135,90,1) 62%,
                    rgba(130,95,60,1) 85%,
                    rgba(95,65,35,1) 100%);
            animation:flicker 3.4s ease-in-out infinite;
        }
        .warmcast{
            position:fixed;inset:0;pointer-events:none;z-index:59;
            mix-blend-mode:soft-light;
            background:radial-gradient(ellipse 30% 30% at 11% 34%,
                rgba(255,180,80,0.9) 0%,
                rgba(255,140,50,0.5) 25%,
                transparent 55%);
            animation:flicker 3.4s ease-in-out infinite;
        }
        .grain{
            position:fixed;inset:-50%;pointer-events:none;z-index:41;opacity:.10;mix-blend-mode:overlay;
            background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='240' height='240'><filter id='n'><feTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='2' stitchTiles='stitch'/><feColorMatrix values='0 0 0 0 1  0 0 0 0 1  0 0 0 0 1  0 0 0 1.4 0'/></filter><rect width='240' height='240' filter='url(%23n)' opacity='0.9'/></svg>");
            animation:grain 1.2s steps(6) infinite;
        }
        @keyframes grain{
            0%{transform:translate(0,0)}20%{transform:translate(-8%,4%)}40%{transform:translate(6%,-6%)}
            60%{transform:translate(-4%,8%)}80%{transform:translate(8%,2%)}100%{transform:translate(-2%,-4%)}
        }

        .lamplight{
            position:fixed;inset:0;pointer-events:none;z-index:18;
            background:
                radial-gradient(ellipse 37.5% 35% at 11% 34%,
                    rgba(255,190,95,0.62) 0%,
                    rgba(255,150,50,0.42) 15%,
                    rgba(220,110,30,0.22) 32%,
                    rgba(150,70,20,0.10) 50%,
                    transparent 72%),
                radial-gradient(circle 19vw at 11% 34%,
                    rgba(255,210,120,0.80) 0%,
                    rgba(255,170,70,0.48) 20%,
                    transparent 60%);
            mix-blend-mode:screen;
            animation:flicker 3.4s ease-in-out infinite;
        }
        .lamplight::after{
            content:"";position:absolute;inset:0;
            background:radial-gradient(circle 7vw at 11% 34%,
                rgba(255,250,225,1) 0%,
                rgba(255,230,170,0.75) 18%,
                rgba(255,190,100,0.35) 40%,
                transparent 65%);
            mix-blend-mode:screen;
            animation:flickerCore 1.7s ease-in-out infinite;
        }
        @keyframes flicker{
            0%,100%{opacity:1.12;filter:brightness(1.12)}
            18%{opacity:1.07;filter:brightness(1.08)}
            30%{opacity:1.16;filter:brightness(1.16)}
            47%{opacity:1.04;filter:brightness(1.05)}
            55%{opacity:1.18;filter:brightness(1.18)}
            72%{opacity:1.08;filter:brightness(1.09)}
            88%{opacity:1.15;filter:brightness(1.15)}
        }
        @keyframes flickerCore{
            0%,100%{opacity:1.14;transform:scale(1.005)}
            25%{opacity:1.04;transform:scale(.99)}
            50%{opacity:1.18;transform:scale(1.015)}
            75%{opacity:1.06;transform:scale(.995)}
        }


        .dust{position:fixed;inset:0;pointer-events:none;z-index:63;overflow:hidden}
        .mote{
            position:absolute;width:3px;height:3px;border-radius:50%;
            background:radial-gradient(circle, rgba(255,220,150,.9), rgba(255,180,80,0) 70%);
            filter:blur(.5px);
            animation:float 14s linear infinite;
        }
        @keyframes float{
            0%{transform:translate(0,110vh) scale(.4);opacity:0}
            10%{opacity:.9}
            90%{opacity:.6}
            100%{transform:translate(80px,-10vh) scale(1);opacity:0}
        }

        /* ==== TOP-RIGHT NAV ==== */
        .top-nav{
            position:fixed;top:2vh;right:2vw;z-index:60;
        }
        .back-btn{
            display:block;
            width:min(210px,16vw);
            transition:transform .18s ease, filter .18s ease;
            filter:drop-shadow(0 6px 12px rgba(0,0,0,.55));
        }
        .back-btn img{width:100%;height:auto;display:block}
        .back-btn:hover{transform:translateY(-2px) rotate(-1deg);filter:drop-shadow(0 10px 18px rgba(0,0,0,.6)) brightness(1.08)}

        /* ==== STAGE ==== */
        .stage{
            position:relative;z-index:10;
            width:100%;height:100vh;
            display:grid;
            grid-template-columns: 1.05fr 0.95fr;
            gap:2vw;
            padding:2vh 3vw 22vh;
            align-items:center;
        }

        /* ==== LEFT — PARCHMENT MANIFEST ==== */
        .manifest{
            position:relative;z-index:12;
            width:100%;max-width:760px;
            justify-self:end;
            padding:5vh 5vw 5vh 5vw;
            color:var(--ink);
            animation:parchIn 1.3s cubic-bezier(.16,1,.3,1) both;
        }
        @keyframes parchIn{
            0%{opacity:0;transform:translateY(28px) scale(.985)}
            100%{opacity:1;transform:translateY(0)}
        }

        /* the parchment sheet — solid background + edge shadow (no clip-path) */
        .manifest-sheet{
            position:absolute;inset:0;z-index:-1;
            border-radius:6px;
            background:
                radial-gradient(ellipse at 30% 20%, rgba(255,240,200,.9), rgba(232,213,163,.85) 45%, rgba(201,172,114,.85) 100%),
                linear-gradient(180deg, #eddcb1 0%, #d8bf85 100%);
            box-shadow:
                0 30px 60px rgba(0,0,0,.55),
                0 6px 12px rgba(0,0,0,.35),
                inset 0 0 90px rgba(80,50,20,.4),
                inset 0 0 22px rgba(255,235,190,.55);
        }
        .manifest-sheet::before{
            content:"";position:absolute;inset:0;pointer-events:none;
            border-radius:6px;
            background-image:
                radial-gradient(circle at 88% 12%, rgba(80,40,15,.22) 0%, transparent 6%),
                radial-gradient(circle at 12% 78%, rgba(80,40,15,.18) 0%, transparent 5%),
                radial-gradient(circle at 68% 62%, rgba(120,60,20,.14) 0%, transparent 4%),
                radial-gradient(circle at 42% 22%, rgba(60,30,10,.16) 0%, transparent 4%);
            mix-blend-mode:multiply;opacity:.9;
        }

        .manifest-kicker{
            font-family:'IM Fell English', serif;font-style:italic;
            font-size:clamp(11px, .95vw, 14px);
            letter-spacing:.42em;text-transform:uppercase;
            color:var(--blood);
            margin-bottom:.35em;opacity:.85;
            text-align:center;
        }
        .manifest-title{
            font-family:'Pirata One', cursive;
            font-size:clamp(34px, 4.4vw, 62px);
            line-height:.92;
            color:var(--ink);
            letter-spacing:.02em;
            text-align:center;
            margin-bottom:.1em;
            text-shadow:0 1px 0 rgba(255,235,180,.55), 1px 2px 0 rgba(0,0,0,.16);
        }
        .manifest-title .amp{color:var(--blood);font-style:italic;font-size:.72em;vertical-align:.15em;margin:0 .12em}
        .manifest-subtitle{
            font-family:'IM Fell English', serif;font-style:italic;
            font-size:clamp(12px, 1vw, 15px);
            color:var(--ink-soft);
            text-align:center;
            margin-bottom:1.4em;
            opacity:.85;
        }

        .filigree{
            display:flex;align-items:center;justify-content:center;gap:.6em;
            margin:.4em auto 1.2em;color:var(--blood);opacity:.55;
        }
        .filigree::before,.filigree::after{
            content:"";flex:1;height:1px;max-width:100px;
            background:linear-gradient(90deg,transparent,rgba(122,26,13,.55),transparent);
        }
        .filigree span{font-family:'Pirata One',cursive;font-size:1.1em;letter-spacing:.4em}

        .steps{
            list-style:none;
            display:flex;flex-direction:column;
            gap:.75em;
        }
        .step{
            display:grid;
            grid-template-columns: 46px 1fr;
            gap:.9em;
            align-items:start;
            padding:.2em 0 .55em;
            border-bottom:1px dotted rgba(74,47,20,.28);
            opacity:0;
            animation:stepIn .7s cubic-bezier(.16,1,.3,1) forwards;
        }
        .step:last-child{border-bottom:none}
        .step:nth-child(1){animation-delay:.45s}
        .step:nth-child(2){animation-delay:.55s}
        .step:nth-child(3){animation-delay:.65s}
        .step:nth-child(4){animation-delay:.75s}
        .step:nth-child(5){animation-delay:.85s}
        .step:nth-child(6){animation-delay:.95s}
        .step:nth-child(7){animation-delay:1.05s}
        @keyframes stepIn{
            0%{opacity:0;transform:translateX(-14px)}
            100%{opacity:1;transform:translateX(0)}
        }

        .step-num{
            width:40px;height:40px;
            border-radius:50%;
            display:flex;align-items:center;justify-content:center;
            background:radial-gradient(circle at 32% 30%, #b4321a 0%, #7a1a0d 45%, #4a0f08 100%);
            box-shadow:
                inset 0 -2px 3px rgba(0,0,0,.55),
                inset 0 2px 3px rgba(255,180,120,.35),
                0 3px 6px rgba(0,0,0,.45);
            font-family:'Pirata One', cursive;
            color:var(--gold-bright);
            font-size:1.15em;letter-spacing:.02em;
            text-shadow:0 1px 2px rgba(0,0,0,.55);
            transform:rotate(-8deg);
        }
        .step:nth-child(even) .step-num{transform:rotate(6deg)}

        .step-body{padding-top:.1em}
        .step-head{
            font-family:'Pirata One', cursive;
            font-size:clamp(16px, 1.45vw, 22px);
            color:var(--ink);
            letter-spacing:.03em;
            line-height:1.1;
            margin-bottom:.05em;
        }
        .step-head .accent{color:var(--blood)}
        .step-head .gold{color:#8a6a20}
        .step-head .monero{color:var(--monero)}
        .step-copy{
            font-family:'IM Fell English', serif;font-style:italic;
            font-size:clamp(12px, .95vw, 14px);
            color:var(--ink-soft);
            line-height:1.45;
        }
        .step-copy .chip{
            display:inline-block;
            font-style:normal;
            font-family:'Pirata One',cursive;letter-spacing:.08em;
            font-size:.88em;
            padding:0 .4em;
            border:1px solid rgba(74,47,20,.4);border-radius:2px;
            background:rgba(255,235,190,.4);
            color:var(--ink);
            margin:0 .1em;
        }

        .signature{
            display:flex;justify-content:space-between;align-items:center;
            margin-top:1.2em;padding-top:.8em;
            border-top:1px dashed rgba(74,47,20,.3);
        }
        .signature-line{
            font-family:'IM Fell English', serif;font-style:italic;
            color:var(--ink-soft);font-size:.85em;
            line-height:1.3;
        }
        .signature-line strong{
            font-family:'Pirata One',cursive;font-style:normal;
            color:var(--ink);letter-spacing:.05em;
            font-size:1.35em;display:block;margin-top:.1em;
        }
        .wax-seal{
            width:66px;height:66px;border-radius:50%;
            background:radial-gradient(circle at 32% 30%, #b4321a 0%, #7a1a0d 45%, #3a0a04 100%);
            display:flex;flex-direction:column;align-items:center;justify-content:center;
            color:var(--gold-bright);
            font-family:'Pirata One',cursive;
            font-size:.9em;line-height:1;letter-spacing:.06em;
            transform:rotate(-14deg);
            box-shadow:
                inset 0 -3px 5px rgba(0,0,0,.55),
                inset 0 2px 4px rgba(255,180,120,.4),
                0 4px 10px rgba(0,0,0,.55);
            flex-shrink:0;
        }
        .wax-seal small{
            font-family:'IM Fell English',serif;font-style:italic;
            font-size:.6em;opacity:.75;letter-spacing:.12em;
            margin-top:.2em;
        }

        /* ==== RIGHT — MONERO HOARD ==== */
        .hoard{
            position:relative;z-index:12;
            width:100%;max-width:640px;
            justify-self:start;
            display:flex;flex-direction:column;align-items:center;
            padding:1vh 0;
            animation:hoardIn 1.4s .3s cubic-bezier(.16,1,.3,1) both;
        }
        @keyframes hoardIn{
            0%{opacity:0;transform:translateY(30px) scale(.92)}
            100%{opacity:1}
        }

        .hoard-label{
            font-family:'Pirata One',cursive;
            font-size:clamp(22px,2.2vw,34px);
            letter-spacing:.12em;
            color:var(--gold-bright);
            text-shadow:0 2px 0 rgba(0,0,0,.55), 0 0 24px rgba(255,180,80,.5);
            text-align:center;
            margin-bottom:.15em;
        }
        .hoard-label .flare{color:var(--monero);text-shadow:0 2px 0 rgba(0,0,0,.55), 0 0 20px rgba(255,120,40,.6)}
        .hoard-sub{
            font-family:'IM Fell English',serif;font-style:italic;
            font-size:clamp(11px,.9vw,13px);
            letter-spacing:.35em;text-transform:uppercase;
            color:rgba(240,215,160,.7);
            margin-bottom:1.4em;
        }

        .chest-stage{
            position:relative;
            width:100%;
            display:flex;align-items:center;justify-content:center;
        }

        .chest-halo{
            position:absolute;
            width:110%;aspect-ratio:1;
            border-radius:50%;
            background:radial-gradient(circle,
                rgba(255,140,50,.4) 0%,
                rgba(255,100,20,.2) 30%,
                transparent 60%);
            filter:blur(24px);
            animation:pulse 4s ease-in-out infinite;
            pointer-events:none;
        }
        @keyframes pulse{
            0%,100%{opacity:.85;transform:scale(1)}
            50%{opacity:1;transform:scale(1.06)}
        }

        .chest-wrap{
            position:relative;z-index:2;
            width:min(500px, 36vw);
            filter:
                brightness(1) saturate(1.08)
                drop-shadow(0 30px 40px rgba(0,0,0,.7))
                drop-shadow(0 0 40px rgba(255,120,40,.35));
            animation:chestBreath 5s ease-in-out infinite;
        }
        .chest-wrap img{width:100%;height:auto;display:block}
        @keyframes chestBreath{
            0%,100%{transform:translateY(0) rotate(-.6deg)}
            50%{transform:translateY(-6px) rotate(.4deg)}
        }

        .coin{
            position:absolute;z-index:3;
            filter:drop-shadow(0 6px 10px rgba(0,0,0,.55)) drop-shadow(0 0 14px rgba(255,140,50,.35));
        }
        .coin img{width:100%;height:auto;display:block}
        .coin.c1{top:6%;right:2%;width:min(120px,9vw);animation:coinBob 6s ease-in-out infinite}
        .coin.c2{top:34%;left:-2%;width:min(100px,7.5vw);animation:coinBob 7s ease-in-out infinite -1.5s}
        .coin.c3{bottom:2%;right:6%;width:min(110px,8vw);animation:coinBob 5.5s ease-in-out infinite -2.2s;transform:rotate(-8deg)}
        @keyframes coinBob{
            0%,100%{transform:translateY(0) rotate(-3deg)}
            50%{transform:translateY(-10px) rotate(3deg)}
        }

        .prize-tiers{
            margin-top:1.2em;
            display:flex;gap:.8em;
            font-family:'Pirata One',cursive;
            padding:.6em 1.1em;
            background:rgba(20,10,4,.72);
            border:1px solid rgba(200,160,90,.35);
            border-radius:3px;
            backdrop-filter:blur(4px);
            box-shadow:0 12px 24px rgba(0,0,0,.6), inset 0 1px 0 rgba(255,220,150,.15);
        }
        .tier{
            display:flex;flex-direction:column;align-items:center;gap:.1em;
            color:rgba(240,215,160,.75);
            font-size:.7em;letter-spacing:.15em;
            min-width:70px;
        }
        .tier strong{
            font-size:1.9em;color:var(--gold-bright);letter-spacing:.03em;
            text-shadow:0 0 12px rgba(255,180,80,.5);
        }
        .tier.grand strong{color:var(--monero);text-shadow:0 0 14px rgba(255,120,40,.6)}
        .tier em{font-family:'IM Fell English',serif;font-style:italic;font-size:.85em;letter-spacing:.18em;opacity:.7}

        /* ==== BOTTOM — HUGE 8/11 ==== */
        .launch-plank{
            position:fixed;left:0;right:0;bottom:0;z-index:21;
            height:min(240px, 26vh);
            pointer-events:none;
            background:linear-gradient(180deg,
                transparent 0%,
                rgba(15,8,3,.55) 40%,
                rgba(10,5,2,.9) 100%);
        }
        .launch{
            position:fixed;left:0;right:0;bottom:0;z-index:22;
            pointer-events:none;
            display:flex;flex-direction:column;align-items:center;
            padding-bottom:1vh;
        }
        .launch-caption{
            font-family:'IM Fell English',serif;font-style:italic;
            font-size:clamp(11px,1vw,14px);
            letter-spacing:.55em;text-transform:uppercase;
            color:rgba(255,210,140,.6);
            margin-bottom:-.1em;
            text-shadow:0 2px 6px rgba(0,0,0,.7);
        }
        .launch-date{
            font-family:'Pirata One', cursive;
            font-size:clamp(120px, 20vw, 300px);
            line-height:.85;
            letter-spacing:-.02em;
            color:#f4c66a;
            background:linear-gradient(180deg, #ffe6a8 0%, #f4c66a 30%, #c69b3d 55%, #8a6a20 78%, #4a2f14 100%);
            -webkit-background-clip:text;
            background-clip:text;
            -webkit-text-fill-color:transparent;
            filter:
                drop-shadow(0 4px 0 rgba(0,0,0,.4))
                drop-shadow(0 12px 22px rgba(0,0,0,.7))
                drop-shadow(0 0 60px rgba(255,150,50,.35));
            animation:launchIn 1.4s 1.1s cubic-bezier(.16,1,.3,1) both;
        }
        .launch-date .slash{
            background:linear-gradient(180deg,#ff9d55 0%,#c94a10 60%,#5a1c05 100%);
            -webkit-background-clip:text;background-clip:text;
            -webkit-text-fill-color:transparent;
            display:inline-block;
            transform:translateY(-.02em) rotate(-6deg);
            margin:0 -.03em;
        }
        @keyframes launchIn{
            0%{opacity:0;transform:translateY(40px) scale(.9)}
            100%{opacity:1;transform:translateY(0) scale(1)}
        }

        /* ==== RESPONSIVE ==== */

        /* ---- Stacks to single column when too narrow for 2-col ---- */
        @media (max-width: 1100px){
            html,body{overflow:auto;height:auto}
            .stage{grid-template-columns:1fr;height:auto;padding:14vh 5vw 28vh;gap:5vh}
            .manifest,.hoard{justify-self:center;max-width:640px}
        }

        /* ---- Tablet (≤768px) ---- */
        @media (max-width: 768px){
            .stage{padding:12vh 5vw 22vh;gap:4vh}

            /* Reduce the massive 8/11 date on tablet */
            .launch-date{font-size:clamp(80px,17vw,160px)}
            .launch-caption{font-size:clamp(9px,2.2vw,12px);letter-spacing:.38em}
            .launch-plank{height:min(170px,21vh)}

            /* Chest and floating coins */
            .chest-wrap{width:min(340px,60vw)}
            .coin.c1{width:min(85px,12vw)}
            .coin.c2{width:min(72px,10vw)}
            .coin.c3{width:min(78px,11vw)}

            .prize-tiers{gap:.55em;padding:.5em .85em}
            .tier{min-width:60px;font-size:.67em}
        }

        /* ---- Mobile (≤640px) ---- */
        @media (max-width: 640px){
            .top-nav{top:1.2vh;right:1.2vw}
            .back-btn{width:clamp(100px,26vw,150px)}
            .manifest{padding:4vh 6vw}
            .step{grid-template-columns:36px 1fr;gap:.7em}
            .step-num{width:34px;height:34px;font-size:.95em}
            .prize-tiers{gap:.5em;padding:.5em .7em}
            .tier{min-width:56px}

            .stage{padding:10vh 5vw 18vh}

            /* Bring the date down to a manageable size */
            .launch-date{font-size:clamp(62px,16vw,110px)}
            .launch-plank{height:min(140px,19vh)}
        }

        /* ---- Small phones (≤480px) ---- */
        @media (max-width: 480px){
            .stage{padding:9vh 4vw 16vh}
            .launch-date{font-size:clamp(52px,14vw,80px)}
            .launch-plank{height:min(110px,16vh)}

            .hoard-label{font-size:clamp(18px,5vw,26px)}
            .hoard-sub{font-size:clamp(9px,2.4vw,11px)}
            .chest-wrap{width:min(260px,72vw)}

            .coin.c1{width:min(60px,15vw)}
            .coin.c2{width:min(52px,13vw)}
            .coin.c3{width:min(56px,14vw)}
        }
    </style>
</head>
<body>

    <div class="shack"></div>
    <div class="lamplight"></div>

    <div class="dust">
        <span class="mote" style="left:8%;animation-delay:0s"></span>
        <span class="mote" style="left:18%;animation-delay:3s"></span>
        <span class="mote" style="left:26%;animation-delay:7s"></span>
        <span class="mote" style="left:42%;animation-delay:9s"></span>
        <span class="mote" style="left:66%;animation-delay:2.5s"></span>
        <span class="mote" style="left:78%;animation-delay:6s"></span>
        <span class="mote" style="left:88%;animation-delay:11s"></span>
    </div>

    <nav class="top-nav">
        <a href="{{ url('/') }}" class="back-btn" aria-label="Back to the map">
            <img src="{{ asset('storage/backtothemapbtn.png') }}" alt="Back to the map" />
        </a>
    </nav>

    <main class="stage">

        {{-- LEFT: PARCHMENT MANIFEST --}}
        <section class="manifest" aria-labelledby="manifest-title">
            <div class="manifest-sheet" aria-hidden="true"></div>

            <div class="manifest-kicker">The Captain's Orders</div>
            <h1 class="manifest-title" id="manifest-title">How <span class="amp">it</span> Works</h1>
            <div class="manifest-subtitle">Seven rules of the hunt — obey them and Monero shall be yours.</div>

            <div class="filigree" aria-hidden="true"><span>✶</span></div>

            <ol class="steps">
                <li class="step">
                    <div class="step-num" aria-hidden="true">I</div>
                    <div class="step-body">
                        <div class="step-head">Connect <span class="accent">thy Wallet</span></div>
                        <div class="step-copy">Sign in with your Solana wallet to prove ye be a hand aboard the ship.</div>
                    </div>
                </li>
                <li class="step">
                    <div class="step-num" aria-hidden="true">II</div>
                    <div class="step-body">
                        <div class="step-head">Christen a <span class="gold">Username</span></div>
                        <div class="step-copy">Choose the name posterity shall inscribe upon the ledger of winners.</div>
                    </div>
                </li>
                <li class="step">
                    <div class="step-num" aria-hidden="true">III</div>
                    <div class="step-body">
                        <div class="step-head">Enter a <span class="monero">Monero Address</span></div>
                        <div class="step-copy">Bind thy XMR wallet — the vessel by which thy bounty shall arrive.</div>
                    </div>
                </li>
                <li class="step">
                    <div class="step-num" aria-hidden="true">IV</div>
                    <div class="step-body">
                        <div class="step-head">Solve <span class="accent">Three Clues</span> to Win Monero</div>
                        <div class="step-copy">Each riddle bested brings ye closer to the buried hoard.</div>
                    </div>
                </li>
                <li class="step">
                    <div class="step-num" aria-hidden="true">V</div>
                    <div class="step-body">
                        <div class="step-head">Fresh Clues <span class="gold">Every Week</span></div>
                        <div class="step-copy">The captain uncovers a new scroll come each Monday's tide.</div>
                    </div>
                </li>
                <li class="step">
                    <div class="step-num" aria-hidden="true">VI</div>
                    <div class="step-body">
                        <div class="step-head">A Small <span class="chip">SOL</span> Fee to Submit</div>
                        <div class="step-copy">A wee sum in Solana keeps the sharks and false claims at bay.</div>
                    </div>
                </li>
                <li class="step">
                    <div class="step-num" aria-hidden="true">VII</div>
                    <div class="step-body">
                        <div class="step-head">First Five to Solve <span class="monero">Share the Chest</span></div>
                        <div class="step-copy">1st 0.6 XMR · 2nd 0.3 · 3rd 0.2 · 4th 0.1 · 5th 0.1. Paid by hand after we confirm no foul play.</div>
                    </div>
                </li>
            </ol>

            <div class="signature">
                <div class="signature-line">
                    Signed by the flame of the lamp,
                    <strong>— The Captain</strong>
                </div>
                <div class="wax-seal" aria-hidden="true">
                    TZLA
                    <small>MMXXVI</small>
                </div>
            </div>
        </section>

        {{-- RIGHT: MONERO HOARD --}}
        <aside class="hoard" aria-labelledby="hoard-label">
            <div class="hoard-label" id="hoard-label">The <span class="flare">Monero</span> Hoard</div>
            <div class="hoard-sub">Buried for the swiftest solver</div>

            <div class="chest-stage">
                <div class="chest-halo" aria-hidden="true"></div>
                <div class="chest-wrap">
                    <img src="{{ asset('storage/treasurechestmcoin.png') }}" alt="Treasure chest overflowing with Monero coins" />
                </div>
                <div class="coin c1" aria-hidden="true"><img src="{{ asset('storage/mcoins.png') }}" alt="" /></div>
                <div class="coin c2" aria-hidden="true"><img src="{{ asset('storage/mcoins.png') }}" alt="" /></div>
                <div class="coin c3" aria-hidden="true"><img src="{{ asset('storage/mcoins.png') }}" alt="" /></div>
            </div>

            <div class="prize-tiers" aria-label="Prize tiers">
                <div class="tier grand">
                    <strong>1st</strong>
                    <em>Grand Hoard</em>
                </div>
                <div class="tier">
                    <strong>2nd</strong>
                    <em>—</em>
                </div>
                <div class="tier">
                    <strong>3rd</strong>
                    <em>—</em>
                </div>
            </div>
        </aside>

    </main>

    {{-- BOTTOM: HUGE 8/11 --}}
    <div class="launch-plank" aria-hidden="true"></div>
    <div class="launch">
        <div class="launch-caption">The Hunt Begins</div>
        <div class="launch-date" aria-label="August 11">8<span class="slash">/</span>11</div>
    </div>

    {{-- LIGHTING LAYERS ON TOP --}}
    <div class="shadowcast"></div>
    <div class="warmcast"></div>
    <div class="grain"></div>

</body>
</html>
