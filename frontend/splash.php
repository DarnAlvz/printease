<?php
require_once __DIR__ . '/../backend/config/app.php';
require_once __DIR__ . '/components/head.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#070566">
    <title>PrintEase</title>
    <?php renderPrintEaseIcons(); ?>
    <style>
        :root {
            --navy: #070566;
            --navy-deep: #05035f;
            --cyan: #08b7d0;
            --cyan-dark: #008fc1;
            --loader-size: clamp(180px, 42vw, 250px);
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            width: 100%;
            min-height: 100%;
            margin: 0;
        }

        body {
            min-height: 100vh;
            min-height: 100dvh;
            display: grid;
            place-items: center;
            padding: 24px;
            background:
                radial-gradient(circle at 50% 42%, rgba(8, 183, 208, .22), transparent 28%),
                linear-gradient(145deg, var(--navy-deep) 0%, var(--navy) 52%, var(--cyan-dark) 100%);
            font-family: Arial, sans-serif;
        }

        .splash {
            display: grid;
            justify-items: center;
            gap: 18px;
            color: #fff;
            text-align: center;
            animation: splash-in .35s ease-out both;
        }

        .splash.is-leaving {
            animation: splash-out .25s ease-in both;
        }

        .logo-loader {
            position: relative;
            width: var(--loader-size);
            aspect-ratio: 1;
            display: grid;
            place-items: center;
        }

        .spinner-ring {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            overflow: visible;
            filter: drop-shadow(0 0 10px rgba(8, 183, 208, .82));
            transform-box: fill-box;
            transform-origin: center;
            animation: ring-spin 2s linear 1 both;
        }

        .spinner-track {
            fill: none;
            stroke: rgba(255, 255, 255, .2);
            stroke-width: 4;
        }

        .spinner-arc {
            fill: none;
            stroke: url(#spinnerGradient);
            stroke-width: 6;
            stroke-linecap: round;
            stroke-dasharray: 112 188;
        }

        .spinner-head {
            fill: #fff;
            filter: drop-shadow(0 0 5px #fff) drop-shadow(0 0 8px var(--cyan));
        }

        .brand-logo {
            position: relative;
            z-index: 1;
            width: 78%;
            aspect-ratio: 1;
            object-fit: contain;
            border-radius: 50%;
            filter: drop-shadow(0 16px 30px rgba(0, 0, 0, .24));
        }

        .loading-status {
            width: min(190px, 64vw);
            margin-top: -7px;
        }

        .loading-label {
            display: block;
            margin-bottom: 8px;
            color: rgba(255, 255, 255, .78);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .16em;
            text-transform: uppercase;
        }

        .loading-track {
            height: 4px;
            overflow: hidden;
            border-radius: 999px;
            background: rgba(255, 255, 255, .18);
        }

        .loading-progress {
            width: 100%;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, #fff 0%, var(--cyan) 58%, #8cefff 100%);
            box-shadow: 0 0 12px rgba(8, 183, 208, .8);
            transform: scaleX(0);
            transform-origin: left center;
            animation: loading-progress 2s linear 1 both;
        }

        .noscript-link {
            color: #fff;
            font-size: 15px;
            text-underline-offset: 4px;
        }

        @keyframes ring-spin {
            to {
                transform: rotate(720deg);
            }
        }

        @keyframes loading-progress {
            to {
                transform: scaleX(1);
            }
        }

        @keyframes splash-in {
            from {
                opacity: 0;
                transform: scale(.96);
            }

            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        @keyframes splash-out {
            to {
                opacity: 0;
                transform: scale(.98);
            }
        }

        @media (max-height: 500px) and (orientation: landscape) {
            :root {
                --loader-size: min(58vh, 190px);
            }

            .splash {
                grid-template-columns: auto auto;
                align-items: center;
                column-gap: 24px;
            }

            .loading-status {
                grid-column: 2;
            }
        }
    </style>
</head>

<body>
    <main class="splash" id="printEaseSplash" aria-label="PrintEase is loading">
        <div class="logo-loader" aria-hidden="true">
            <svg class="spinner-ring" id="splashSpinner" viewBox="0 0 100 100">
                <defs>
                    <linearGradient id="spinnerGradient" x1="0" y1="0" x2="1" y2="1">
                        <stop offset="0%" stop-color="#ffffff"></stop>
                        <stop offset="48%" stop-color="#8cefff"></stop>
                        <stop offset="100%" stop-color="#08b7d0"></stop>
                    </linearGradient>
                </defs>
                <circle class="spinner-track" cx="50" cy="50" r="47"></circle>
                <circle class="spinner-arc" cx="50" cy="50" r="47" transform="rotate(-90 50 50)"></circle>
                <circle class="spinner-head" cx="50" cy="3" r="3.2"></circle>
            </svg>
            <?php renderPrintEaseLogo(['class' => 'brand-logo', 'decorative' => true]); ?>
        </div>
        <div class="loading-status" aria-live="polite">
            <span class="loading-label" id="loadingLabel">Loading</span>
            <div class="loading-track" aria-hidden="true">
                <div class="loading-progress"></div>
            </div>
        </div>
    </main>

    <noscript>
        <a class="noscript-link" href="../index.php">Continue to PrintEase</a>
    </noscript>

    <script>
        (function () {
            const splash = document.getElementById('printEaseSplash');
            const spinner = document.getElementById('splashSpinner');
            const loadingLabel = document.getElementById('loadingLabel');
            const destination = '../index.php';
            const completionPauseMs = 250;
            const exitDurationMs = 250;
            let isFinishing = false;

            function finishSplash() {
                if (isFinishing) return;
                isFinishing = true;
                loadingLabel.textContent = 'Ready';

                window.setTimeout(function () {
                    splash.classList.add('is-leaving');
                    window.setTimeout(function () {
                        window.location.replace(destination);
                    }, exitDurationMs);
                }, completionPauseMs);
            }

            spinner.addEventListener('animationend', function (event) {
                if (event.animationName === 'ring-spin') {
                    finishSplash();
                }
            });

            window.setTimeout(finishSplash, 2300);
        })();
    </script>
</body>

</html>
