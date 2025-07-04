// Google Reviews Slider - Fixed Read More Functionality
// Replace your js/script.js file with this content

jQuery(document).ready(function($) {
    console.log("Google Reviews Slider: Starting with fixed read more...");
    
    // Initialize slider
    function initSlider() {
        const $slider = $('.grs-direct-slider');
        
        if ($slider.length === 0) {
            console.log('No slider found');
            return;
        }
        
        if ($slider.hasClass('slick-initialized')) {
            console.log('Slider already initialized');
            return;
        }
        
        const reviewCount = $slider.find('.grs-direct-review').length;
        console.log('Initializing slider with', reviewCount, 'reviews');
        
        try {
            $slider.slick({
                slidesToShow: 3,
                slidesToScroll: 1,
                infinite: reviewCount > 3,
                dots: reviewCount > 3,
                arrows: reviewCount > 3,
                autoplay: reviewCount > 3,
                autoplaySpeed: 4000,
                speed: 500,
                pauseOnHover: true,
                adaptiveHeight: false,
                variableWidth: false,
                centerMode: false,
                prevArrow: '<button class="slick-prev">‹</button>',
                nextArrow: '<button class="slick-next">›</button>',
                responsive: [
                    {
                        breakpoint: 1024,
                        settings: {
                            slidesToShow: 2,
                            slidesToScroll: 1,
                            infinite: reviewCount > 2,
                            dots: reviewCount > 2,
                            arrows: reviewCount > 2
                        }
                    },
                    {
                        breakpoint: 768,
                        settings: {
                            slidesToShow: 1,
                            slidesToScroll: 1,
                            infinite: reviewCount > 1,
                            dots: reviewCount > 1,
                            arrows: reviewCount > 1
                        }
                    }
                ]
            });
            
            $slider.on('init reInit afterChange', function(event, slick) {
                $slider.css({
                    'opacity': '1',
                    'visibility': 'visible',
                    'display': 'block'
                });
            });
            
            console.log('Slider initialized successfully');
            
        } catch (error) {
            console.error('Slider initialization failed:', error);
        }
    }
    
    // Initialize read more functionality
    function initReadMore() {
        $('.grs-direct-review').each(function() {
            const $review = $(this);
            const $text = $review.find('.grs-direct-text');
            const $readMore = $review.find('.grs-direct-read-more');
            const $showLess = $review.find('.grs-direct-hide');
            const text = $text.text().trim();
            
            // Check if text needs truncation
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
    
    // Read more click handler - expand only the clicked review
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
        if ($('.grs-direct-slider').hasClass('slick-initialized')) {
            $('.grs-direct-slider').slick('setPosition');
        }
    });
    
    // Show less click handler - collapse only the clicked review
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
        if ($('.grs-direct-slider').hasClass('slick-initialized')) {
            $('.grs-direct-slider').slick('setPosition');
        }
    });
    
    // Main initialization
    function waitAndInit() {
        let attempts = 0;
        
        function check() {
            attempts++;
            
            if (typeof $.fn.slick !== 'undefined') {
                console.log('Slick library loaded');
                initReadMore();
                initSlider();
            } else if (attempts < 50) {
                setTimeout(check, 200);
            } else {
                console.error('Slick library failed to load');
            }
        }
        
        check();
    }
    
    // Start initialization
    waitAndInit();
    
    // Backup on window load
    $(window).on('load', function() {
        setTimeout(function() {
            if ($('.grs-direct-slider').length && !$('.grs-direct-slider').hasClass('slick-initialized')) {
                console.log('Backup initialization');
                waitAndInit();
            }
        }, 1000);
    });
    
    // Resize handler
    $(window).on('resize', function() {
        if ($('.grs-direct-slider').hasClass('slick-initialized')) {
            $('.grs-direct-slider').slick('setPosition');
        }
    });
});

