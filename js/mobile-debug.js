/**
 * Mobile Debug Script for Google Reviews Slider
 *
 * This script adds extensive debugging for mobile devices
 * to help identify why the slider isn't working
 *
 * Include BEFORE script.js on mobile troubleshooting
 */

(function($) {
    'use strict';

    // Create debug console
    var debugMessages = [];
    var debugElement = null;

    function initDebugConsole() {
        // Create floating debug console
        debugElement = $('<div>')
            .attr('id', 'grs-mobile-debug')
            .css({
                position: 'fixed',
                bottom: '0',
                left: '0',
                right: '0',
                background: 'rgba(0, 0, 0, 0.9)',
                color: '#0f0',
                font-family: 'monospace',
                font-size: '10px',
                padding: '10px',
                max-height: '200px',
                overflow: 'auto',
                z-index: '99999',
                border-top: '2px solid #0f0'
            })
            .appendTo('body');

        // Add toggle button
        var toggleBtn = $('<button>')
            .text('Debug')
            .css({
                position: 'fixed',
                bottom: '210px',
                right: '10px',
                padding: '10px',
                background: '#0f0',
                color: '#000',
                border: 'none',
                'border-radius': '5px',
                'z-index': '100000',
                'font-weight': 'bold'
            })
            .on('click', function() {
                debugElement.toggle();
            })
            .appendTo('body');

        log('GRS Mobile Debug Console Initialized');
    }

    function log(message, type) {
        type = type || 'info';
        var timestamp = new Date().toLocaleTimeString();
        var color = type === 'error' ? '#f00' : type === 'warn' ? '#ff0' : '#0f0';

        debugMessages.push('[' + timestamp + '] ' + message);

        if (debugElement) {
            var logLine = $('<div>')
                .css('color', color)
                .text('[' + timestamp + '] ' + message);
            debugElement.append(logLine);

            // Auto-scroll to bottom
            debugElement.scrollTop(debugElement[0].scrollHeight);

            // Keep only last 50 messages
            if (debugElement.children().length > 50) {
                debugElement.children().first().remove();
            }
        }

        // Also log to console
        console.log('[GRS Debug] ' + message);
    }

    // Device detection
    function detectDevice() {
        var ua = navigator.userAgent;
        var info = {
            userAgent: ua,
            isIOS: /iPad|iPhone|iPod/.test(ua) && !window.MSStream,
            isAndroid: /Android/.test(ua),
            isMobile: /Mobi|Android/i.test(ua),
            isTablet: /iPad|Android/i.test(ua) && !/Mobile/i.test(ua),
            screenWidth: window.screen.width,
            screenHeight: window.screen.height,
            windowWidth: $(window).width(),
            windowHeight: $(window).height(),
            touchSupport: 'ontouchstart' in window,
            pixelRatio: window.devicePixelRatio || 1
        };

        log('=== DEVICE INFO ===');
        log('iOS: ' + info.isIOS);
        log('Android: ' + info.isAndroid);
        log('Mobile: ' + info.isMobile);
        log('Touch: ' + info.touchSupport);
        log('Screen: ' + info.screenWidth + 'x' + info.screenHeight);
        log('Window: ' + info.windowWidth + 'x' + info.windowHeight);
        log('==================');

        return info;
    }

    // Initialize on document ready
    $(document).ready(function() {
        initDebugConsole();
        var deviceInfo = detectDevice();

        // Monitor slider initialization
        log('Waiting for sliders...');

        setTimeout(function() {
            var sliders = $('.grs-direct-slider');
            log('Found ' + sliders.length + ' slider(s)');

            sliders.each(function(index) {
                var $slider = $(this);
                log('Slider #' + index + ' classes: ' + $slider.attr('class'));
                log('Slider #' + index + ' has slick: ' + $slider.hasClass('slick-initialized'));

                if ($slider.hasClass('slick-initialized')) {
                    // Check slick settings
                    try {
                        var slickInstance = $slider.slick('getSlick');
                        log('Slider #' + index + ' settings: ' + JSON.stringify(slickInstance.options));
                        log('Slider #' + index + ' slide count: ' + slickInstance.slideCount);
                        log('Slider #' + index + ' current slide: ' + slickInstance.currentSlide);
                    } catch(e) {
                        log('Error getting slick instance: ' + e.message, 'error');
                    }

                    // Check arrows
                    var $prevArrow = $slider.find('.slick-prev');
                    var $nextArrow = $slider.find('.slick-next');
                    log('Prev arrow found: ' + $prevArrow.length);
                    log('Next arrow found: ' + $nextArrow.length);

                    if ($prevArrow.length) {
                        log('Prev arrow visible: ' + $prevArrow.is(':visible'));
                        log('Prev arrow CSS display: ' + $prevArrow.css('display'));
                    }

                    if ($nextArrow.length) {
                        log('Next arrow visible: ' + $nextArrow.is(':visible'));
                        log('Next arrow CSS display: ' + $nextArrow.css('display'));
                    }

                    // Test arrow clicks
                    if ($prevArrow.length) {
                        log('Adding test handler to prev arrow...');
                        $prevArrow.on('click touchend', function(e) {
                            log('PREV ARROW CLICKED! Event: ' + e.type, 'warn');
                            e.preventDefault();
                            return false;
                        });
                    }

                    if ($nextArrow.length) {
                        log('Adding test handler to next arrow...');
                        $nextArrow.on('click touchend', function(e) {
                            log('NEXT ARROW CLICKED! Event: ' + e.type, 'warn');
                            e.preventDefault();
                            return false;
                        });
                    }

                    // Check autoplay
                    $slider.on('beforeChange', function(event, slick, currentSlide, nextSlide) {
                        log('Slide changing: ' + currentSlide + ' → ' + nextSlide, 'warn');
                    });

                    $slider.on('afterChange', function(event, slick, currentSlide) {
                        log('Slide changed to: ' + currentSlide);
                    });

                } else {
                    log('Slider #' + index + ' NOT initialized!', 'error');
                }
            });

            // Check if slick is loaded
            if (typeof $.fn.slick === 'undefined') {
                log('SLICK LIBRARY NOT LOADED!', 'error');
            } else {
                log('Slick library loaded: OK');
            }

        }, 2000);

        // Monitor touch events
        $(document).on('touchstart', function(e) {
            var target = $(e.target);
            if (target.closest('.slick-arrow').length) {
                log('Touch on arrow: ' + target.attr('class'), 'warn');
            }
        });

        $(document).on('touchend', function(e) {
            var target = $(e.target);
            if (target.closest('.slick-arrow').length) {
                log('Touchend on arrow: ' + target.attr('class'), 'warn');
            }
        });

        // Export debug info
        window.GRS_DEBUG = {
            deviceInfo: deviceInfo,
            messages: debugMessages,
            getLog: function() {
                return debugMessages.join('\n');
            },
            clear: function() {
                debugMessages = [];
                debugElement.empty();
                log('Debug console cleared');
            }
        };

        log('Debug tools available: window.GRS_DEBUG');
    });

})(jQuery);
