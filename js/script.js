// Google Reviews Slider - Mobile Fixed JavaScript with Avada Compatibility
jQuery(document).ready(function($) {
    console.log("Google Reviews Slider: Initializing with Avada and mobile fixes...");
    
    // Detect mobile and Avada theme
    const isMobile = window.innerWidth <= 768;
    const isAvada = $('body').hasClass('avada-html') || $('body').hasClass('fusion-body') || $('.fusion-builder-container').length > 0;
    
    console.log('Environment:', { isMobile, isAvada, windowWidth: window.innerWidth });
    
    // Force visibility function for Avada compatibility
    function forceVisibility() {
        console.log('Forcing visibility for all review elements...');
        
        // Force all main elements to be visible
        $('.grs-direct-wrapper, .grs-direct-slider, .grs-direct-review, .grs-direct-text').css({
            'opacity': '1',
            'visibility': 'visible',
            'display': 'block',
            'position': 'relative'
        });
        
        // Force review cards to use flex
        $('.grs-direct-review').css({
            'display': 'flex',
            'flex-direction': 'column'
        });
        
        // Force header to use flex
        $('.grs-direct-header').css({
            'display': 'flex'
        });
        
        // Force stars to be visible
        $('.grs-direct-stars').css({
            'display': 'flex'
        });
        
        // Force content area
        $('.grs-direct-content').css({
            'display': 'flex',
            'flex-direction': 'column'
        });
        
        // Ensure all text is visible
        $('.grs-direct-name, .grs-direct-date, .grs-direct-text').css({
            'color': '#333',
            'opacity': '1',
            'visibility': 'visible'
        });
        
        // Fix profile images
        $('.grs-direct-profile-img img').css({
            'display': 'block',
            'opacity': '1',
            'visibility': 'visible'
        });
    }
    
    // Enhanced slider initialization with Avada compatibility
    function initSlider() {
        const $sliders = $('.grs-direct-slider');
        
        if ($sliders.length === 0) {
            console.log('No sliders found');
            return;
        }
        
        console.log('Found', $sliders.length, 'slider(s) to initialize');
        
        $sliders.each(function(index) {
            const $slider = $(this);
            const sliderId = $slider.attr('id') || 'grs-slider-' + index;
            
            console.log('Processing slider:', sliderId);
            
            // Skip if already initialized
            if ($slider.hasClass('slick-initialized')) {
                console.log('Slider already initialized:', sliderId);
                forceVisibility(); // Still force visibility
                return;
            }
            
            const $reviews = $slider.find('.grs-direct-review');
            const reviewCount = $reviews.length;
            
            console.log('Reviews found in slider:', reviewCount);
            
            if (reviewCount === 0) {
                console.warn('No reviews found in slider:', sliderId);
                return;
            }
            
            // Force initial visibility
            forceVisibility();
            
            // Get configuration from data attributes
            const autoplay = $slider.data('autoplay') !== 'false' && $slider.data('autoplay') !== false;
            const autoplaySpeed = parseInt($slider.data('autoplay-speed')) || 4000;
            const slidesDesktop = Math.min(parseInt($slider.data('slides-desktop')) || 3, reviewCount);
            const slidesTablet = Math.min(parseInt($slider.data('slides-tablet')) || 2, reviewCount);
            const slidesMobile = Math.min(parseInt($slider.data('slides-mobile')) || 1, reviewCount);
            
            console.log('Slider config:', {
                autoplay,
                autoplaySpeed,
                slidesDesktop,
                slidesTablet,
                slidesMobile,
                reviewCount
            });
            
            // Enhanced Slick configuration
            const slickConfig = {
                slidesToShow: slidesDesktop,
                slidesToScroll: 1,
                infinite: reviewCount > slidesDesktop,
                dots: reviewCount > 1,
                arrows: reviewCount > 1,
                autoplay: autoplay && reviewCount > 1,
                autoplaySpeed: autoplaySpeed,
                speed: 500,
                pauseOnHover: true,
                pauseOnFocus: true,
                adaptiveHeight: isMobile,
                variableWidth: false,
                centerMode: false,
                mobileFirst: false,
                cssEase: 'ease',
                useCSS: true,
                useTransform: !isAvada, // Disable transforms for Avada
                lazyLoad: 'ondemand',
                accessibility: true,
                focusOnSelect: false,
                prevArrow: '<button type="button" class="slick-prev" aria-label="Previous slide">‹</button>',
                nextArrow: '<button type="button" class="slick-next" aria-label="Next slide">›</button>',
                responsive: [
                    {
                        breakpoint: 1024,
                        settings: {
                            slidesToShow: slidesTablet,
                            slidesToScroll: 1,
                            infinite: reviewCount > slidesTablet,
                            dots: reviewCount > 1,
                            arrows: reviewCount > slidesTablet,
                            autoplay: autoplay && reviewCount > slidesTablet
                        }
                    },
                    {
                        breakpoint: 768,
                        settings: {
                            slidesToShow: slidesMobile,
                            slidesToScroll: 1,
                            infinite: reviewCount > slidesMobile,
                            dots: reviewCount > 1,
                            arrows: reviewCount > slidesMobile,
                            autoplay: autoplay && reviewCount > slidesMobile,
                            adaptiveHeight: true,
                            centerMode: false,
                            variableWidth: false
                        }
                    }
                ]
            };
            
            try {
                console.log('Initializing Slick with config:', slickConfig);
                
                // Initialize Slick
                $slider.slick(slickConfig);
                
                console.log('Slick initialized successfully for:', sliderId);
                
                // Post-initialization fixes
                setTimeout(function() {
                    console.log('Running post-initialization fixes...');
                    
                    // Force visibility again
                    forceVisibility();
                    
                    // Ensure slider is visible
                    $slider.css({
                        'opacity': '1',
                        'visibility': 'visible',
                        'display': 'block'
                    });
                    
                    // Force refresh
                    $slider.slick('setPosition');
                    
                    // Mobile-specific fixes
                    if (isMobile) {
                        $slider.find('.slick-slide').css({
                            'opacity': '1',
                            'visibility': 'visible',
                            'display': 'block'
                        });
                        
                        // Force review cards to be visible
                        $slider.find('.grs-direct-review').css({
                            'opacity': '1',
                            'visibility': 'visible',
                            'display': 'flex',
                            'flex-direction': 'column'
                        });
                    }
                    
                    // Avada-specific fixes
                    if (isAvada) {
                        console.log('Applying Avada-specific fixes...');
                        
                        // Force all elements to override Avada styles
                        $slider.find('*').css({
                            'opacity': '1',
                            'visibility': 'visible'
                        });
                        
                        // Additional Avada overrides
                        $slider.closest('.fusion-builder-container, .fusion-text, .fusion-content-boxes').css({
                            'overflow': 'visible'
                        });
                    }
                    
                }, 100);
                
                // Additional delayed fix for stubborn themes
                setTimeout(function() {
                    forceVisibility();
                    $slider.slick('setPosition');
                }, 500);
                
            } catch (error) {
                console.error('Slick initialization failed for:', sliderId, error);
                
                // Fallback: show all reviews without slider
                console.log('Showing fallback display...');
                $reviews.show().css({
                    'opacity': '1',
                    'visibility': 'visible',
                    'display': 'flex',
                    'flex-direction': 'column',
                    'margin-bottom': '20px'
                });
            }
        });
    }
    
    // Enhanced read more functionality
    function initReadMore() {
        console.log('Initializing read more functionality...');
        
        $('.grs-direct-review').each(function() {
            const $review = $(this);
            const $text = $review.find('.grs-direct-text');
            const $readMore = $review.find('.grs-direct-read-more');
            const $showLess = $review.find('.grs-direct-hide');
            const text = $text.text().trim();
            
            if (text.length > 150) {
                $text.addClass('truncated');
                $readMore.show().css('display', 'inline-block');
                $showLess.hide();
            } else {
                $readMore.hide();
                $showLess.hide();
            }
        });
    }
    
    // Enhanced read more click handler
    $(document).on('click', '.grs-direct-read-more', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        console.log('Read more clicked');
        
        const $this = $(this);
        const $review = $this.closest('.grs-direct-review');
        const $text = $review.find('.grs-direct-text');
        const $showLess = $review.find('.grs-direct-hide');
        
        // Expand only this specific review
        $text.removeClass('truncated').css({
            'display': 'block',
            'max-height': 'none',
            'overflow': 'visible',
            '-webkit-line-clamp': 'unset',
            '-webkit-box-orient': 'unset'
        });
        
        $this.hide();
        $showLess.show().css('display', 'inline-block');
        
        // Update slider position after content change
        const $slider = $this.closest('.grs-direct-slider');
        if ($slider.hasClass('slick-initialized')) {
            setTimeout(function() {
                $slider.slick('setPosition');
            }, 50);
        }
    });
    
    // Enhanced show less click handler
    $(document).on('click', '.grs-direct-hide', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        console.log('Show less clicked');
        
        const $this = $(this);
        const $review = $this.closest('.grs-direct-review');
        const $text = $review.find('.grs-direct-text');
        const $readMore = $review.find('.grs-direct-read-more');
        
        // Collapse only this specific review
        $text.addClass('truncated');
        $this.hide();
        $readMore.show().css('display', 'inline-block');
        
        // Update slider position after content change
        const $slider = $this.closest('.grs-direct-slider');
        if ($slider.hasClass('slick-initialized')) {
            setTimeout(function() {
                $slider.slick('setPosition');
            }, 50);
        }
    });
    
    // Enhanced initialization sequence
    function init() {
        console.log('Starting enhanced initialization sequence...');
        
        // Wait for DOM to be fully ready
        if (document.readyState !== 'complete') {
            console.log('Waiting for document to be ready...');
            $(window).on('load', function() {
                setTimeout(init, 100);
            });
            return;
        }
        
        // Check if sliders exist
        if ($('.grs-direct-slider').length === 0) {
            console.log('No sliders found, exiting...');
            return;
        }
        
        // Force initial visibility
        forceVisibility();
        
        // Initialize read more functionality
        initReadMore();
        
        // Check if Slick is available
        if (typeof $.fn.slick !== 'undefined') {
            console.log('Slick is loaded, initializing sliders...');
            initSlider();
        } else {
            console.log('Waiting for Slick to load...');
            
            // Enhanced Slick loading detection
            let attempts = 0;
            const maxAttempts = 100; // 10 seconds max wait
            const checkInterval = setInterval(function() {
                attempts++;
                
                if (typeof $.fn.slick !== 'undefined') {
                    console.log('Slick loaded after', attempts, 'attempts');
                    clearInterval(checkInterval);
                    initSlider();
                } else if (attempts >= maxAttempts) {
                    console.error('Slick failed to load after', maxAttempts, 'attempts');
                    clearInterval(checkInterval);
                    
                    // Fallback: show all reviews
                    $('.grs-direct-review').show().css({
                        'opacity': '1',
                        'visibility': 'visible',
                        'display': 'flex',
                        'margin-bottom': '20px'
                    });
                }
            }, 100);
        }
    }
    
    // Multiple initialization triggers for reliability
    init();
    
    // Mobile-specific enhancements
    if (isMobile) {
        console.log('Applying mobile-specific enhancements...');
        
        // Force initialization on mobile after full page load
        $(window).on('load', function() {
            setTimeout(function() {
                console.log('Mobile: Re-checking sliders after page load...');
                
                $('.grs-direct-slider').each(function() {
                    const $slider = $(this);
                    
                    if ($slider.hasClass('slick-initialized')) {
                        console.log('Mobile: Refreshing existing slider...');
                        forceVisibility();
                        $slider.slick('setPosition');
                    } else {
                        console.log('Mobile: Re-initializing slider...');
                        initSlider();
                    }
                });
            }, 1000);
        });
        
        // Handle orientation changes
        $(window).on('orientationchange', function() {
            console.log('Mobile: Orientation changed, refreshing sliders...');
            setTimeout(function() {
                forceVisibility();
                $('.grs-direct-slider.slick-initialized').each(function() {
                    $(this).slick('setPosition');
                });
            }, 300);
        });
        
        // Handle viewport changes
        let resizeTimer;
        $(window).on('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                if (Math.abs(window.innerWidth - $(window).width()) > 50) {
                    console.log('Mobile: Significant resize detected, refreshing...');
                    forceVisibility();
                    $('.grs-direct-slider.slick-initialized').each(function() {
                        $(this).slick('setPosition');
                    });
                }
            }, 250);
        });
    }
    
    // Avada-specific enhancements
    if (isAvada) {
        console.log('Applying Avada-specific enhancements...');
        
        // Hook into Avada's live builder events
        if (window.FusionEvents) {
            window.FusionEvents.on('fusion-element-render-fusion_text', function() {
                console.log('Avada: Text element rendered, re-initializing...');
                setTimeout(init, 100);
            });
        }
        
        // Check for Avada live builder
        if ($('body').hasClass('fusion-builder-live')) {
            console.log('Avada Live Builder detected...');
            
            // Re-initialize when live builder updates
            setInterval(function() {
                if ($('.grs-direct-slider:not(.slick-initialized)').length > 0) {
                    console.log('Avada Live: Found uninitialized sliders...');
                    init();
                }
            }, 2000);
        }
        
        // Additional Avada compatibility
        $(document).on('fusion-column-resized fusion-content-changed', function() {
            console.log('Avada: Content changed, refreshing sliders...');
            setTimeout(function() {
                forceVisibility();
                $('.grs-direct-slider.slick-initialized').each(function() {
                    $(this).slick('setPosition');
                });
            }, 100);
        });
    }
    
    // Periodic visibility enforcement (for stubborn themes)
    setInterval(function() {
        if ($('.grs-direct-slider').length > 0) {
            forceVisibility();
        }
    }, 5000);
    
    // Debug information
    if (window.grsData && window.grsData.isDebug) {
        console.log('Google Reviews Slider Debug Info:', {
            'Mobile': isMobile,
            'Avada Theme': isAvada,
            'Window Width': window.innerWidth,
            'Document Ready': document.readyState,
            'Sliders Found': $('.grs-direct-slider').length,
            'Reviews Found': $('.grs-direct-review').length,
            'jQuery Version': $.fn.jquery,
            'Slick Available': typeof $.fn.slick !== 'undefined',
            'Body Classes': $('body').attr('class')
        });
    }
    
    // Final fallback for extreme cases
    setTimeout(function() {
        const $uninitialized = $('.grs-direct-slider:not(.slick-initialized)');
        if ($uninitialized.length > 0) {
            console.warn('Found uninitialized sliders after 10 seconds, forcing display...');
            
            $uninitialized.each(function() {
                const $slider = $(this);
                const $reviews = $slider.find('.grs-direct-review');
                
                // Show all reviews in a simple layout
                $reviews.css({
                    'display': 'flex',
                    'flex-direction': 'column',
                    'margin-bottom': '20px',
                    'opacity': '1',
                    'visibility': 'visible'
                });
                
                // Hide navigation elements since slider failed
                $slider.find('.slick-dots, .slick-arrows').hide();
            });
        }
    }, 10000);
    
    // Check if Slick is loaded
    console.log('Slick loaded?', typeof jQuery.fn.slick);

    // Check if slider exists
    console.log('Slider elements:', jQuery('.grs-direct-slider').length);

    // Check if already initialized
    console.log('Already initialized?', jQuery('.grs-direct-slider').hasClass('slick-initialized'));

    // Try to manually initialize
    jQuery('.grs-direct-slider').slick({
        slidesToShow: 3,
        slidesToScroll: 1,
        dots: true,
        arrows: true
    });

    console.log('Google Reviews Slider initialization complete');
});