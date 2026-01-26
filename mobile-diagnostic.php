<?php
/**
 * Mobile Diagnostic Page for Google Reviews Slider
 *
 * Upload this file to your WordPress root directory
 * Access it at: https://yoursite.com/mobile-diagnostic.php
 *
 * This will show comprehensive diagnostics for mobile slider issues
 */

// Load WordPress
require_once('wp-load.php');

// Force mobile debug mode
define('GRS_MOBILE_DEBUG', true);

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>GRS Mobile Diagnostic</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f5f5f5;
            padding: 10px;
        }
        .diagnostic-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }
        .diagnostic-section {
            background: white;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .diagnostic-section h3 {
            color: #667eea;
            margin-bottom: 10px;
            font-size: 16px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            font-weight: 600;
            color: #555;
        }
        .info-value {
            color: #333;
            text-align: right;
        }
        .status-good {
            color: #10b981;
            font-weight: bold;
        }
        .status-bad {
            color: #ef4444;
            font-weight: bold;
        }
        .status-warn {
            color: #f59e0b;
            font-weight: bold;
        }
        .console-log {
            background: #1e1e1e;
            color: #0f0;
            font-family: 'Courier New', monospace;
            font-size: 11px;
            padding: 15px;
            border-radius: 5px;
            max-height: 300px;
            overflow-y: auto;
            margin-top: 10px;
        }
        .console-log div {
            margin-bottom: 3px;
            line-height: 1.4;
        }
        .test-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 15px;
        }
        .test-button {
            background: #667eea;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            touch-action: manipulation;
        }
        .test-button:active {
            background: #5568d3;
            transform: scale(0.98);
        }
        .slider-container {
            margin-top: 20px;
            background: white;
            padding: 20px;
            border-radius: 10px;
        }
        #diagnostic-log {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0,0,0,0.95);
            color: #0f0;
            font-family: monospace;
            font-size: 10px;
            padding: 10px;
            max-height: 150px;
            overflow-y: auto;
            z-index: 999999;
            display: none;
        }
        #diagnostic-log.show {
            display: block;
        }
        .toggle-log {
            position: fixed;
            bottom: 160px;
            right: 10px;
            background: #0f0;
            color: #000;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            font-weight: bold;
            z-index: 1000000;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="diagnostic-header">
        <h1>🔍 GRS Mobile Diagnostic</h1>
        <p style="margin-top: 10px; opacity: 0.9;">Real-time slider debugging</p>
    </div>

    <div class="diagnostic-section">
        <h3>📱 Device Information</h3>
        <div class="info-row">
            <span class="info-label">User Agent:</span>
            <span class="info-value" id="user-agent">...</span>
        </div>
        <div class="info-row">
            <span class="info-label">Screen Size:</span>
            <span class="info-value" id="screen-size">...</span>
        </div>
        <div class="info-row">
            <span class="info-label">Window Size:</span>
            <span class="info-value" id="window-size">...</span>
        </div>
        <div class="info-row">
            <span class="info-label">Touch Support:</span>
            <span class="info-value" id="touch-support">...</span>
        </div>
        <div class="info-row">
            <span class="info-label">Device Type:</span>
            <span class="info-value" id="device-type">...</span>
        </div>
        <div class="info-row">
            <span class="info-label">Pixel Ratio:</span>
            <span class="info-value" id="pixel-ratio">...</span>
        </div>
    </div>

    <div class="diagnostic-section">
        <h3>📚 Library Status</h3>
        <div class="info-row">
            <span class="info-label">jQuery:</span>
            <span class="info-value" id="jquery-status">...</span>
        </div>
        <div class="info-row">
            <span class="info-label">Slick Carousel:</span>
            <span class="info-value" id="slick-status">...</span>
        </div>
        <div class="info-row">
            <span class="info-label">GRS Plugin:</span>
            <span class="info-value" id="grs-status">...</span>
        </div>
    </div>

    <div class="diagnostic-section">
        <h3>🎢 Slider Status</h3>
        <div class="info-row">
            <span class="info-label">Sliders Found:</span>
            <span class="info-value" id="sliders-found">...</span>
        </div>
        <div class="info-row">
            <span class="info-label">Initialized:</span>
            <span class="info-value" id="sliders-initialized">...</span>
        </div>
        <div class="info-row">
            <span class="info-label">Arrows Visible:</span>
            <span class="info-value" id="arrows-visible">...</span>
        </div>
        <div class="info-row">
            <span class="info-label">Autoplay Status:</span>
            <span class="info-value" id="autoplay-status">...</span>
        </div>
        <div class="info-row">
            <span class="info-label">Current Slide:</span>
            <span class="info-value" id="current-slide">...</span>
        </div>
    </div>

    <div class="diagnostic-section">
        <h3>🧪 Interactive Tests</h3>
        <div class="test-buttons">
            <button class="test-button" onclick="testPrevArrow()">◀ Test Prev</button>
            <button class="test-button" onclick="testNextArrow()">Test Next ▶</button>
            <button class="test-button" onclick="testAutoplay()">▶ Start Auto</button>
            <button class="test-button" onclick="testRefresh()">🔄 Refresh</button>
        </div>
    </div>

    <div class="diagnostic-section">
        <h3>📋 Event Log</h3>
        <div class="console-log" id="event-log"></div>
    </div>

    <div class="slider-container">
        <h3 style="margin-bottom: 15px; color: #667eea;">📱 Test Slider</h3>
        <?php echo do_shortcode('[google_reviews_slider]'); ?>
    </div>

    <button class="toggle-log" onclick="toggleDiagLog()">Show/Hide Log</button>
    <div id="diagnostic-log"></div>

    <?php wp_footer(); ?>

    <script>
    (function() {
        var log = [];
        var logElement = document.getElementById('event-log');
        var diagLogElement = document.getElementById('diagnostic-log');

        function addLog(message, type) {
            type = type || 'info';
            var timestamp = new Date().toLocaleTimeString();
            var color = type === 'error' ? '#f00' : type === 'warn' ? '#ff0' : type === 'success' ? '#0f0' : '#888';

            var logLine = '<div style="color: ' + color + '">[' + timestamp + '] ' + message + '</div>';
            log.push(logLine);

            logElement.innerHTML += logLine;
            diagLogElement.innerHTML += logLine;

            logElement.scrollTop = logElement.scrollHeight;
            diagLogElement.scrollTop = diagLogElement.scrollHeight;

            console.log('[DIAGNOSTIC] ' + message);
        }

        window.diagLog = addLog;
        window.toggleDiagLog = function() {
            diagLogElement.classList.toggle('show');
        };

        // Device detection
        function detectDevice() {
            var ua = navigator.userAgent;
            var isIOS = /iPad|iPhone|iPod/.test(ua) && !window.MSStream;
            var isAndroid = /Android/.test(ua);
            var isMobile = /Mobi|Android/i.test(ua);

            document.getElementById('user-agent').textContent = ua.substring(0, 50) + '...';
            document.getElementById('screen-size').textContent = screen.width + 'x' + screen.height;
            document.getElementById('window-size').textContent = window.innerWidth + 'x' + window.innerHeight;
            document.getElementById('touch-support').innerHTML = ('ontouchstart' in window) ?
                '<span class="status-good">YES ✓</span>' : '<span class="status-bad">NO ✗</span>';
            document.getElementById('pixel-ratio').textContent = (window.devicePixelRatio || 1) + 'x';

            var deviceType = 'Desktop';
            if (isIOS) deviceType = 'iOS';
            else if (isAndroid) deviceType = 'Android';
            else if (isMobile) deviceType = 'Mobile';

            document.getElementById('device-type').innerHTML = '<span class="status-good">' + deviceType + '</span>';

            addLog('Device detected: ' + deviceType, 'info');
            addLog('Screen: ' + screen.width + 'x' + screen.height, 'info');
            addLog('Touch: ' + ('ontouchstart' in window), 'info');
        }

        // Check libraries
        function checkLibraries() {
            setTimeout(function() {
                // jQuery
                if (typeof jQuery !== 'undefined') {
                    document.getElementById('jquery-status').innerHTML =
                        '<span class="status-good">✓ v' + jQuery.fn.jquery + '</span>';
                    addLog('jQuery loaded: v' + jQuery.fn.jquery, 'success');
                } else {
                    document.getElementById('jquery-status').innerHTML =
                        '<span class="status-bad">✗ NOT LOADED</span>';
                    addLog('jQuery NOT loaded!', 'error');
                    return;
                }

                // Slick
                if (typeof jQuery.fn.slick !== 'undefined') {
                    document.getElementById('slick-status').innerHTML =
                        '<span class="status-good">✓ Loaded</span>';
                    addLog('Slick carousel loaded', 'success');
                } else {
                    document.getElementById('slick-status').innerHTML =
                        '<span class="status-bad">✗ NOT LOADED</span>';
                    addLog('Slick carousel NOT loaded!', 'error');
                }

                // GRS
                document.getElementById('grs-status').innerHTML =
                    '<span class="status-good">✓ Active</span>';
                addLog('GRS plugin active', 'success');

                checkSliders();
            }, 1000);
        }

        // Check sliders
        function checkSliders() {
            setTimeout(function() {
                if (typeof jQuery === 'undefined') return;

                var $ = jQuery;
                var sliders = $('.grs-direct-slider');
                var initialized = $('.grs-direct-slider.slick-initialized');

                document.getElementById('sliders-found').innerHTML =
                    sliders.length > 0 ? '<span class="status-good">' + sliders.length + '</span>' :
                    '<span class="status-bad">0</span>';

                document.getElementById('sliders-initialized').innerHTML =
                    initialized.length > 0 ? '<span class="status-good">' + initialized.length + '</span>' :
                    '<span class="status-bad">0</span>';

                addLog('Sliders found: ' + sliders.length, sliders.length > 0 ? 'success' : 'error');
                addLog('Initialized: ' + initialized.length, initialized.length > 0 ? 'success' : 'warn');

                if (initialized.length > 0) {
                    var $slider = initialized.first();

                    // Check arrows
                    var prevArrow = $slider.find('.slick-prev');
                    var nextArrow = $slider.find('.slick-next');
                    var arrowsVisible = prevArrow.is(':visible') && nextArrow.is(':visible');

                    document.getElementById('arrows-visible').innerHTML = arrowsVisible ?
                        '<span class="status-good">YES ✓</span>' :
                        '<span class="status-bad">NO ✗</span>';

                    addLog('Arrows visible: ' + arrowsVisible, arrowsVisible ? 'success' : 'error');
                    addLog('Prev arrow display: ' + prevArrow.css('display'), 'info');
                    addLog('Next arrow display: ' + nextArrow.css('display'), 'info');

                    // Monitor slider events
                    $slider.on('beforeChange', function(event, slick, currentSlide, nextSlide) {
                        addLog('Slide changing: ' + currentSlide + ' → ' + nextSlide, 'warn');
                        document.getElementById('current-slide').textContent = nextSlide + 1;
                    });

                    $slider.on('afterChange', function(event, slick, currentSlide) {
                        addLog('Slide changed to: ' + (currentSlide + 1), 'success');
                    });

                    // Monitor arrow clicks
                    prevArrow.on('touchstart click', function(e) {
                        addLog('PREV arrow ' + e.type + ' detected!', 'warn');
                    });

                    nextArrow.on('touchstart click', function(e) {
                        addLog('NEXT arrow ' + e.type + ' detected!', 'warn');
                    });

                    // Get current slide
                    try {
                        var slickInstance = $slider.slick('getSlick');
                        document.getElementById('current-slide').textContent = (slickInstance.currentSlide + 1) + ' / ' + slickInstance.slideCount;
                        document.getElementById('autoplay-status').innerHTML = slickInstance.options.autoplay ?
                            '<span class="status-good">ENABLED ✓</span>' :
                            '<span class="status-warn">DISABLED</span>';

                        addLog('Current slide: ' + (slickInstance.currentSlide + 1) + ' of ' + slickInstance.slideCount, 'info');
                        addLog('Autoplay: ' + (slickInstance.options.autoplay ? 'enabled' : 'disabled'), 'info');
                    } catch(e) {
                        addLog('Error getting slider info: ' + e.message, 'error');
                    }
                }
            }, 2000);
        }

        // Test functions
        window.testPrevArrow = function() {
            addLog('Testing PREV arrow...', 'warn');
            try {
                jQuery('.grs-direct-slider').slick('slickPrev');
                addLog('PREV command sent successfully', 'success');
            } catch(e) {
                addLog('PREV failed: ' + e.message, 'error');
            }
        };

        window.testNextArrow = function() {
            addLog('Testing NEXT arrow...', 'warn');
            try {
                jQuery('.grs-direct-slider').slick('slickNext');
                addLog('NEXT command sent successfully', 'success');
            } catch(e) {
                addLog('NEXT failed: ' + e.message, 'error');
            }
        };

        window.testAutoplay = function() {
            addLog('Starting autoplay...', 'warn');
            try {
                jQuery('.grs-direct-slider').slick('slickPlay');
                addLog('Autoplay started successfully', 'success');
                document.getElementById('autoplay-status').innerHTML = '<span class="status-good">RUNNING ✓</span>';
            } catch(e) {
                addLog('Autoplay failed: ' + e.message, 'error');
            }
        };

        window.testRefresh = function() {
            addLog('Refreshing slider...', 'warn');
            try {
                jQuery('.grs-direct-slider').slick('refresh');
                addLog('Slider refreshed successfully', 'success');
                setTimeout(checkSliders, 500);
            } catch(e) {
                addLog('Refresh failed: ' + e.message, 'error');
            }
        };

        // Initialize
        detectDevice();
        checkLibraries();

        // Update window size on resize
        window.addEventListener('resize', function() {
            document.getElementById('window-size').textContent = window.innerWidth + 'x' + window.innerHeight;
        });

        addLog('=== DIAGNOSTIC PAGE LOADED ===', 'success');
        addLog('Waiting for slider initialization...', 'info');

    })();
    </script>
</body>
</html>
