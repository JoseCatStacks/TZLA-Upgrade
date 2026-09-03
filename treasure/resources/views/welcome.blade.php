<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TZLA — The Captain's Riddle</title>
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

        /* ==== WOODEN SHACK WALL ==== */
        .shack{
            position:fixed;inset:0;z-index:0;
            background:url("{{ asset('storage/wallpaper2.jpg') }}") center center / cover no-repeat;
            filter:brightness(.68) saturate(.9);
        }

        /* SHADOWCAST — darkens everything far from the lamp (multiply on TOP of all content) */
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
        /* WARM CAST — broad room-fill tint, no point source */
        .warmcast{
            position:fixed;inset:0;pointer-events:none;z-index:59;
            mix-blend-mode:soft-light;
            background:radial-gradient(ellipse 120% 100% at 20% 40%,
                rgba(255,160,60,0.28) 0%,
                rgba(220,110,30,0.12) 45%,
                transparent 75%);
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

        /* dust motes lit by lamp */
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


        /* ==== WEEK SCROLLS ==== */
        .week-row{
            position:fixed;left:50%;bottom:4vh;z-index:25;
            transform:translateX(-50%);
            display:flex;gap:2.2vw;align-items:flex-end;
            pointer-events:none;
        }
        .week{
            position:relative;
            width:min(140px, 11vw);
            filter:brightness(.75) saturate(1.1) sepia(.38) hue-rotate(-10deg);
            animation:propIn 1.4s cubic-bezier(.16,1,.3,1) both;
        }
        .week img{width:100%;height:auto;display:block}
        .week .lbl{
            position:absolute;top:50%;left:50%;
            transform:translate(-50%,-50%);
            font-family:'Pirata One', cursive;
            font-size:clamp(11px,1.05vw,17px);
            color:var(--ink);
            letter-spacing:.08em;
            text-shadow:0 1px 0 rgba(255,220,150,.4);
            white-space:nowrap;
            pointer-events:none;
        }
        .week:nth-child(1){animation-delay:1.0s;transform:rotate(-6deg) var(--tilt)}
        .week:nth-child(2){animation-delay:1.1s;transform:rotate(3deg)  var(--tilt)}
        .week:nth-child(3){animation-delay:1.2s;transform:rotate(-2deg) var(--tilt)}
        .week:nth-child(4){animation-delay:1.3s;transform:rotate(5deg)  var(--tilt)}
        .week:nth-child(5){animation-delay:1.4s;transform:rotate(-4deg) var(--tilt)}

        /* scattered scrolls sitting atop the big map */
        .map-scroll{
            position:absolute;
            width:min(220px, 18vw);z-index:17;
            filter:brightness(.78) saturate(.95);
            animation:propIn 1.4s cubic-bezier(.16,1,.3,1) both;
            pointer-events:none;
        }
        .map-scroll img{width:100%;height:auto;display:block}
        .map-scroll:nth-of-type(1){top:28%;left:20%;animation-delay:1.0s;transform:rotate(-14deg) var(--tilt)}
        .map-scroll:nth-of-type(2){top:34%;left:64%;animation-delay:1.15s;transform:rotate(8deg)  var(--tilt)}
        .map-scroll:nth-of-type(3){top:56%;left:29%;animation-delay:1.3s;transform:rotate(-3deg) var(--tilt)}
        .map-scroll:nth-of-type(4){top:56%;left:70%;animation-delay:1.45s;transform:rotate(17deg) var(--tilt)}
        .map-scroll:nth-of-type(5){top:44%;left:45%;animation-delay:1.6s;transform:rotate(-9deg) var(--tilt)}
        .map-scroll .lbl{
            position:absolute;top:50%;left:50%;
            transform:translate(-50%,-50%);
            font-family:'Pirata One', cursive;
            font-size:clamp(13px,1.3vw,22px);
            color:var(--ink);
            letter-spacing:.08em;
            text-shadow:0 1px 0 rgba(255,220,150,.4);
            white-space:nowrap;
            pointer-events:none;
        }

        /* ==== MAIN STAGE ==== */
        .stage{
            position:relative;z-index:10;
            width:100%;height:100vh;
            display:flex;align-items:center;justify-content:center;
            padding:2vh 2vw;
        }

        .map-frame{
            position:relative;
            width:min(2100px, 144vw);
            aspect-ratio: 16 / 8.2;
            max-height:none;
            display:flex;align-items:center;justify-content:center;
            transform:translateY(-10vh);
        }

        /* ==== MODULAR TILT ====
           Applies a tabletop perspective tilt: top recedes, bottom advances.
           Tunable via CSS vars so animations can compose it via var(--tilt).
           Usage: add .laid-flat to any element (optionally override --tilt-angle). */
        .laid-flat{
            --tilt-angle: 45deg;
            --tilt-depth: 1400px;
            --tilt: perspective(var(--tilt-depth)) rotateX(var(--tilt-angle));
            transform: var(--tilt);
            transform-origin: 50% 50%;
        }

        /* Wrapper that animates the map + all sea decals together as one unit */
        .map-canvas{
            position:absolute;inset:0;z-index:16;
            transform-origin:50% 50%;
        }
        /* Offset layer to nudge the decals onto the visible tilted map area
           (the scroll gets vertically compressed by its 45deg tilt, so decals
           positioned in raw frame % end up above/left of the map surface). */
        .seadecal-layer{
            position:absolute;inset:0;z-index:17;
            transform: translate(7%, 8%) scale(.88);
            transform-origin:50% 50%;
        }

        .scroll{
            position:absolute;inset:0;z-index:16;
            width:100%;height:100%;
            object-fit:contain;
            user-select:none;pointer-events:none;
            /* mid-far from lamp — center of the scene */
            filter:brightness(.68) saturate(.9);
        }
        /* Legacy keyframe kept for any other consumers of scrollIn */
        @keyframes scrollIn{
            0%{opacity:0;transform:var(--tilt) scale(.85)}
            100%{opacity:1;transform:var(--tilt) scale(1)}
        }

        .seadecal{
            position:absolute;z-index:17;
            width:13%;height:auto;
            object-fit:contain;
            user-select:none;pointer-events:none;
            transform-origin:50% 50%;
            transform: translate(-50%,-50%) scaleX(1.12);
            filter:brightness(.72) saturate(.95);
            mix-blend-mode:multiply;
            opacity:.9;
        }
        /* ==== WEEKPAPERS ON MAP ==== */
        .weekpaper{
            position:absolute;
            width:min(210px, 16.5vw);
            z-index:19;
            cursor:pointer;
            pointer-events:auto;
            transform:translate(-50%,-50%) rotate(var(--rot,0deg));
            transform-origin:center center;
            transition:filter .25s ease, transform .25s ease;
            animation:paperIn 1.2s cubic-bezier(.16,1,.3,1) both;
            filter:drop-shadow(3px 3px 5px rgba(60,30,0,.45));
        }
        .weekpaper img{
            width:100%;height:auto;display:block;
            filter:brightness(.80) saturate(1.1) sepia(.38) hue-rotate(-10deg);
            mix-blend-mode:multiply;
            opacity:.95;
            transition:filter .25s ease,opacity .25s ease;
        }
        .weekpaper-num{
            position:absolute;top:50%;left:50%;
            transform:translate(-50%,-62%);
            font-family:'Pirata One',cursive;
            font-size:clamp(28px,2.8vw,42px);
            color:var(--ink);
            letter-spacing:.18em;
            text-shadow:0 1px 0 rgba(255,220,150,.4);
            pointer-events:none;
            user-select:none;
        }
        .weekpaper:hover{
            filter:brightness(1.2) drop-shadow(3px 3px 5px rgba(60,30,0,.45)) drop-shadow(0 0 14px rgba(255,190,90,.65));
            transform:translate(-50%,-50%) rotate(var(--rot,0deg)) scale(1.07);
        }
        .weekpaper:hover img{filter:brightness(1.15) saturate(1.1);opacity:1}
        @keyframes paperIn{
            0%{opacity:0;transform:translate(-50%,-50%) rotate(var(--rot,0deg)) scale(.82)}
            100%{opacity:1;transform:translate(-50%,-50%) rotate(var(--rot,0deg)) scale(1)}
        }

        /* PROPS sitting atop the map */
        @keyframes propIn{
            0%{opacity:0;transform:translateY(-30px) rotate(30deg) scale(.7)}
            100%{opacity:1}
        }
        .skull{
            position:absolute;top:-4%;right:-4%;
            width:min(345px, 27vw);
            transform:rotate(12deg) scaleX(-1);z-index:30;
            /* closest to lamp — nearly full brightness, warm rim */
            filter: brightness(1.05) saturate(1.05);
        }
        .flag{
            position:fixed;bottom:-16%;left:-2%;
            width:min(300px, 22vw);
            transform:rotate(-8deg);z-index:15;
            /* mid distance — below lamp but on the same side */
            filter:brightness(.72) saturate(.9);
            animation:wobbleL 9s ease-in-out infinite 1s;
        }
        @keyframes wobbleL{
            0%,100%{transform:rotate(-8deg)}
            50%{transform:rotate(-5deg)}
        }
        .isle{
            position:absolute;bottom:-2%;right:-8%;
            width:min(300px, 21vw);
            transform:rotate(25deg);z-index:20;
            /* farthest from lamp (opposite diagonal) — deepest falloff */
            filter:brightness(.42) saturate(.65);
        }

        /* ==== SCROLL CONTENT ==== */
        .parchment-content{
            position:relative;z-index:12;
            width:52%;max-width:640px;
            padding:2vh 0;
            text-align:center;color:var(--ink);
            animation:textIn 1.4s .9s cubic-bezier(.16,1,.3,1) both;
        }
        @keyframes textIn{
            0%{opacity:0;transform:translateY(20px)}
            100%{opacity:1}
        }

        .kicker{
            font-family:'IM Fell English', serif;font-style:italic;
            font-size:clamp(11px, 1vw, 14px);
            letter-spacing:.35em;text-transform:uppercase;
            color:var(--blood);
            margin-bottom:.6em;opacity:.75;
        }

        .title{
            font-family:'Pirata One', cursive;
            font-size:clamp(28px, 4.2vw, 62px);
            line-height:.95;color:var(--ink);
            letter-spacing:.02em;
            text-shadow:0 1px 0 rgba(255,220,150,0.4), 1px 2px 0 rgba(0,0,0,0.15);
            margin-bottom:.2em;
        }
        .title .amp{color:var(--blood);font-style:italic;font-size:.75em;vertical-align:.15em;margin:0 .1em}

        .riddle{
            font-family:'IM Fell English', serif;font-style:italic;
            font-size:clamp(13px, 1.15vw, 17px);
            line-height:1.55;color:var(--ink-soft);
            margin:1.2em auto 1.4em;
            max-width:90%;position:relative;
        }
        .riddle::before,.riddle::after{
            font-family:'IM Fell English',serif;
            font-size:2em;color:var(--blood);opacity:.35;
            position:absolute;line-height:1;
        }
        .riddle::before{content:"“";top:-.35em;left:-.4em}
        .riddle::after{content:"”";bottom:-.7em;right:-.4em}

        .guess-row{
            display:flex;gap:.5rem;align-items:stretch;
            margin:1em auto .6em;
            max-width:92%;
        }
        .guess-input{
            flex:1;background:transparent;border:none;
            border-bottom:2px dashed rgba(74,47,20,.55);
            padding:.5em .4em .35em;
            font-family:'IM Fell English', serif;
            font-size:clamp(14px, 1.3vw, 18px);
            color:var(--ink);outline:none;
            text-align:center;letter-spacing:.05em;
            transition:border-color .3s;
        }
        .guess-input::placeholder{color:rgba(74,47,20,.4);font-style:italic}
        .guess-input:focus{border-bottom-color:var(--blood)}
        .guess-input:disabled{opacity:.5}

        .cta{
            font-family:'Pirata One', cursive;
            font-size:clamp(13px, 1.1vw, 16px);
            letter-spacing:.15em;text-transform:uppercase;
            padding:.55em 1.4em;
            background:linear-gradient(180deg, #7a1a0d 0%, #4a0f08 100%);
            color:var(--parchment);
            border:1px solid #2c0806;border-radius:2px;
            cursor:pointer;
            box-shadow:
                inset 0 1px 0 rgba(255,180,120,.25),
                inset 0 -2px 4px rgba(0,0,0,.4),
                0 3px 6px rgba(0,0,0,.5);
            text-shadow:0 1px 2px rgba(0,0,0,.6);
            transition:transform .12s, filter .2s;
        }
        .cta:hover{filter:brightness(1.15);transform:translateY(-1px)}
        .cta:active{transform:translateY(1px)}

        .meta{
            display:flex;justify-content:space-around;align-items:center;
            margin-top:1.2em;
            font-family:'IM Fell English', serif;
            font-size:clamp(11px, .9vw, 13px);
            color:var(--ink-soft);
            border-top:1px dotted rgba(74,47,20,.35);
            padding-top:.9em;
        }
        .meta-cell{display:flex;flex-direction:column;align-items:center;gap:.15em}
        .meta-label{font-style:italic;opacity:.65;font-size:.85em;letter-spacing:.1em;text-transform:uppercase}
        .meta-val{font-family:'Pirata One',cursive;font-size:1.7em;color:var(--blood);letter-spacing:.05em}
        .meta-val.gold{color:#8a6a20}

        .hint-btn{
            background:none;border:none;
            font-family:'IM Fell English',serif;font-style:italic;
            font-size:inherit;color:var(--blood);cursor:pointer;
            border-bottom:1px solid rgba(122,26,13,.4);
            padding:0 .1em 1px;
        }
        .hint-btn:hover{color:#a02818;border-bottom-color:#a02818}

        .status{
            min-height:1.5em;margin-top:.6em;
            font-family:'IM Fell English',serif;font-style:italic;
            font-size:clamp(12px, 1vw, 14px);
            color:var(--blood);
            opacity:0;transition:opacity .3s;
        }
        .status.show{opacity:1}
        .status.correct{color:#2d5016}

        .mark{
            position:fixed;bottom:1.4vh;right:1.5vw;z-index:30;
            font-family:'IM Fell English',serif;font-style:italic;
            font-size:11px;color:rgba(230,200,140,.35);
            letter-spacing:.25em;text-transform:uppercase;
        }

        .crest{
            position:fixed;top:2vh;right:2vw;z-index:30;
            text-align:right;
            font-family:'IM Fell English',serif;
            color:rgba(230,200,140,.55);
        }
        .crest .no{
            font-family:'Pirata One',cursive;
            font-size:clamp(20px,2vw,32px);
            color:var(--gold-bright);
            letter-spacing:.1em;
            text-shadow:0 0 20px rgba(255,180,80,.4);
        }
        .crest .lbl{
            font-style:italic;font-size:11px;
            letter-spacing:.3em;text-transform:uppercase;opacity:.7;
        }

        @media (max-width:900px){
            .parchment-content{width:68%}
        }

        /* ===== MOBILE: rotate entire page to force landscape on portrait phones ===== */
        @media (max-width:767px) and (orientation:portrait){
            /* Pivot the whole page 90° CCW — portrait phone shows landscape */
            html{
                transform:rotate(-90deg);
                transform-origin:left top;
                position:absolute;
                top:100svh;   /* drop below viewport; rotation swings content back into view */
                left:0;
                width:100svh; /* landscape width  = portrait device height */
                height:100svw;/* landscape height = portrait device width  */
                overflow:hidden;
            }
            body{min-height:unset;overflow:hidden}

            .stage{width:100%;height:100%;padding:0;align-items:center;justify-content:center}

            /* svh = portrait height = landscape width (primary sizing axis).
               svw cap prevents height from overflowing the landscape height. */
            .map-frame{
                width:min(76svh, calc(92svw * 1660 / 948));
                aspect-ratio:1660 / 948;
                transform:none;
                overflow:visible;
            }

            .map-canvas{overflow:hidden;border-radius:3px;transform:none}

            .scroll.laid-flat{
                position:absolute;inset:0;
                width:100%;height:100%;
                transform:none;
                object-fit:fill;
                filter:brightness(.72) saturate(.92);
            }

            .seadecal-layer{transform:none}
            .seadecal{width:13% !important}

            .weekpaper{width:min(65px,8svh)}
            .weekpaper-num{font-size:clamp(11px,1.8svh,16px)}

            .skull{position:absolute;width:min(130px,15svh);top:-4%;right:-4%}
            .isle {position:absolute;width:min(110px,13svh);bottom:-2%;right:-6%}
            .flag {width:min(120px,14svh)}

            #tzla-connect{top:2svw;left:2svw;transform:none;right:auto}
            .wallet-connect img{width:clamp(90px,14svh,130px)}
            .wallet-pill{font-size:9px;padding:.2em .5em;gap:.35em}

            #tzla-popup{width:92svw;max-height:90svw;font-size:.9em}
            .tzla-popup-inner{aspect-ratio:auto;min-height:80svw;padding:7% 5% 5%}
            .tzla-popup-hit{width:90%;height:auto;align-items:flex-start}
            #tzla-popup-body{width:100%;height:auto;max-height:62svw;overflow-y:auto}
            #tzla-popup[open] .tzla-popup-inner{animation:none;clip-path:none}
            #tzla-popup[open] #tzla-popup-body{animation:tzla-scroll-content 250ms ease 60ms forwards}
            .tzla-scrollside-img{display:none}
            .tzla-week-title{font-size:clamp(14px,3svw,22px)}
            .tzla-guess-row input{width:10ch}
            .tzla-guess-row button img{height:clamp(30px,7svw,50px)}

            #tzla-htp{width:90svw}
            .htp-inner{
                aspect-ratio:auto;min-height:80svw;
                padding:10% 8% 8%;
                display:flex;align-items:flex-start;justify-content:center;
            }
            .htp-content{width:100%;height:auto;max-height:65svw;overflow-y:auto;transform:none}
        }

        /* ==== WEEK STATE TINTS ==== */
        .weekpaper.is-locked{filter:brightness(.42) saturate(.5) grayscale(.4)}
        .weekpaper.is-partial{filter:brightness(.85) saturate(1) hue-rotate(-8deg)}
        .weekpaper.is-complete{filter:brightness(.95) saturate(1.1) hue-rotate(50deg)}

        /* ==== WALLET CONNECT PILL (replaces .crest) ==== */
        #tzla-connect{position:fixed;top:2vh;left:2vw;z-index:60;font-family:'IM Fell English',serif}
        .wallet-connect{
            background:none;border:none;padding:0;cursor:pointer;
            display:inline-block;line-height:0;
            transition:transform .12s, filter .2s;
        }
        .wallet-connect img{
            width:clamp(140px, 12vw, 220px);height:auto;display:block;
            filter:drop-shadow(0 3px 6px rgba(0,0,0,.5));
        }
        .wallet-connect:hover{filter:brightness(1.1);transform:translateY(-1px)}
        .wallet-connect:active{transform:translateY(1px)}
        .wallet-pill{
            display:inline-flex;align-items:center;gap:.6em;
            background:rgba(20,10,4,.72);color:rgba(240,215,160,.9);
            border:1px solid rgba(200,160,90,.35);border-radius:16px;
            padding:.35em .8em;font-size:12px;
            backdrop-filter:blur(4px);
        }
        .wallet-dot{width:8px;height:8px;border-radius:50%;background:#5cc46a;box-shadow:0 0 6px #5cc46a}
        .wallet-addr{font-family:'Pirata One',cursive;letter-spacing:.05em}
        .wallet-att{color:rgba(240,215,160,.55);font-style:italic}
        .wallet-disc{
            background:none;border:none;color:rgba(240,215,160,.7);cursor:pointer;
            font-size:1.2em;line-height:1;padding:0 .1em;
        }
        .wallet-disc:hover{color:#f4c66a}

        /* ==== POPUP DIALOG (weekwindow) ==== */
        #tzla-popup{
            /* Center in viewport regardless of UA styles. */
            position:fixed;
            top:50%; left:50%;
            transform:translate(-50%,-50%);
            margin:0;
            border:none;padding:0;background:transparent;color:var(--ink);
            width:min(1430px,96vw);
            max-height:92vh;
            font-size:1.3em;
            overflow:visible;
            outline:none;
        }
        #tzla-popup:focus,
        .tzla-popup-inner:focus,
        .tzla-popup-hit:focus,
        #tzla-popup-body:focus{ outline:none; }
        #tzla-popup::backdrop{background:rgba(5,3,1,.72);backdrop-filter:blur(3px)}

        .tzla-popup-inner{
            position:relative;
            /* the scroll image IS the background; content lives on top of the flat middle */
            background:url("{{ asset('storage/emptyscroll.png') }}") no-repeat center center;
            background-size:100% 100%;
            /* Lock the scroll to its natural aspect ratio so its shape never distorts.
               Content is centered inside via flexbox and cannot push the container taller. */
            aspect-ratio: 1.74 / 1;
            display:flex;
            align-items:center;
            justify-content:center;
            font-family:'IM Fell English',serif;
            /* start rolled up on the left: only the left rolled end is visible. */
            clip-path:inset(0 100% 0 0);
            transform-origin:left center;
        }
        /* Only animate when the dialog is actually open — avoids running on page load. */
        #tzla-popup[open] .tzla-popup-inner{
            animation:tzla-scroll-unroll 1000ms cubic-bezier(.22,.61,.36,1) 0.31s both;
        }
        @keyframes tzla-scroll-unroll{
            0%   { clip-path:inset(0 100% 0 0); }
            100% { clip-path:inset(0 0    0 0); }
        }

        /* Click-safe zone: sits inside the scroll image. Clicks outside this box
           (i.e., on the scroll's transparent edges or the backdrop) close the popup.
           Sized at 75% of the scroll container. */
        .tzla-popup-hit{
            width:85%;
            height:75%;
            display:flex;
            align-items:center;
            justify-content:center;
        }

        /* Content stays hidden until the scroll has unrolled, then fades in.
           Sized relative to the hit box to preserve its original container-relative footprint
           (68% × 52% of the scroll → ~90.7% × ~69.3% of the 75% × 75% hit). */
        #tzla-popup-body{
            opacity:0;
            width:80%;
            height:69.3%;
            overflow-y:auto;
            scrollbar-width:none;
        }
        #tzla-popup-body::-webkit-scrollbar{display:none}
        #tzla-popup[open] #tzla-popup-body{
            animation:tzla-scroll-content 420ms ease 980ms forwards;
        }
        @keyframes tzla-scroll-content{
            from{ opacity:0; transform:translateY(4px); }
            to  { opacity:1; transform:none; }
        }

        /* Scrollside: right-curl decoration that tracks the unrolling edge */
        .tzla-scrollside-img{
            position:absolute;
            top:0;left:0;
            width:100%;
            aspect-ratio:1.74 / 1;
            object-fit:contain;
            pointer-events:none;user-select:none;
            z-index:5;
            /* rest state: invisible and parked at its t=0 position */
            opacity:0;
            transform:translateX(-100%);
        }
        #tzla-popup[open] .tzla-scrollside-img{
            animation:tzla-scrollside-move 1000ms cubic-bezier(.22,.61,.36,1) 0.2s both;
        }
        @keyframes tzla-scrollside-move{
            0%  { opacity:1; transform:translateX(-100%); }
            100%{ opacity:1; transform:translateX(0);     }
        }

        .tzla-week-title{
            font-family:'Pirata One',cursive;font-size:clamp(20px,2.4vw,32px);
            color:var(--ink);text-align:center;margin:0 0 .3em;letter-spacing:.03em;
        }
        .tzla-reward{
            text-align:center;font-style:italic;color:var(--blood);
            margin-bottom:.9em;font-size:.95em;
        }
        .tzla-note{
            text-align:center;color:var(--ink-soft);font-style:italic;
            border:1px dashed rgba(74,47,20,.35);border-radius:3px;
            padding:.5em;margin-bottom:.9em;font-size:.9em;
        }
        .tzla-note strong{font-style:normal;color:var(--blood)}
        .tzla-profile{
            display:flex;gap:.5em;margin-bottom:.9em;flex-wrap:wrap;
        }
        .tzla-profile input{
            flex:1 1 12ch;min-width:0;background:transparent;
            border:none;border-bottom:1px dashed rgba(74,47,20,.4);
            padding:.35em .3em;font-family:inherit;font-size:.85em;color:var(--ink);
            outline:none;
        }
        .tzla-profile input:focus{border-bottom-color:var(--blood)}
        .tzla-profile input::placeholder{color:var(--ink-soft);opacity:.7;font-style:italic}
        .tzla-loading,.tzla-err{text-align:center;color:var(--ink-soft);font-style:italic;padding:1em}
        .tzla-err{color:var(--blood)}
        .tzla-done{
            margin-top:1em;text-align:center;font-family:'Pirata One',cursive;
            color:#2d5016;font-size:1.1em;letter-spacing:.05em;
        }
        .tzla-words{display:flex;flex-direction:column;gap:.9em}
        .tzla-word{
            padding:.7em .8em;
        }
        .tzla-word-head{
            display:flex;justify-content:space-between;align-items:baseline;
            font-family:'Pirata One',cursive;font-size:1.1em;color:var(--ink);
            margin-bottom:.3em;font-weight:700;
        }
        .tzla-word-n{letter-spacing:.05em}
        .tzla-tries{font-family:'IM Fell English',serif;font-style:italic;font-size:.9em;color:var(--ink);font-weight:700}
        .tzla-ok{color:#2d5016;font-family:'Pirata One',cursive;font-size:.95em;letter-spacing:.03em}
        .tzla-bad{color:var(--blood)}
        .tzla-hint{font-style:italic;color:var(--ink);margin-bottom:.5em;font-size:1em;line-height:1.4;font-weight:600}
        .tzla-guess-row{display:flex;gap:.4em;align-items:center}
        .tzla-guess-row input{
            width:18ch;flex:none;background:transparent;
            border:none;border-bottom:2px dashed rgba(74,47,20,.5);
            padding:.4em .3em;font-family:inherit;font-size:1em;color:var(--ink);
            outline:none;font-weight:700;
        }
        .tzla-guess-row input:focus{border-bottom-color:var(--blood)}
        .tzla-guess-row input:disabled{opacity:.5}
        .tzla-guess-row button{
            background:none;border:none;padding:0;cursor:pointer;
            display:inline-block;line-height:0;
            transition:transform .12s, filter .2s;
        }
        .tzla-guess-row button img{
            height:clamp(42px, 4vw, 62px);width:auto;display:block;
            filter:drop-shadow(0 2px 4px rgba(0,0,0,.4));
        }
        .tzla-guess-row button:hover{filter:brightness(1.1);transform:translateY(-1px)}
        .tzla-guess-row button:active{transform:translateY(1px)}
        .tzla-guess-row button:disabled{opacity:.45;cursor:not-allowed;transform:none;filter:grayscale(.4)}
        .tzla-word-status{margin-top:.4em;font-size:.85em;font-style:italic;min-height:1.2em;font-weight:600}

        #tzla-toast{
            position:fixed;bottom:2vh;left:50%;transform:translateX(-50%);
            background:rgba(20,10,4,.85);color:rgba(240,215,160,.95);
            padding:.6em 1.2em;border-radius:16px;
            font-family:'IM Fell English',serif;font-size:.9em;
            border:1px solid rgba(200,160,90,.4);
            opacity:0;pointer-events:none;transition:opacity .3s;
            z-index:100;
        }
        #tzla-toast.show{opacity:1}

        /* ==== HOW TO PLAY BUTTON ==== */
        .htp-btn{
            display:block;margin-top:.5em;
            position:relative;width:clamp(100px,9vw,140px);
            background:none;border:none;padding:0;cursor:pointer;
            transition:filter .2s, transform .15s;
            filter:brightness(.78) saturate(1.05) sepia(.3);
        }
        .htp-btn img{width:100%;height:auto;display:block}
        .htp-btn span{
            position:absolute;top:50%;left:50%;
            transform:translate(-50%,-54%);
            font-family:'Pirata One',cursive;
            font-size:clamp(9px,.85vw,13px);
            color:var(--ink);letter-spacing:.06em;
            white-space:nowrap;pointer-events:none;
            text-shadow:0 1px 0 rgba(255,220,150,.4);
        }
        .htp-btn:hover{filter:brightness(1.05) drop-shadow(0 0 8px rgba(255,190,90,.55));transform:translateY(-1px)}
        .htp-btn:active{transform:translateY(1px)}

        /* ==== HOW TO PLAY DIALOG ==== */
        #tzla-htp{
            position:fixed;top:50%;left:50%;
            transform:translate(-50%,-50%);
            margin:0;border:none;padding:0;background:transparent;
            color:var(--ink);
            width:min(1100px,95vw);
            outline:none;
        }
        #tzla-htp::backdrop{background:rgba(5,3,1,.75);backdrop-filter:blur(4px)}
        .htp-inner{
            background:url("{{ asset('storage/guesspaper.png') }}") no-repeat center center;
            background-size:100% 100%;
            aspect-ratio:2778/1562;
            position:relative;
            font-family:'IM Fell English',serif;
            display:flex;align-items:center;justify-content:center;
            box-shadow:0 12px 40px rgba(0,0,0,.7);
        }
        .htp-content{
            /* ── TUNE THESE to shift the content within the paper ── */
            --htp-nudge-x: 0%;   /* + moves right, - moves left  */
            --htp-nudge-y: -5%;  /* + moves down,  - moves up    */
            width:42%;height:68%;
            position:relative;
            transform:translate(var(--htp-nudge-x), var(--htp-nudge-y));
            overflow-y:auto;
            display:flex;flex-direction:column;
            justify-content:flex-start;
            scrollbar-width:none;
        }
        .htp-content::-webkit-scrollbar{display:none}
        .htp-title{
            font-family:'Pirata One',cursive;
            font-size:clamp(22px,2.4vw,32px);
            color:var(--ink);text-align:center;
            letter-spacing:.05em;
            margin-bottom:.15em;
        }
        .htp-sub{
            text-align:center;font-style:italic;
            font-size:.9em;color:var(--ink-soft);opacity:.7;
            margin-bottom:1.1em;letter-spacing:.1em;text-transform:uppercase;
        }
        .htp-section{margin-bottom:1em}
        .htp-section-title{
            font-family:'Pirata One',cursive;
            font-size:1.05em;letter-spacing:.12em;text-transform:uppercase;
            color:var(--blood);margin-bottom:.5em;
            border-bottom:1px dotted rgba(122,26,13,.3);padding-bottom:.2em;
        }
        .htp-tiers{display:flex;flex-direction:column;gap:.42em}
        .htp-tier{
            display:flex;align-items:center;gap:.7em;
            padding:.42em .65em;border-radius:3px;
            border:1px solid transparent;
            font-size:1em;line-height:1.4;
            transition:background .2s;
        }
        .htp-tier-icon{font-size:1.25em;flex-shrink:0;width:1.6em;text-align:center}
        .htp-tier-text{flex:1}
        .htp-tier-label{font-weight:700;color:var(--ink)}
        .htp-tier-desc{color:var(--ink-soft);font-style:italic;font-size:.92em}
        /* eligible highlight */
        .htp-tier.eligible{
            background:rgba(45,80,22,.1);
            border-color:rgba(45,80,22,.3);
        }
        .htp-tier.eligible .htp-tier-label{color:#2d5016}
        .htp-eligible-badge{
            font-size:.78em;font-style:normal;font-family:'IM Fell English',serif;
            background:#2d5016;color:#d4e8b0;
            padding:.1em .4em;border-radius:10px;white-space:nowrap;flex-shrink:0;
        }
        /* ineligible dim */
        .htp-tier.ineligible{opacity:.55}

        .htp-steps{
            display:flex;flex-direction:column;gap:.45em;
            list-style:none;padding:0;margin:0;
            font-size:1em;line-height:1.45;color:var(--ink-soft);
        }
        .htp-steps li{
            display:flex;gap:.6em;align-items:flex-start;
        }
        .htp-steps li::before{
            content:attr(data-n);
            font-family:'Pirata One',cursive;font-size:1.15em;
            color:var(--blood);opacity:.7;flex-shrink:0;width:1.2em;
            text-align:center;
        }
        .htp-divider{
            border:none;border-top:1px dotted rgba(74,47,20,.3);
            margin:1em 0;
        }
        .htp-not-eligible-note{
            text-align:center;font-style:italic;font-size:.88em;
            color:var(--blood);margin-top:.5em;
        }

        /* ==== PRIZES DIALOG ==== */
        #tzla-prizes{
            position:fixed;top:50%;left:50%;
            transform:translate(-50%,-50%);
            margin:0;border:none;padding:0;background:transparent;
            color:var(--ink);
            width:min(1100px,95vw);
            outline:none;
        }
        #tzla-prizes::backdrop{background:rgba(5,3,1,.75);backdrop-filter:blur(4px)}
        .prizes-inner{
            background:url("{{ asset('storage/guesspaper.png') }}") no-repeat center center;
            background-size:100% 100%;
            aspect-ratio:2778/1562;
            position:relative;
            font-family:'IM Fell English',serif;
            display:flex;align-items:center;justify-content:center;
            box-shadow:0 12px 40px rgba(0,0,0,.7);
        }
        .prizes-content{
            --prizes-nudge-x: 0%;
            --prizes-nudge-y: -5%;
            width:42%;height:68%;
            position:relative;
            transform:translate(var(--prizes-nudge-x), var(--prizes-nudge-y));
            overflow-y:auto;
            display:flex;flex-direction:column;
            justify-content:flex-start;
            scrollbar-width:none;
        }
        .prizes-content::-webkit-scrollbar{display:none}
        .prizes-list{display:flex;flex-direction:column;gap:.7em;margin-top:.6em}
        .prizes-week{
            display:flex;align-items:flex-start;gap:.7em;
            padding:.5em .65em;
        }
        .prizes-week-num{
            font-family:'Pirata One',cursive;font-size:1.15em;color:var(--blood);
            flex-shrink:0;width:1.8em;text-align:center;line-height:1.3;
        }
        .prizes-week-body{flex:1}
        .prizes-week-title{font-weight:700;color:var(--ink);font-size:1.05em;line-height:1.3}
        .prizes-week-reward{
            font-style:italic;color:var(--ink-soft);font-size:.95em;margin-top:.15em;line-height:1.35;
        }
        .prizes-week-reward em{color:var(--gold);font-style:normal;font-weight:700}
        .prizes-badge{
            flex-shrink:0;align-self:center;
            font-size:.75em;font-style:normal;font-family:'IM Fell English',serif;
            padding:.12em .5em;border-radius:10px;white-space:nowrap;
        }
        .prizes-badge.claimed{background:#2d5016;color:#d4e8b0}
        .prizes-badge.unclaimed{background:rgba(122,26,13,.12);color:var(--blood);border:1px solid rgba(122,26,13,.3)}
        .prizes-empty{
            text-align:center;font-style:italic;color:var(--ink-soft);
            font-size:.9em;margin-top:1em;
        }

        /* ==== REWARD DETAIL DIALOG ==== */
        #tzla-reward-detail{
            position:fixed;top:50%;left:50%;
            transform:translate(-50%,-50%);
            margin:0;border:none;padding:0;background:transparent;
            color:var(--ink);
            width:min(700px,85vw);
            outline:none;
        }
        #tzla-reward-detail::backdrop{background:rgba(5,3,1,.82);backdrop-filter:blur(5px)}
        .prizes-content--detail{
            width:55%;height:auto;min-height:30%;
            justify-content:center;
            gap:.6em;
            padding:.5em 0;
        }
        .reward-detail-week-num{
            font-family:'Pirata One',cursive;font-size:2em;color:var(--blood);
            text-align:center;line-height:1.2;margin-bottom:.1em;
        }
        .reward-detail-title{
            font-size:1.25em;text-align:center;font-weight:700;margin-bottom:.2em;
        }
        .reward-detail-reward{
            font-size:1.15em;text-align:center;margin-top:.1em;
        }
        .reward-detail-badge{
            text-align:center;margin-top:.6em;
        }
        .reward-detail-badge .prizes-badge{
            font-size:.9em;
        }

        @media (max-width:768px){
            #tzla-prizes{width:96vw}
            #tzla-reward-detail{width:96vw}
            .prizes-inner{
                aspect-ratio:auto;min-height:85vh;
                padding:14% 10% 10%;
                display:flex;align-items:flex-start;justify-content:center;
            }
            .prizes-content{width:100%;height:auto;max-height:65vh;overflow-y:auto;transform:none}
            .prizes-content--detail{min-height:unset;max-height:65vh;padding:.5em 0}
        }
        @media (max-width:480px){
            .prizes-inner{padding:18% 8% 8%;min-height:90vh}
            .prizes-content{max-height:62vh}
        }
    </style>
</head>
<body>

    <div class="shack"></div>

    <div id="tzla-connect"></div>

    <div class="dust">
        <span class="mote" style="left:8%;animation-delay:0s"></span>
        <span class="mote" style="left:18%;animation-delay:3s"></span>
        <span class="mote" style="left:26%;animation-delay:7s"></span>
        <span class="mote" style="left:33%;animation-delay:1.5s"></span>
        <span class="mote" style="left:42%;animation-delay:9s"></span>
        <span class="mote" style="left:14%;animation-delay:5s"></span>
        <span class="mote" style="left:22%;animation-delay:11s"></span>
        <span class="mote" style="left:37%;animation-delay:2.5s"></span>
    </div>

    <main class="stage">
        <div class="map-frame">

            <div class="map-canvas">
                <img src="{{ asset('storage/emptyscroll2.png') }}" alt="" class="scroll laid-flat" />

                @php
                    $seadecalPositions = [
                        1 => ['top' => '30%', 'left' => '16%', 'w' => '15%'],
                        2 => ['top' => '32%', 'left' => '38%', 'w' => '13%'],
                        3 => ['top' => '28%', 'left' => '74%', 'w' => '13%'],
                        4 => ['top' => '60%', 'left' => '10%', 'w' => '16%'],
                        5 => ['top' => '30%', 'left' => '56%', 'w' => '13%'],
                        6 => ['top' => '48%', 'left' => '66%', 'w' => '17%'],
                        7 => ['top' => '48%', 'left' => '26%', 'w' => '16%'],
                        8 => ['top' => '50%', 'left' => '48%', 'w' => '15%'],
                        9 => ['top' => '60%', 'left' => '78%', 'w' => '16%'],
                    ];
                @endphp
                <div class="seadecal-layer">
                    @foreach ($seadecalPositions as $n => $pos)
                        <img src="{{ asset('storage/seadecal' . $n . '.png') }}" alt=""
                             class="seadecal"
                             style="top:{{ $pos['top'] }};left:{{ $pos['left'] }};width:{{ $pos['w'] }}" />
                    @endforeach
                    @php
                        $weekPaperPositions = [
                            1 => ['top'=>'39%','left'=>'8%','rot'=>'-8'],
                            2 => ['top'=>'50%','left'=>'28%','rot'=>'9'],
                            3 => ['top'=>'30%','left'=>'46%','rot'=>'6'],
                            4 => ['top'=>'47%','left'=>'62%','rot'=>'-5'],
                            5 => ['top'=>'27%','left'=>'72%','rot'=>'-11'],
                        ];
                    @endphp
                    @foreach($weekPaperPositions as $wn => $p)
                        <div class="weekpaper" data-week="{{ $wn }}"
                             style="top:{{ $p['top'] }};left:{{ $p['left'] }};--rot:{{ $p['rot'] }}deg;animation-delay:{{ number_format(0.9 + ($wn - 1) * 0.13, 2) }}s">
                            <img src="{{ asset('storage/weekpaper.png') }}" alt="" />
                            <span class="weekpaper-num">{{ $wn }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <img src="{{ asset('storage/pirateskull.png') }}" alt="" class="skull" />
            <img src="{{ asset('storage/pirateflag.png') }}" alt="" class="flag" />
            <img src="{{ asset('storage/compass.png') }}" alt="" class="isle" />

        </div>
    </main>

    <!-- LIGHTING LAYERS — sit above all content so they actually affect the scene -->
    <div class="shadowcast"></div>
    <div class="warmcast"></div>
    <div class="grain"></div>

    <dialog id="tzla-popup">
        <div class="tzla-popup-inner">
            <div class="tzla-popup-hit">
                <div id="tzla-popup-body"></div>
            </div>
        </div>
        <img src="{{ asset('storage/scollside.png') }}" alt="" class="tzla-scrollside-img" id="tzla-scrollside" />
    </dialog>

    <dialog id="tzla-htp">
        <div class="htp-inner">
            <div class="htp-content">
                <h2 class="htp-title">The Captain's Riddle</h2>
                <p class="htp-sub">Rules of the Hunt</p>

                <div class="htp-section">
                    <div class="htp-section-title">Who May Enter</div>
                    <div class="htp-tiers" id="htp-tiers">
                        <div class="htp-tier" data-tier="golden">
                            <span class="htp-tier-icon">★</span>
                            <span class="htp-tier-text">
                                <span class="htp-tier-label">Golden Ticket NFT</span><br>
                                <span class="htp-tier-desc">Any Golden Ticket — maximum reward rate</span>
                            </span>
                        </div>
                        <div class="htp-tier" data-tier="nft">
                            <span class="htp-tier-icon">◈</span>
                            <span class="htp-tier-text">
                                <span class="htp-tier-label">TZLA NFT holder</span><br>
                                <span class="htp-tier-desc">One or more TZLA collection NFTs</span>
                            </span>
                        </div>
                        <div class="htp-tier" data-tier="token">
                            <span class="htp-tier-icon">⚓</span>
                            <span class="htp-tier-text">
                                <span class="htp-tier-label">9+ TZLA Tokens</span><br>
                                <span class="htp-tier-desc">Hold at least 9 TZLA in your wallet</span>
                            </span>
                        </div>
                    </div>
                    <p class="htp-not-eligible-note" id="htp-not-eligible" style="display:none">
                        Ye lack the coin or credentials to play — acquire a TZLA NFT or 9 TZLA tokens to join the hunt.
                    </p>
                </div>

                <hr class="htp-divider">

                <div class="htp-section">
                    <div class="htp-section-title">How to Play</div>
                    <ol class="htp-steps">
                        <li data-n="I">Each week holds hidden words. Click a scroll on the map to open that week.</li>
                        <li data-n="II">Study the clue for each word, then enter your best guess.</li>
                        <li data-n="III">NFT holders earn extra attempts per word — more NFTs, more tries.</li>
                        <li data-n="IV">Solve all words in a week to claim the treasure reward.</li>
                        <li data-n="V">Each guess costs a small SOL fee sent to the treasury.</li>
                    </ol>
                </div>
            </div>
        </div>
    </dialog>

    <dialog id="tzla-prizes">
        <div class="prizes-inner">
            <div class="prizes-content">
                <h2 class="htp-title">The Bounty Board</h2>
                <p class="htp-sub">Weekly Prizes</p>
                <div class="prizes-list" id="prizes-list">
                    <p class="prizes-empty">Loading the ledger…</p>
                </div>
            </div>
        </div>
    </dialog>

    <dialog id="tzla-reward-detail">
        <div class="prizes-inner">
            <div class="prizes-content prizes-content--detail" id="reward-detail-content">
            </div>
        </div>
    </dialog>

    <div id="tzla-toast"></div>

</body>
</html>
