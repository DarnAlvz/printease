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
    <link rel="prefetch" href="../index.php">
    <style>
        :root {
            --navy: #070566;
            --navy-deep: #05035f;
            --cyan: #08b7d0;
            --cyan-dark: #008fc1;
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
            background: var(--navy);
            font-family: Arial, sans-serif;
        }

        .splash {
            width: 100%;
            min-height: 100vh;
            min-height: 100dvh;
            display: grid;
            place-items: center;
            justify-items: center;
            gap: 18px;
            color: #fff;
            text-align: center;
            background:
                radial-gradient(circle at 50% 42%, rgba(8, 183, 208, .22), transparent 28%),
                linear-gradient(145deg, var(--navy-deep) 0%, var(--navy) 52%, var(--cyan-dark) 100%);
            animation: splash-in .35s ease-out both;
        }

        .splash-status {
            position: relative;
            z-index: 1;
            font-size: 14px;
            color: rgba(255, 255, 255, .7);
            letter-spacing: .5px;
            margin-top: 12px;
        }
        
        .logo-loader {
            position: relative;
            width: var(--loader-size);
            height: var(--loader-size);
        
            display: flex;
            align-items: center;
            justify-content: center;
        }

      .spinner-ring {
            position: absolute;
        
            /* Bigger than logo container */
            width: 125%;
            height: 125%;
        
            top: -12.5%;
            left: -12.5%;
        
            overflow: visible;
        
            filter: drop-shadow(0 0 10px rgba(8, 183, 208, .82));
        
            transform-box: fill-box;
            transform-origin: center;
        
            animation: ring-spin 1s linear infinite both;
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
        
            z-index: 2;
        
            /* Responsive logo size */
            width: 52%;
            height: 52%;
        
            object-fit: contain;
        
            border-radius: 18%;
        
            filter:
                drop-shadow(0 16px 30px rgba(0,0,0,.24));
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

        @media (max-height: 500px) and (orientation: landscape) {
            :root {
                --loader-size: min(58vh, 190px);
            }

            .splash {
                grid-template-columns: auto auto;
                align-items: center;
                column-gap: 24px;
            }
        }
        
        @media (max-width: 360px) {

            :root {
                --loader-size: 160px;
            }
        
            .brand-logo {
                width: 50%;
                height: 50%;
            }
        
        }
        
        @media (min-width: 768px) {

            :root {
                --loader-size: 300px;
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
    </main>

    <noscript>
        <a class="noscript-link" href="../index.php">Continue to PrintEase</a>
    </noscript>

    <script nonce="<?php echo $GLOBALS['csp_nonce'] ?? ''; ?>">
        (function () {
            var destination = '../index.php';
            var maxWaitMs = 10000;
            var logoReady = false;
            var pageReady = false;
            var done = false;

            function checkReady() {
                if (done) return;
                if (logoReady && pageReady) {
                    done = true;
                    window.location.replace(destination);
                }
            }

            var logo = document.querySelector('.brand-logo');
            if (logo) {
                if (logo.complete) {
                    logoReady = true;
                } else {
                    logo.onload = function () {
                        logoReady = true;
                        checkReady();
                    };
                    logo.onerror = function () {
                        logoReady = true;
                        checkReady();
                    };
                }
            } else {
                logoReady = true;
            }

            fetch(destination, { credentials: 'same-origin' })
                .then(function () {
                    pageReady = true;
                    checkReady();
                })
                .catch(function () {
                    pageReady = true;
                    checkReady();
                });

            setTimeout(function () {
                if (!done) {
                    done = true;
                    window.location.replace(destination);
                }
            }, maxWaitMs);
        })();
    </script>
</body>

</html>
