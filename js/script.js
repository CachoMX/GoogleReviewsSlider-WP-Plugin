// Google Reviews Slider - Mobile Fixed JavaScript
jQuery(document).ready(function($) {
    console.log("Google Reviews Slider: Initializing with mobile fixes...");
    
    // Check if we're on mobile
    const isMobile = window.innerWidth <= 768;
    
    // Initialize slider
    function initSlider() {
        const $sliders = $('.grs-direct-slider');
        
        if ($sliders.length === 0) {
            console.log('No sliders found');
            return;
        }
        
        $sliders.each(function() {
            const $slider = $(this);
            
            if ($slider.hasClass('slick-initialized')) {
                console.log('Slider already initialized');
                return;
            }
            
            const reviewCount = $slider.find('.grs-direct-review').length;
            console.log('Initializing slider with', reviewCount, 'reviews');
            
            // Get data attributes
            const autoplay = $slider.data('autoplay') === 'true';
            const autoplaySpeed = parseInt($slider.data('autoplay-speed')) || 4000;
            const slidesDesktop = parseInt($slider.data('slides-desktop')) || 3;
            const slidesTablet = parseInt($slider.data('slides-tablet')) || 2;
            const slidesMobile = parseInt($slider.data('slides-mobile')) || 1;
            
            try {
                $slider.slick({
                    slidesToShow: slidesDesktop,
                    slidesToScroll: 1,
                    infinite: reviewCount > slidesDesktop,
                    dots: true,
                    arrows: reviewCount > 1,
                    autoplay: autoplay && reviewCount > slidesDesktop,
                    autoplaySpeed: autoplaySpeed,
                    speed: 500,
                    pauseOnHover: true,
                    adaptiveHeight: false,
                    variableWidth: false,
                    centerMode: false,
                    mobileFirst: false,
                    prevArrow: '<button type="button" class="slick-prev" aria-label="Previous">‹</button>',
                    nextArrow: '<button type="button" class="slick-next" aria-label="Next">›</button>',
                    responsive: [
                        {
                            breakpoint: 1024,
                            settings: {
                                slidesToShow: slidesTablet,
                                slidesToScroll: 1,
                                infinite: reviewCount > slidesTablet,
                                dots: true,
                                arrows: reviewCount > slidesTablet
                            }
                        },
                        {
                            breakpoint: 768,
                            settings: {
                                slidesToShow: slidesMobile,
                                slidesToScroll: 1,
                                infinite: reviewCount > slidesMobile,
                                dots: true,
                                arrows: reviewCount > slidesMobile,
                                adaptiveHeight: true
                            }
                        }
                    ]
                });
                
                // Force visibility after initialization
                $slider.css({
                    'opacity': '1',
                    'visibility': 'visible'
                });
                
                // Force refresh on mobile
                if (isMobile) {
                    setTimeout(function() {
                        $slider.slick('setPosition');
                        $slider.find('.slick-slide').css({
                            'opacity': '1',
                            'visibility': 'visible'
                        });
                    }, 100);
                }
                
                console.log('Slider initialized successfully');
                
            } catch (error) {
                console.error('Slider initialization failed:', error);
            }
        });
    }
    
    // Initialize read more functionality
    function initReadMore() {
        $('.grs-direct-review').each(function() {
            const $review = $(this);
            const $text = $review.find('.grs-direct-text');
            const $readMore = $review.find('.grs-direct-read-more');
            const $showLess = $review.find('.grs-direct-hide');
            const text = $text.text().trim();
            
            if (text.length > 150) {
                $text.addClass('truncated');
                $readMore.show();
                $showLess.hide();
            } else {
                $readMore.hide();
                $showLess.hide();
            }
        });
    }
    
    // Read more click handler
    $(document).on('click', '.grs-direct-read-more', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const $this = $(this);
        const $review = $this.closest('.grs-direct-review');
        const $text = $review.find('.grs-direct-text');
        const $showLess = $review.find('.grs-direct-hide');
        
        // Expand only this review
        $text.removeClass('truncated');
        $this.hide();
        $showLess.show();
        
        // Update slider position
        const $slider = $this.closest('.grs-direct-slider');
        if ($slider.hasClass('slick-initialized')) {
            $slider.slick('setPosition');
        }
    });
    
    // Show less click handler
    $(document).on('click', '.grs-direct-hide', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const $this = $(this);
        const $review = $this.closest('.grs-direct-review');
        const $text = $review.find('.grs-direct-text');
        const $readMore = $review.find('.grs-direct-read-more');
        
        // Collapse only this review
        $text.addClass('truncated');
        $this.hide();
        $readMore.show();
        
        // Update slider position
        const $slider = $this.closest('.grs-direct-slider');
        if ($slider.hasClass('slick-initialized')) {
            $slider.slick('setPosition');
        }
    });
    
    // Main initialization
    function init() {
        console.log('Starting initialization...');
        
        // Initialize read more functionality first
        initReadMore();
        
        // Check if Slick is loaded
        if (typeof $.fn.slick !== 'undefined') {
            console.log('Slick is loaded, initializing slider...');
            initSlider();
        } else {
            console.log('Waiting for Slick to load...');
            // Wait for Slick to load
            let attempts = 0;
            const checkInterval = setInterval(function() {
                attempts++;
                if (typeof $.fn.slick !== 'undefined') {
                    console.log('Slick loaded after', attempts, 'attempts');
                    clearInterval(checkInterval);
                    initSlider();
                } else if (attempts > 50) {
                    console.error('Slick failed to load after 50 attempts');
                    clearInterval(checkInterval);
                }
            }, 100);
        }
    }
    
    // Start initialization
    init();
    
    // Mobile-specific initialization
    if (isMobile) {
        // Force re-initialization after DOM is fully loaded
        $(window).on('load', function() {
            setTimeout(function() {
                $('.grs-direct-slider').each(function() {
                    const $slider = $(this);
                    if ($slider.hasClass('slick-initialized')) {
                        $slider.slick('setPosition');
                    } else {
                        console.log('Re-initializing slider on mobile...');
                        init();
                    }
                });
            }, 500);
        });
        
        // Handle orientation change
        $(window).on('orientationchange', function() {
            setTimeout(function() {
                $('.grs-direct-slider').each(function() {
                    if ($(this).hasClass('slick-initialized')) {
                        $(this).slick('setPosition');
                    }
                });
            }, 300);
        });
    }
    
    // Resize handler
    let resizeTimer;
    $(window).on('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            $('.grs-direct-slider').each(function() {
                if ($(this).hasClass('slick-initialized')) {
                    $(this).slick('setPosition');
                }
            });
        }, 250);
    });
    
    // Force visibility fix
    function forceVisibility() {
        $('.grs-direct-slider, .grs-direct-review').css({
            'visibility': 'visible',
            'opacity': '1'
        });
    }
    
    // Apply visibility fix
    forceVisibility();
    setTimeout(forceVisibility, 1000);
    
    // Debug information
    if (window.grsData && window.grsData.isDebug) {
        console.log('Google Reviews Slider Debug:', {
            'Mobile': isMobile,
            'Window Width': window.innerWidth,
            'Sliders Found': $('.grs-direct-slider').length,
            'Reviews Found': $('.grs-direct-review').length,
            'jQuery Version': $.fn.jquery,
            'Slick Loaded': typeof $.fn.slick !== 'undefined'
        });
    }
});