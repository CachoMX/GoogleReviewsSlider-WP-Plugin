/**
 * Google Reviews Slider - Swiper.js Initialization
 * Optimized for iOS/Safari compatibility
 */
document.addEventListener('DOMContentLoaded', function() {
    // Initialize all sliders
    document.querySelectorAll('.grs-swiper').forEach(function(el) {
        var autoplay = el.dataset.autoplay !== 'false';
        var autoplaySpeed = parseInt(el.dataset.autoplaySpeed) || 4000;
        var slidesDesktop = parseInt(el.dataset.slidesDesktop) || 3;
        var slidesTablet = parseInt(el.dataset.slidesTablet) || 2;
        var slidesMobile = parseInt(el.dataset.slidesMobile) || 1;
        // Swiper 11 disables loop with a console warning when
        // slides <= slidesPerView; skip it up front in that case.
        var slideCount = el.querySelectorAll('.swiper-slide').length;

        var swiper = new Swiper(el, {
            // Core settings
            slidesPerView: slidesMobile,
            spaceBetween: 20,
            loop: slideCount > slidesDesktop,
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
            // dynamicBullets injects an inline width + translateX on the
            // .swiper-pagination element, which fights the CSS centering and
            // shoves the dots off to one side. Keep it off so the bullets stay
            // centered via plain text-align:center.
            pagination: {
                el: el.querySelector('.swiper-pagination'),
                clickable: true,
                dynamicBullets: false
            },

            // Nav buttons live outside the swiper element and are wired up
            // manually below; passing them to Swiper's navigation module too
            // would bind a second click handler and advance two slides per click.

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

        // touchend calls preventDefault, which suppresses the synthetic click
        // that follows a tap, so mobile taps advance exactly one slide.
        var container = el.closest('.grs-direct-slider-container');
        var prevBtn = container ? container.querySelector('.grs-nav-prev') : null;
        var nextBtn = container ? container.querySelector('.grs-nav-next') : null;

        if (prevBtn) {
            prevBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                swiper.slidePrev();
            });
            prevBtn.addEventListener('touchend', function(e) {
                e.preventDefault();
                e.stopPropagation();
                swiper.slidePrev();
            });
        }

        if (nextBtn) {
            nextBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                swiper.slideNext();
            });
            nextBtn.addEventListener('touchend', function(e) {
                e.preventDefault();
                e.stopPropagation();
                swiper.slideNext();
            });
        }
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
