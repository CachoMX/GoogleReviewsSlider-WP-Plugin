// Fixed script.js for Read More/Show Less toggle

jQuery(document).ready(function($) {
    console.log("Google Reviews Slider: Script initialized");
    
    // Hide slider container immediately
    $('.grs-direct-slider').css({
        'opacity': '0',
        'visibility': 'hidden',
        'display': 'none'
    });

    // Pre-load Slick resources
    function initSlider() {
        const $slider = $('.grs-direct-slider');
        
        // Initialize Slick
        $slider.slick({
            dots: true,
            infinite: false,
            speed: 300,
            slidesToShow: 3,
            slidesToScroll: 1,
            adaptiveHeight: true,
            arrows: false,
            autoplay: true,           // Enable autoplay
            autoplaySpeed: 3000,      // Set to 4 seconds
            pauseOnHover: true,       // Pause on hover
            pauseOnFocus: true,       // Pause on focus
            responsive: [
                {
                    breakpoint: 1024,
                    settings: {
                        slidesToShow: 2,
                        slidesToScroll: 1
                    }
                },
                {
                    breakpoint: 600,
                    settings: {
                        slidesToShow: 1,
                        slidesToScroll: 1
                    }
                }
            ]
        });

        // Show slider only after initialization is complete
        $slider.css('display', 'block').delay(100).queue(function(next) {
            $(this).css({
                'opacity': '1',
                'visibility': 'visible'
            });
            next();
        });
    }

    // Load Slick CSS first
    $('<link>')
        .appendTo('head')
        .attr({
            type: 'text/css', 
            rel: 'stylesheet',
            href: '/wp-content/plugins/google-reviews-slider/assets/slick/slick.css'
        })
        .on('load', function() {
            // Initialize after CSS is loaded
            initSlider();
        });

    // Backup initialization on window load
    $(window).on("load", function() {
        if (!$(".grs-direct-slider").hasClass("slick-initialized")) {
            initSlider();
        }
    });
    
    // Remove any existing click handlers first
    $(document).off("click", ".grs-direct-read-more, .grs-direct-hide");

    // Initialize each review independently
    $('.grs-direct-review').each(function(index) {
        var $review = $(this);
        var $text = $review.find('.grs-direct-text');
        $text.addClass('truncated')
            .css('max-height', '100px')
            .attr('data-review-id', 'review-' + index);
        $review.find('.grs-direct-hide').hide();
    });
    
    // Handle Read More click
    $(document).on('click', '.grs-direct-read-more', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        var $review = $(this).closest('.grs-direct-review');
        var $text = $review.find('.grs-direct-text');
        var $readMore = $(this);
        var $showLess = $review.find('.grs-direct-hide');

        // Store original height
        var originalHeight = $review.height();

        // Expand only this review
        $text.removeClass('truncated')
            .css('max-height', 'none');
        $readMore.hide();
        $showLess.show();

        // Calculate new height
        var expandedHeight = $review.height();
        
        // Animate just this review
        $review
            .height(originalHeight)
            .animate({ height: expandedHeight }, 300, function() {
                $review.css('height', 'auto');
                // Refresh slider without affecting other slides
                $('.grs-direct-slider').slick('setPosition');
            });
    });

    // Handle Show Less click
    $(document).on('click', '.grs-direct-hide', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        var $review = $(this).closest('.grs-direct-review');
        var $text = $review.find('.grs-direct-text');
        var $showLess = $(this);
        var $readMore = $review.find('.grs-direct-read-more');

        // Store expanded height
        var expandedHeight = $review.height();
        
        // Add truncated class
        $text.addClass('truncated')
            .css('max-height', '100px');
        $showLess.hide();
        $readMore.show();

        // Calculate collapsed height
        var collapsedHeight = $review.height();
        
        // Animate just this review
        $review
            .height(expandedHeight)
            .animate({ height: collapsedHeight }, 300, function() {
                $review.css('height', 'auto');
                // Refresh slider without affecting other slides
                $('.grs-direct-slider').slick('setPosition');
            });
    });
});