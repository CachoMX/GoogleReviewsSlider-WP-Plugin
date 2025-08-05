jQuery(document).ready(function($) {
    console.log("Google Reviews Slider: Initializing horizontal layout...");
    setTimeout(function() {
        var sliderCount = $('.grs-direct-slider.slick-initialized').length;
        var arrowCount = $('.grs-direct-slider .slick-arrow').length;
        console.log('GRS Debug - Sliders initialized:', sliderCount);
        console.log('GRS Debug - Arrows found:', arrowCount);
        
        // Force arrow visibility if they exist but are hidden
        $('.grs-direct-slider .slick-arrow').each(function() {
            var display = $(this).css('display');
            var visibility = $(this).css('visibility');
            console.log('Arrow display:', display, 'visibility:', visibility);
            
            // Force show
            $(this).css({
                'display': 'flex',
                'opacity': '1',
                'visibility': 'visible'
            });
        });
    }, 2000);
    
    // Initialize all sliders
    function initializeSliders() {
        $('.grs-direct-slider').each(function() {
            var $slider = $(this);
            
            // Skip if already initialized
            if ($slider.hasClass('slick-initialized')) {
                return;
            }
            
            // Get settings
            var autoplay = $slider.data('autoplay') !== 'false';
            var autoplaySpeed = parseInt($slider.data('autoplay-speed')) || 4000;
            var slidesDesktop = parseInt($slider.data('slides-desktop')) || 3;
            var slidesTablet = parseInt($slider.data('slides-tablet')) || 2;
            var slidesMobile = parseInt($slider.data('slides-mobile')) || 1;
            
            console.log('Initializing with settings:', {
                slidesToShow: slidesDesktop,
                autoplay: autoplay,
                autoplaySpeed: autoplaySpeed
            });
            
            // Initialize Slick with horizontal settings
            $slider.slick({
                slidesToShow: slidesDesktop,
                slidesToScroll: 1,
                infinite: true,
                dots: true,
                arrows: true,
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
                prevArrow: '<button type="button" class="slick-prev" aria-label="Previous">Previous</button>',
                nextArrow: '<button type="button" class="slick-next" aria-label="Next">Next</button>',
                responsive: [
                    {
                        breakpoint: 1024,
                        settings: {
                            slidesToShow: slidesTablet,
                            slidesToScroll: 1
                        }
                    },
                    {
                        breakpoint: 768,
                        settings: {
                            slidesToShow: slidesMobile,
                            slidesToScroll: 1,
                            arrows: false
                        }
                    }
                ]
            });
            
            console.log('Slider initialized successfully');
            
            // Ensure visibility
            $slider.css({
                'opacity': '1',
                'visibility': 'visible'
            });
            
            // Force position update
            setTimeout(function() {
                $slider.slick('setPosition');
            }, 100);
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
        
        // Update slider
        var $slider = $this.closest('.grs-direct-slider');
        if ($slider.hasClass('slick-initialized')) {
            $slider.slick('setPosition');
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
        
        // Update slider
        var $slider = $this.closest('.grs-direct-slider');
        if ($slider.hasClass('slick-initialized')) {
            $slider.slick('setPosition');
        }
    });
    
    // Check if Slick is loaded
    if (typeof $.fn.slick === 'undefined') {
        console.error('Slick is not loaded, waiting...');
        
        // Wait for Slick to load
        var checkSlick = setInterval(function() {
            if (typeof $.fn.slick !== 'undefined') {
                clearInterval(checkSlick);
                console.log('Slick loaded, initializing sliders...');
                initializeSliders();
            }
        }, 100);
    } else {
        // Initialize immediately
        initializeSliders();
    }
    
    // Also initialize on window load
    $(window).on('load', function() {
        setTimeout(initializeSliders, 100);
    });
    
    // Handle window resize
    var resizeTimer;
    $(window).on('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            $('.grs-direct-slider.slick-initialized').slick('setPosition');
        }, 250);
    });

});