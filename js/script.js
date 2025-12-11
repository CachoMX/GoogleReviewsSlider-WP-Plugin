// Updated js/script.js - Mobile Display Fix
jQuery(document).ready(function($) {
    console.log("Google Reviews Slider: Initializing...");
    
    // Check if mobile
    var isMobile = window.innerWidth <= 768;
    
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
            
            console.log('Initializing slider with settings:', {
                slidesToShow: isMobile ? slidesMobile : slidesDesktop,
                autoplay: autoplay,
                autoplaySpeed: autoplaySpeed,
                arrows: arrows,
                isMobile: isMobile
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
                            arrows: false, // No arrows on mobile - cleaner look
                            dots: false, // No dots on mobile - too many slides
                            infinite: true,
                            autoplay: true, // Auto-rotate on mobile
                            autoplaySpeed: 5000, // 5 seconds per review
                            centerMode: false,
                            centerPadding: '0px',
                            swipe: true,
                            touchMove: true,
                            pauseOnHover: true,
                            pauseOnFocus: true
                        }
                    }
                ]
            });

            // Ensure autoplay starts without interaction
            if (autoplay) {
                $slider.slick('slickPlay');
                $slider.on('afterChange', function() {
                    $slider.slick('slickPlay');
                });
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
                }, 500);
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