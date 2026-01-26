/**
 * Google Reviews Slider - Swiper.js Initialization
 * Optimized for iOS/Safari compatibility
 */
document.addEventListener('DOMContentLoaded', function() {
    console.log('GRS: Initializing Swiper...');

    // Initialize all sliders
    document.querySelectorAll('.grs-swiper').forEach(function(el) {
        var autoplay = el.dataset.autoplay !== 'false';
        var autoplaySpeed = parseInt(el.dataset.autoplaySpeed) || 4000;
        var slidesDesktop = parseInt(el.dataset.slidesDesktop) || 3;
        var slidesTablet = parseInt(el.dataset.slidesTablet) || 2;
        var slidesMobile = parseInt(el.dataset.slidesMobile) || 1;

        var swiper = new Swiper(el, {
            // Core settings
            slidesPerView: slidesMobile,
            spaceBetween: 20,
            loop: true,
            grabCursor: true,

            // iOS/Safari critical settings
            touchEventsTarget: 'container',
            simulateTouch: true,
            allowTouchMove: true,
            touchRatio: 1,
            touchAngle: 45,
            shortSwipes: true,
            longSwipes: true,
            longSwipesRatio: 0.5,
            longSwipesMs: 300,
            followFinger: true,
            threshold: 5,
            touchMoveStopPropagation: false,
            touchStartPreventDefault: false,
            touchReleaseOnEdges: true,
            passiveListeners: true,
            resistance: true,
            resistanceRatio: 0.85,

            // Autoplay
            autoplay: autoplay ? {
                delay: autoplaySpeed,
                disableOnInteraction: false,
                pauseOnMouseEnter: true
            } : false,

            // Pagination
            pagination: {
                el: el.querySelector('.swiper-pagination'),
                clickable: true,
                dynamicBullets: true
            },

            // Navigation (buttons are outside swiper, in parent container)
            navigation: {
                nextEl: el.closest('.grs-direct-slider-container').querySelector('.grs-nav-next'),
                prevEl: el.closest('.grs-direct-slider-container').querySelector('.grs-nav-prev')
            },

            // Responsive breakpoints
            breakpoints: {
                320: {
                    slidesPerView: slidesMobile,
                    spaceBetween: 15
                },
                768: {
                    slidesPerView: slidesTablet,
                    spaceBetween: 20
                },
                1024: {
                    slidesPerView: slidesDesktop,
                    spaceBetween: 25
                }
            },

            // Accessibility
            a11y: {
                prevSlideMessage: 'Previous review',
                nextSlideMessage: 'Next review',
                firstSlideMessage: 'First review',
                lastSlideMessage: 'Last review'
            }
        });

        // iOS Safari: Restart autoplay after interaction
        if (autoplay) {
            el.addEventListener('touchend', function() {
                setTimeout(function() {
                    if (swiper.autoplay && !swiper.autoplay.running) {
                        swiper.autoplay.start();
                    }
                }, 1000);
            });
        }

        console.log('GRS: Swiper initialized successfully');
    });

    // Read more/less functionality
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('grs-direct-read-more')) {
            e.preventDefault();
            var review = e.target.closest('.grs-direct-review');
            var text = review.querySelector('.grs-direct-text');
            var showLess = review.querySelector('.grs-direct-hide');
            var swiperEl = e.target.closest('.grs-swiper');

            // Expand the text
            text.classList.remove('truncated');
            text.classList.add('expanded');
            e.target.style.display = 'none';
            if (showLess) showLess.style.display = 'inline';

            // Update Swiper to recalculate heights
            if (swiperEl && swiperEl.swiper) {
                setTimeout(function() {
                    swiperEl.swiper.update();
                }, 50);
            }
        }

        if (e.target.classList.contains('grs-direct-hide')) {
            e.preventDefault();
            var review = e.target.closest('.grs-direct-review');
            var text = review.querySelector('.grs-direct-text');
            var readMore = review.querySelector('.grs-direct-read-more');
            var swiperEl = e.target.closest('.grs-swiper');

            // Collapse the text
            text.classList.remove('expanded');
            text.classList.add('truncated');
            e.target.style.display = 'none';
            if (readMore) readMore.style.display = 'inline';

            // Update Swiper to recalculate heights
            if (swiperEl && swiperEl.swiper) {
                setTimeout(function() {
                    swiperEl.swiper.update();
                }, 50);
            }
        }
    });
});
