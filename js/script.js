// Updated js/script.js - Mobile Display Fix
jQuery(document).ready(function($) {
    console.log("Google Reviews Slider: Initializing...");

    // Better mobile detection including touch devices
    var isMobile = window.innerWidth <= 768;
    var isTouchDevice = ('ontouchstart' in window) || (navigator.maxTouchPoints > 0) || (navigator.msMaxTouchPoints > 0);
    var isRealMobile = isMobile && isTouchDevice;

    console.log('Device detection:', {
        windowWidth: window.innerWidth,
        isMobile: isMobile,
        isTouchDevice: isTouchDevice,
        isRealMobile: isRealMobile
    });

    // Initialize all sliders
    function initializeSliders() {
        $('.grs-direct-slider').each(function() {
            var $slider = $(this);

            // Skip if already initialized
            if ($slider.hasClass('slick-initialized')) {
                console.log('Slider already initialized, refreshing...');
                $slider.slick('refresh');
                return;
            }

            // Get settings
            var autoplay = $slider.data('autoplay') !== 'false';
            var autoplaySpeed = parseInt($slider.data('autoplay-speed')) || 4000;
            var slidesDesktop = parseInt($slider.data('slides-desktop')) || 3;
            var slidesTablet = parseInt($slider.data('slides-tablet')) || 2;
            var slidesMobile = parseInt($slider.data('slides-mobile')) || 1;
            var arrows = $slider.data('arrows') !== 'false';

            // Force autoplay on real mobile devices
            if (isRealMobile) {
                autoplay = true;
                autoplaySpeed = 5000;
            }

            console.log('Initializing slider with settings:', {
                slidesToShow: isMobile ? slidesMobile : slidesDesktop,
                autoplay: autoplay,
                autoplaySpeed: autoplaySpeed,
                arrows: arrows,
                isMobile: isMobile,
                isRealMobile: isRealMobile
            });

            // Initialize Slick with horizontal settings
            $slider.slick({
                slidesToShow: isMobile ? slidesMobile : slidesDesktop,
                slidesToScroll: isMobile ? slidesMobile : slidesDesktop, // Scroll same amount as shown
                infinite: true,
                dots: true,
                arrows: arrows,
                autoplay: autoplay,
                autoplaySpeed: autoplaySpeed,
                pauseOnHover: true,
                pauseOnFocus: true,
                speed: 500,
                cssEase: 'ease',
                adaptiveHeight: false,
                variableWidth: false,
                vertical: false, // CRITICAL: Ensure horizontal mode
                verticalSwiping: false,
                rtl: false,
                centerMode: false,
                focusOnSelect: false,
                swipe: true,
                touchMove: true,
                touchThreshold: 5,
                dotsClass: 'slick-dots grs-slider-dots', // Custom class for better control
                prevArrow: '<button type="button" class="slick-prev" aria-label="Previous"><span>Previous</span></button>',
                nextArrow: '<button type="button" class="slick-next" aria-label="Next"><span>Next</span></button>',
                responsive: [
                    {
                        breakpoint: 1024,
                        settings: {
                            slidesToShow: slidesTablet,
                            slidesToScroll: slidesTablet, // Match scroll to slides shown
                            arrows: true,
                            dots: true
                        }
                    },
                    {
                        breakpoint: 768,
                        settings: {
                            slidesToShow: 1,
                            slidesToScroll: 1,
                            arrows: true, // Show arrows on mobile for better navigation
                            dots: false, // No dots on mobile - too many slides
                            infinite: true,
                            autoplay: true, // Auto-rotate on mobile
                            autoplaySpeed: 5000, // 5 seconds per review
                            centerMode: false,
                            centerPadding: '0px',
                            variableWidth: false, // Fixed width slides
                            adaptiveHeight: false, // Prevent height jumping
                            swipe: true,
                            touchMove: true,
                            pauseOnHover: true,
                            pauseOnFocus: true
                        }
                    }
                ]
            });

            // Force autoplay to start - especially important on mobile devices
            // iOS Safari requires special handling for autoplay
            if (autoplay || isRealMobile) {
                console.log('Setting up autoplay for mobile...');

                // For iOS/Mobile: Start autoplay after slider is fully initialized
                setTimeout(function() {
                    try {
                        $slider.slick('slickPlay');
                        console.log('Mobile autoplay started');
                    } catch(e) {
                        console.error('Error starting autoplay:', e);
                    }
                }, 500);

                // Ensure autoplay continues after any interaction
                $slider.on('afterChange', function(event, slick, currentSlide) {
                    console.log('Slide changed to:', currentSlide);
                    // Re-enable autoplay after each change
                    setTimeout(function() {
                        try {
                            $slider.slick('slickPlay');
                        } catch(e) {
                            console.error('Error restarting autoplay:', e);
                        }
                    }, 100);
                });

                // iOS-specific: Start autoplay on first touch/scroll
                if (isRealMobile) {
                    var iosAutoplayStarted = false;

                    // Listen for any user interaction to start autoplay
                    $slider.one('touchstart click', function() {
                        if (!iosAutoplayStarted) {
                            console.log('iOS: User interaction detected, starting autoplay...');
                            setTimeout(function() {
                                try {
                                    $slider.slick('slickPlay');
                                    iosAutoplayStarted = true;
                                } catch(e) {
                                    console.error('Error starting iOS autoplay:', e);
                                }
                            }, 300);
                        }
                    });

                    // Also restart autoplay after swipe/drag
                    $slider.on('swipe afterChange', function() {
                        setTimeout(function() {
                            try {
                                $slider.slick('slickPlay');
                            } catch(e) {
                                console.error('Error restarting after swipe:', e);
                            }
                        }, 1000); // Wait 1 second after swipe
                    });
                }
            }

            // Ensure container width is correct on mobile
            if (isMobile) {
                setTimeout(function() {
                    var $container = $slider.closest('.grs-direct-slider-container');
                    var $list = $slider.find('.slick-list');

                    // Ensure list and container use full width
                    $container.css({
                        'width': '100%',
                        'max-width': '100%'
                    });

                    $list.css({
                        'width': '100%',
                        'max-width': '100%'
                    });

                    // Refresh Slick positioning
                    $slider.slick('setPosition');

                    // Start autoplay after positioning is set
                    if (autoplay || isRealMobile) {
                        $slider.slick('slickPlay');
                        console.log('Autoplay restarted after setPosition');
                    }
                }, 100);
            }
            
            console.log('Slider initialized successfully');

            // Ensure visibility immediately after initialization
            $slider.css({
                'opacity': '1',
                'visibility': 'visible',
                'display': 'block'
            });

            // Force all reviews to be visible
            $slider.find('.grs-direct-review').css({
                'opacity': '1',
                'visibility': 'visible',
                'display': 'flex'
            });

            // Ensure text is visible
            $slider.find('.grs-direct-text').css({
                'opacity': '1',
                'visibility': 'visible',
                'display': 'block',
                'color': '#333'
            });

            // Force position update
            setTimeout(function() {
                $slider.slick('setPosition');

                // Additional mobile-specific fixes
                if (isMobile) {
                    // Ensure proper height on mobile
                    $slider.find('.slick-slide').css({
                        'height': 'auto',
                        'min-height': '280px'
                    });

                    // Force track to display properly
                    $slider.find('.slick-track').css({
                        'display': 'flex',
                        'align-items': 'stretch'
                    });

                    console.log('Applied mobile-specific fixes');
                }
            }, 100);

            // Additional position update for stubborn mobile browsers
            if (isMobile) {
                setTimeout(function() {
                    $slider.slick('setPosition');

                    // CRITICAL FIX: Force float:left on slides if CSS didn't load properly
                    $slider.find('.slick-slide').css({
                        'float': 'left',
                        'display': 'block'
                    });
                    $slider.find('.slick-track').css({
                        'display': 'block',
                        'position': 'relative'
                    });

                    console.log('FORCED mobile CSS via JavaScript');
                    $slider.slick('setPosition'); // Refresh after CSS changes
                }, 500);
            }

            // iOS-specific: Fix for arrow button clicks not working
            // CRITICAL: This must be inside the .each() loop to have access to $slider
            if (isRealMobile || isTouchDevice) {
                setTimeout(function() {
                    // Find arrow buttons (created by Slick)
                    var $prevArrow = $slider.find('.slick-prev');
                    var $nextArrow = $slider.find('.slick-next');

                    console.log('Found arrows for this slider:', {
                        prev: $prevArrow.length,
                        next: $nextArrow.length
                    });

                    if ($prevArrow.length > 0 && $nextArrow.length > 0) {
                        // Remove any existing handlers first
                        $prevArrow.off('touchend touchstart click');
                        $nextArrow.off('touchend touchstart click');

                        // Add touch event handlers for iOS - using touchstart for better responsiveness
                        $prevArrow.on('touchstart', function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            console.log('iOS: Previous arrow touched (touchstart)');
                            $slider.slick('slickPrev');
                            return false;
                        });

                        $nextArrow.on('touchstart', function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            console.log('iOS: Next arrow touched (touchstart)');
                            $slider.slick('slickNext');
                            return false;
                        });

                        // Also add click handlers as fallback
                        $prevArrow.on('click', function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            console.log('Previous arrow clicked');
                            $slider.slick('slickPrev');
                            return false;
                        });

                        $nextArrow.on('click', function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            console.log('Next arrow clicked');
                            $slider.slick('slickNext');
                            return false;
                        });

                        console.log('iOS/Mobile arrow touch handlers attached successfully');
                    } else {
                        console.error('Arrows not found! Cannot attach handlers');
                    }
                }, 200);
            }
        });
    }

    // Read more/less functionality
    $(document).on('click', '.grs-direct-read-more', function(e) {
        e.preventDefault();
        var $this = $(this);
        var $review = $this.closest('.grs-direct-review');
        var $text = $review.find('.grs-direct-text');
        var $showLess = $review.find('.grs-direct-hide');
        
        $text.removeClass('truncated');
        $this.hide();
        $showLess.show();
        
        // Update slider position after text expansion
        var $slider = $this.closest('.grs-direct-slider');
        if ($slider.hasClass('slick-initialized')) {
            setTimeout(function() {
                $slider.slick('setPosition');
            }, 50);
        }
    });
    
    $(document).on('click', '.grs-direct-hide', function(e) {
        e.preventDefault();
        var $this = $(this);
        var $review = $this.closest('.grs-direct-review');
        var $text = $review.find('.grs-direct-text');
        var $readMore = $review.find('.grs-direct-read-more');
        
        $text.addClass('truncated');
        $this.hide();
        $readMore.show();
        
        // Update slider position after text collapse
        var $slider = $this.closest('.grs-direct-slider');
        if ($slider.hasClass('slick-initialized')) {
            setTimeout(function() {
                $slider.slick('setPosition');
            }, 50);
        }
    });
    
    // Mobile visibility enforcement
    function enforceMobileVisibility() {
        if (isMobile) {
            $('.grs-direct-wrapper').css({
                'opacity': '1',
                'visibility': 'visible',
                'display': 'block'
            });
            
            $('.grs-direct-slider').css({
                'opacity': '1',
                'visibility': 'visible',
                'display': 'block',
                'min-height': '320px'
            });
            
            $('.grs-direct-review').css({
                'opacity': '1',
                'visibility': 'visible',
                'display': 'flex',
                'flex-direction': 'column'
            });
            
            $('.grs-direct-text').css({
                'opacity': '1',
                'visibility': 'visible',
                'display': 'block',
                'color': '#333'
            });
            
            console.log('Enforced mobile visibility');
        }
    }
    
    // Check if Slick is loaded
    if (typeof $.fn.slick === 'undefined') {
        console.error('Slick is not loaded, waiting...');

        // Wait for Slick to load
        var checkSlick = setInterval(function() {
            if (typeof $.fn.slick !== 'undefined') {
                clearInterval(checkSlick);
                console.log('Slick loaded, initializing sliders...');
                initializeSliders();
                enforceMobileVisibility();
            }
        }, 100);
    } else {
        // Initialize immediately
        initializeSliders();
        enforceMobileVisibility();
    }
    
    // Also initialize on window load
    $(window).on('load', function() {
        console.log('Window loaded, checking sliders...');
        setTimeout(function() {
            initializeSliders();
            enforceMobileVisibility();
            
            // Force refresh on mobile after load
            if (isMobile) {
                $('.grs-direct-slider.slick-initialized').slick('refresh');
            }
        }, 100);
    });
    
    // Handle window resize
    var resizeTimer;
    $(window).on('resize orientationchange', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            var wasDesktop = !isMobile;
            isMobile = window.innerWidth <= 768;
            
            if (wasDesktop !== !isMobile) {
                console.log('Device type changed, refreshing sliders...');
                $('.grs-direct-slider.slick-initialized').slick('refresh');
                enforceMobileVisibility();
            } else {
                $('.grs-direct-slider.slick-initialized').slick('setPosition');
            }
        }, 250);
    });
    
    // Mobile-specific initialization
    if (isMobile) {
        console.log('Mobile device detected, applying additional fixes...');
        
        // Ensure reviews are visible after a delay
        setTimeout(function() {
            enforceMobileVisibility();
            $('.grs-direct-slider.slick-initialized').slick('refresh');
        }, 500);
        
        // Additional check after 1 second
        setTimeout(function() {
            enforceMobileVisibility();
        }, 1000);
    }
    
    // Debug: Log slider state
    setTimeout(function() {
        var sliderCount = $('.grs-direct-slider.slick-initialized').length;
        var visibleReviews = $('.grs-direct-review:visible').length;
        console.log('GRS Debug - Initialized sliders:', sliderCount);
        console.log('GRS Debug - Visible reviews:', visibleReviews);
        
        if (isMobile && visibleReviews === 0) {
            console.warn('No visible reviews on mobile, forcing visibility...');
            enforceMobileVisibility();
        }
    }, 2000);
});