<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OnPoint Mobile Test</title>
    <style>
        body {
            font-family: monospace;
            background: #000;
            color: #0f0;
            padding: 10px;
            font-size: 12px;
        }
        .test {
            background: #111;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #0f0;
        }
        .error { color: #f00; }
        .success { color: #0f0; }
        .warn { color: #ff0; }
        button {
            background: #0f0;
            color: #000;
            border: none;
            padding: 15px;
            margin: 5px;
            font-weight: bold;
            font-size: 14px;
            width: 100%;
        }
    </style>
</head>
<body>
    <h1>🔍 OnPoint Mobile Test</h1>

    <div class="test">
        <h3>Step 1: Visit your real page</h3>
        <button onclick="window.location.href='https://dev.vetcelerator.com/onpoint/'">Go to OnPoint Page</button>
    </div>

    <div class="test">
        <h3>Step 2: Open Browser Console</h3>
        <p>On iPhone Safari:</p>
        <ol>
            <li>Settings → Safari → Advanced → Web Inspector (turn ON)</li>
            <li>Connect iPhone to Mac</li>
            <li>Mac: Safari → Develop → [Your iPhone] → dev.vetcelerator.com</li>
        </ol>
        <p>Or use this button to see console here:</p>
        <button onclick="showConsole()">Show Console Logs</button>
    </div>

    <div class="test">
        <h3>Step 3: Test Arrows</h3>
        <p>When on /onpoint/ page:</p>
        <ol>
            <li>Look for arrows on the sides of slider</li>
            <li>Try tapping the arrows</li>
            <li>See if slider moves</li>
        </ol>
    </div>

    <div class="test">
        <h3>Step 4: Check These in Console</h3>
        <pre id="checks" style="color: #fff; background: #222; padding: 10px;">
Checking...
        </pre>
    </div>

    <div class="test">
        <h3>Quick Tests</h3>
        <button onclick="testJQuery()">Test 1: Check jQuery</button>
        <button onclick="testSlick()">Test 2: Check Slick</button>
        <button onclick="testSlider()">Test 3: Find Slider</button>
        <button onclick="testArrows()">Test 4: Check Arrows</button>
        <div id="results" style="margin-top: 10px; color: #fff;"></div>
    </div>

    <script>
    var log = [];

    function addLog(msg, type) {
        type = type || 'info';
        var color = type === 'error' ? 'red' : type === 'success' ? 'lime' : type === 'warn' ? 'yellow' : 'white';
        log.push('<div style="color:' + color + '">' + msg + '</div>');
        console.log('[TEST] ' + msg);
    }

    function showResults() {
        document.getElementById('results').innerHTML = log.join('');
    }

    function testJQuery() {
        log = [];
        if (typeof jQuery !== 'undefined') {
            addLog('✓ jQuery loaded: v' + jQuery.fn.jquery, 'success');
        } else {
            addLog('✗ jQuery NOT loaded', 'error');
        }
        showResults();
    }

    function testSlick() {
        log = [];
        if (typeof jQuery === 'undefined') {
            addLog('✗ jQuery not loaded first', 'error');
        } else if (typeof jQuery.fn.slick !== 'undefined') {
            addLog('✓ Slick loaded', 'success');
        } else {
            addLog('✗ Slick NOT loaded', 'error');
        }
        showResults();
    }

    function testSlider() {
        log = [];
        if (typeof jQuery === 'undefined') {
            addLog('✗ jQuery not loaded', 'error');
            showResults();
            return;
        }

        var sliders = jQuery('.grs-direct-slider');
        addLog('Sliders found: ' + sliders.length, sliders.length > 0 ? 'success' : 'error');

        var initialized = jQuery('.grs-direct-slider.slick-initialized');
        addLog('Initialized: ' + initialized.length, initialized.length > 0 ? 'success' : 'warn');

        showResults();
    }

    function testArrows() {
        log = [];
        if (typeof jQuery === 'undefined') {
            addLog('✗ jQuery not loaded', 'error');
            showResults();
            return;
        }

        var prevArrow = jQuery('.slick-prev');
        var nextArrow = jQuery('.slick-next');

        addLog('Prev arrows found: ' + prevArrow.length, prevArrow.length > 0 ? 'success' : 'error');
        addLog('Next arrows found: ' + nextArrow.length, nextArrow.length > 0 ? 'success' : 'error');

        if (prevArrow.length > 0) {
            addLog('Prev visible: ' + prevArrow.is(':visible'), prevArrow.is(':visible') ? 'success' : 'warn');
            addLog('Prev display: ' + prevArrow.css('display'), 'info');
        }

        if (nextArrow.length > 0) {
            addLog('Next visible: ' + nextArrow.is(':visible'), nextArrow.is(':visible') ? 'success' : 'warn');
            addLog('Next display: ' + nextArrow.css('display'), 'info');
        }

        showResults();
    }

    function showConsole() {
        alert('Instructions:\n\n1. Long press anywhere on this page\n2. Tap "Inspect Element"\n3. Tap "Console" tab\n4. You will see all JavaScript logs\n\nOr connect to Mac and use Safari Web Inspector');
    }

    // Auto-run checks
    setTimeout(function() {
        var checks = '';
        checks += 'Device: ' + navigator.userAgent.substring(0, 50) + '...\n';
        checks += 'Screen: ' + screen.width + 'x' + screen.height + '\n';
        checks += 'Window: ' + window.innerWidth + 'x' + window.innerHeight + '\n';
        checks += 'Touch: ' + ('ontouchstart' in window) + '\n';
        checks += 'jQuery: ' + (typeof jQuery !== 'undefined' ? 'YES (v' + jQuery.fn.jquery + ')' : 'NO') + '\n';
        checks += 'Slick: ' + (typeof jQuery !== 'undefined' && typeof jQuery.fn.slick !== 'undefined' ? 'YES' : 'NO') + '\n';

        document.getElementById('checks').textContent = checks;
    }, 500);
    </script>
</body>
</html>
