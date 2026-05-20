import { nanoid } from 'nanoid';
import Swiper from 'swiper/bundle';

/**
 * Initialize a Swiper instance for a given element.
 * @param el
 * @param swiperClass
 * @param options
 */
const initializeSwiper = (el, swiperClass, options) => {
    if (!(el instanceof Element)) {
        console.error('Error: The provided element is not a valid DOM element.');
        return;
    }

    if (el.classList.contains('swiper-initialized') || el.dataset.swiperInitialized) return; // Prevent re-initialization
    el.dataset.swiperInitialized = 'true';

    const swiper = new Swiper(swiperClass, options);

    // Pause autoplay on hover, resume on mouse out
    el.addEventListener('mouseover', () => swiper.autoplay?.stop());
    el.addEventListener('mouseout', () => options.autoplay && swiper.autoplay?.start());

    return swiper;
};

/**
 * Generate unique class names for Swiper instance.
 * @returns {Object} - Object containing unique class names.
 */
const generateClasses = () => {
    const rand = nanoid(10);
    return {
        rand: rand,
        swiperClass: `swiper-${rand}`,
        nextClass: `next-${rand}`,
        prevClass: `prev-${rand}`,
        paginationClass: `pagination-${rand}`,
        scrollbarClass: `scrollbar-${rand}`,
    };
};

/**
 * Default Swiper options.
 * @returns {Object} - Default Swiper configuration.
 */
const getDefaultOptions = () => ({
    grabCursor: true,
    allowTouchMove: true,
    threshold: 5,
    autoHeight: false,
    loop: false,
    hashNavigation: false,
    direction: 'horizontal',
    freeMode: false,
    cssMode: false,
    centeredSlides: false,
    slidesPerView: 'auto',
});

/**
 * Parse options safely
 * @param el
 * @returns {{}|any|{}}
 */
const parseOptions = (el) => {
    try {
        return JSON.parse(el.dataset.options) || {};
    } catch (e) {
        console.error('Invalid JSON in data-options', e);
        return {};
    }
};

// Initialize Swipers
const initializeSwipers = () => {
    const swiperElements = document.querySelectorAll('.w-swiper');
    swiperElements.forEach((el) => {
        if (el.classList.contains('swiper-initialized')) return; // Prevent re-initialization

        const classes = generateClasses();
        el.classList.add(classes.swiperClass);

        const container = el.closest('.swiper-container');

        // Create or get control container
        let controls = container?.querySelector('.swiper-controls');
        if (!controls) {
            controls = document.createElement('div');
            controls.classList.add('swiper-controls');
            el.after(controls);
        }

        let options = parseOptions(el);
        let swiperOptions = { ...getDefaultOptions() };

        // Parse specific options
        [
            'autoHeight',
            'loop',
            'freeMode',
            'cssMode',
            'mousewheel',
            'parallax',
            'hashNavigation',
        ].forEach(key => options[key] && (swiperOptions[key] = true));

        swiperOptions.wrapperClass = String(options.wrapperClass || 'swiper-wrapper');
        swiperOptions.slideClass = String(options.slideClass || 'swiper-slide');
        swiperOptions.slideActiveClass = String(options.slideActiveClass || 'swiper-slide-active');

        swiperOptions.direction = String(options.direction || 'horizontal');
        swiperOptions.slidesPerView = options.slidesPerView || 'auto';
        swiperOptions.spaceBetween = parseInt(options.spaceBetween, 10) || 0;
        swiperOptions.speed = parseInt(options.speed, 10) || 300;

        // Grid settings
        if (options.grid) {
            swiperOptions.grid = {
                rows: Math.max(parseInt(options.grid?.rows) || 1, 1),
                fill: options.grid?.fill || 'row',
            };
            if (options.loop) swiperOptions.loopAddBlankSlides = true;
        }

        // Autoplay settings
        if (options.autoplay) {
            swiperOptions.autoplay = {
                delay: parseInt(options.autoplay?.delay) || 3000,
                disableOnInteraction: options.autoplay?.disableOnInteraction || true,
                reverseDirection: options.autoplay?.reverseDirection || false,
            };
        }

        // Navigation controls
        if (options.navigation) {
            let btnPrev = container?.querySelector('.swiper-button-prev');
            let btnNext = container?.querySelector('.swiper-button-next');

            if (!btnPrev) {
                btnPrev = document.createElement('div');
                btnPrev.classList.add('swiper-button', 'swiper-button-prev');
                btnPrev.setAttribute('data-fa', '');
                controls.append(btnPrev);
            }
            if (!btnNext) {
                btnNext = document.createElement('div');
                btnNext.classList.add('swiper-button', 'swiper-button-next');
                btnNext.setAttribute('data-fa', '');
                controls.append(btnNext);
            }

            btnPrev.classList.add(classes.prevClass);
            btnNext.classList.add(classes.nextClass);

            swiperOptions.navigation = {
                nextEl: `.${classes.nextClass}`,
                prevEl: `.${classes.prevClass}`,
            };
        }

        // Pagination controls
        if (options.pagination) {
            let pagination = container?.querySelector('.swiper-pagination');
            if (!pagination) {
                pagination = document.createElement('div');
                pagination.classList.add('swiper-pagination');
                controls.appendChild(pagination);
            }

            pagination.classList.add(classes.paginationClass);

            const paginationType = String(options.pagination);
            swiperOptions.pagination = {
                el: `.${classes.paginationClass}`,
                clickable: true,
                ...(paginationType === 'bullets' && { dynamicBullets: !0, type: 'bullets' }),
                ...(paginationType === 'fraction' && { type: 'fraction' }),
                ...(paginationType === 'progressbar' && { type: 'progressbar' }),
                ...(paginationType === 'custom' && {
                    renderBullet: (index, className) => `<span class="${className}">${index + 1}</span>`,
                }),
            };
        }

        // Scrollbar controls
        if (options.scrollbar) {
            let scrollbar = container?.querySelector('.swiper-scrollbar');
            if (!scrollbar) {
                scrollbar = document.createElement('div');
                scrollbar.classList.add('swiper-scrollbar');
                controls.appendChild(scrollbar);
            }

            scrollbar.classList.add(classes.scrollbarClass);

            swiperOptions.scrollbar = {
                el: `.${classes.scrollbarClass}`,
                hide: true,
                draggable: true,
            };
        }

        // observer
        if (options._observer) {
            swiperOptions.observer = !0;
            swiperOptions.observeParents = !0;
            swiperOptions.observeSlideChildren = !0;
        }

        // centeredSlides
        if (options._centered) {
            swiperOptions.centeredSlides = !0;
            swiperOptions.centeredSlidesBounds = !0;
        }

        // marquee
        if (options._marquee) {
            swiperOptions.centeredSlides = !1;
            swiperOptions.autoplay = {
                delay: 1,
                disableOnInteraction: !0,
            };
            swiperOptions.loop = !0;
            swiperOptions.speed = 6000;
            swiperOptions.allowTouchMove = !0;
        }

        // spaceBetween breakpoints
        if (options._gap) {
            swiperOptions.spaceBetween = 20;
            swiperOptions.breakpoints = {
                768: { spaceBetween: 28 },
            };
        }

        // breakpoints
        if (options._breakpoints) {
            swiperOptions.breakpoints = {
                0: options?._mobile || {},
                768: options?._tablet || {},
                1024: options?._desktop || {},
            };
        }

        initializeSwiper(el, `.${classes.swiperClass}`, swiperOptions);
    });
};

//
// Products slides
//
const spgSwipers = () => {
    const swiperElements = [...document?.querySelectorAll('.swiper-product-gallery')];

    swiperElements.forEach((el, index) => {
        const classes = generateClasses();
        el.classList.add(classes.swiperClass);

        const w_images = el?.querySelector('.swiper-images');
        const w_thumbs = el?.querySelector('.swiper-thumbs');

        let swiper_images = false;
        let swiper_thumbs = false;

        /** wpg thumbs */
        if (w_thumbs) {
            w_thumbs?.querySelector('.swiper-button-prev').classList.add('prev-thumbs-' + classes.rand);
            w_thumbs?.querySelector('.swiper-button-next').classList.add('next-thumbs-' + classes.rand);
            w_thumbs.classList.add('thumbs-' + classes.rand);

            let thumbs_options = { ...getDefaultOptions() };
            thumbs_options.breakpoints = {
                0: {
                    spaceBetween: 5,
                    slidesPerView: 3,
                },
                768: {
                    spaceBetween: 10,
                    slidesPerView: 3,
                },
                1024: {
                    spaceBetween: 10,
                    slidesPerView: 5,
                },
            };

            thumbs_options.navigation = {
                prevEl: '.prev-thumbs-' + classes.rand,
                nextEl: '.next-thumbs-' + classes.rand,
            };

            swiper_thumbs = initializeSwiper(w_thumbs, '.thumbs-' + classes.rand, thumbs_options);
        }

        /** wpg images */
        if (w_images) {
            w_images?.querySelector('.swiper-button-prev').classList.add('prev-images-' + classes.rand);
            w_images?.querySelector('.swiper-button-next').classList.add('next-images-' + classes.rand);
            w_images.classList.add('images-' + classes.rand);

            let images_options = { ...getDefaultOptions() };
            images_options.slidesPerView = 'auto';
            images_options.spaceBetween = 10;
            images_options.watchSlidesProgress = !0;

            images_options.navigation = {
                prevEl: '.prev-images-' + classes.rand,
                nextEl: '.next-images-' + classes.rand,
            };

            if (swiper_thumbs) {
                images_options.thumbs = {
                    swiper: swiper_thumbs,
                };
            }

            swiper_images = initializeSwiper(w_images, '.images-' + classes.rand, images_options);
        }

        /** variation image */
        let firstImage = w_images?.querySelector('.swiper-images-first img');
        let firstImageIframe = w_images?.querySelector('.swiper-images-video iframe');
        let firstImageVideo = w_images?.querySelector('.swiper-images-video');
        firstImage.removeAttribute('srcset');

        let firstImageSrc = firstImage.getAttribute('src');
        let imagePopupSrc = w_images?.querySelector('.swiper-images-first .image-popup');

        let firstThumb = false;
        let firstThumbSrc = false;
        let dataLargeImage = false;

        if (swiper_thumbs) {
            firstThumb = w_thumbs?.querySelector('.swiper-thumbs-first img');
            firstThumb.removeAttribute('srcset');

            firstThumbSrc = firstThumb.getAttribute('src');
            dataLargeImage = firstThumb.getAttribute('data-large_image');
        }
        let lastAddedSlideIndex = null;
        let galleryWrappers = document.querySelectorAll('.woocommerce-product-gallery__wrapper .swiper-slide');
        galleryWrappers.forEach(function(wrapper) {
            wrapper.classList.add('active');
        });
        /** WC event */
        const variations_form = $('form.variations_form');
        variations_form.on('found_variation', function (event, variation) {
            if (variation.image.src) {
                if (lastAddedSlideIndex !== null) {
                    swiper_images.removeSlide(lastAddedSlideIndex);
                }
                let newSlide = document.createElement('div');
                newSlide.classList.add('swiper-slide'); // Thêm class swiper-slide
                // Tạo img mới cho slide
                let newImg = document.createElement('img');
                newImg.setAttribute('src', variation.image.src);
                newImg.setAttribute('data-src', variation.image.src);
                newImg.setAttribute('data-large_image', variation.image.src);
                newImg.setAttribute('alt', variation.image.alt);
                newImg.classList.add('wp-post-image');
                let newLink = document.createElement('a');
                newLink.classList.add('res', 'ar[1-1]', 'fcy-popup');
                // newLink.setAttribute('data-fancybox', 'gallery');
                newLink.setAttribute('href', variation.image.src);
                newLink.appendChild(newImg);
                let newDiv = document.createElement('div');
                newDiv.setAttribute('data-thumb', variation.image.src);
                newDiv.classList.add('wpg__image', 'cover');
                newDiv.appendChild(newLink);
                let newSpan = document.createElement('span');
                newSpan.setAttribute('data-rel', 'lightbox');
                newSpan.classList.add('image-popup');
                newSpan.setAttribute('data-src', variation.image.src);
                newSpan.setAttribute('data-fa', '');
                newDiv.appendChild(newSpan);
                newSlide.appendChild(newDiv);
                swiper_images.appendSlide(newSlide);
                lastAddedSlideIndex = swiper_images.slides.length - 1;
                swiper_images.slideTo(lastAddedSlideIndex);
                // swiper_images.slideTo(0);
                // if (firstImageVideo) {
                //     if (firstImageIframe) {
                //         firstImageIframe.classList.add('hidden');
                //     }
                //     let existingImg = firstImageVideo.querySelector('img');
                //     if (existingImg) {
                //         firstImageVideo.removeChild(existingImg);
                //     }
                //     let newImg = document.createElement('img');
                //     newImg.setAttribute('src', variation.image.src);
                //     newImg.setAttribute('alt', 'img'); 
                //     newImg.classList.add('wp-post-image');
                //     let newLink = document.createElement('a');
                //     newLink.classList.add('res', 'ar[1-1]');
                //     newLink.setAttribute('href', variation.image.src);
                //     newLink.appendChild(newImg);
                //     let newDiv = document.createElement('div');
                //     newDiv.setAttribute('data-thumb', variation.image.src);
                //     newDiv.classList.add('wpg__image', 'cover');
                //     newDiv.appendChild(newLink);
                //     let newSpan = document.createElement('span');
                //     newSpan.setAttribute('data-rel', 'lightbox');
                //     newSpan.classList.add('image-popup');
                //     newSpan.setAttribute('data-src', variation.image.src);
                //     newSpan.setAttribute('data-glyph', '');
                //     newDiv.appendChild(newSpan);
                //     firstImageVideo.appendChild(newDiv);
                // }
                // firstImage.setAttribute('src', variation.image.src);
                // imagePopupSrc.setAttribute('data-src', variation.image.full_src);
                // if (swiper_thumbs) {
                //     firstThumb.setAttribute('src', variation.image.gallery_thumbnail_src);
                // }
                //swiper_images.slideTo(0);
            }
        });

        variations_form.on('reset_image', function () {
            firstImage.setAttribute('src', firstImageSrc);
            imagePopupSrc.setAttribute('data-src', dataLargeImage);
            
            if (swiper_thumbs) {
                firstThumb.setAttribute('src', firstThumbSrc);
            }
            swiper_images.slideTo(0);
        });
    });
};

document.addEventListener('DOMContentLoaded', initializeSwipers);
document.addEventListener('DOMContentLoaded', spgSwipers);