import { n as nanoid, S as Swiper } from "./_vendor.js";
const initializeSwiper = (el, swiperClass, options) => {
  if (!(el instanceof Element)) {
    console.error("Error: The provided element is not a valid DOM element.");
    return;
  }
  if (el.classList.contains("swiper-initialized") || el.dataset.swiperInitialized) return;
  el.dataset.swiperInitialized = "true";
  const swiper = new Swiper(swiperClass, options);
  el.addEventListener("mouseover", () => {
    var _a;
    return (_a = swiper.autoplay) == null ? void 0 : _a.stop();
  });
  el.addEventListener("mouseout", () => {
    var _a;
    return options.autoplay && ((_a = swiper.autoplay) == null ? void 0 : _a.start());
  });
  return swiper;
};
const generateClasses = () => {
  const rand = nanoid(10);
  return {
    rand,
    swiperClass: `swiper-${rand}`,
    nextClass: `next-${rand}`,
    prevClass: `prev-${rand}`,
    paginationClass: `pagination-${rand}`,
    scrollbarClass: `scrollbar-${rand}`
  };
};
const getDefaultOptions = () => ({
  grabCursor: true,
  allowTouchMove: true,
  threshold: 5,
  autoHeight: false,
  loop: false,
  hashNavigation: false,
  direction: "horizontal",
  freeMode: false,
  cssMode: false,
  centeredSlides: false,
  slidesPerView: "auto"
});
const parseOptions = (el) => {
  try {
    return JSON.parse(el.dataset.options) || {};
  } catch (e) {
    console.error("Invalid JSON in data-options", e);
    return {};
  }
};
const initializeSwipers = () => {
  const swiperElements = document.querySelectorAll(".w-swiper");
  swiperElements.forEach((el) => {
    var _a, _b, _c, _d, _e;
    if (el.classList.contains("swiper-initialized")) return;
    const classes = generateClasses();
    el.classList.add(classes.swiperClass);
    const container = el.closest(".swiper-container");
    let controls = container == null ? void 0 : container.querySelector(".swiper-controls");
    if (!controls) {
      controls = document.createElement("div");
      controls.classList.add("swiper-controls");
      el.after(controls);
    }
    let options = parseOptions(el);
    let swiperOptions = { ...getDefaultOptions() };
    [
      "autoHeight",
      "loop",
      "freeMode",
      "cssMode",
      "mousewheel",
      "parallax",
      "hashNavigation"
    ].forEach((key) => options[key] && (swiperOptions[key] = true));
    swiperOptions.wrapperClass = String(options.wrapperClass || "swiper-wrapper");
    swiperOptions.slideClass = String(options.slideClass || "swiper-slide");
    swiperOptions.slideActiveClass = String(options.slideActiveClass || "swiper-slide-active");
    swiperOptions.direction = String(options.direction || "horizontal");
    swiperOptions.slidesPerView = options.slidesPerView || "auto";
    swiperOptions.spaceBetween = parseInt(options.spaceBetween, 10) || 0;
    swiperOptions.speed = parseInt(options.speed, 10) || 300;
    if (options.grid) {
      swiperOptions.grid = {
        rows: Math.max(parseInt((_a = options.grid) == null ? void 0 : _a.rows) || 1, 1),
        fill: ((_b = options.grid) == null ? void 0 : _b.fill) || "row"
      };
      if (options.loop) swiperOptions.loopAddBlankSlides = true;
    }
    if (options.autoplay) {
      swiperOptions.autoplay = {
        delay: parseInt((_c = options.autoplay) == null ? void 0 : _c.delay) || 3e3,
        disableOnInteraction: ((_d = options.autoplay) == null ? void 0 : _d.disableOnInteraction) || true,
        reverseDirection: ((_e = options.autoplay) == null ? void 0 : _e.reverseDirection) || false
      };
    }
    if (options.navigation) {
      let btnPrev = container == null ? void 0 : container.querySelector(".swiper-button-prev");
      let btnNext = container == null ? void 0 : container.querySelector(".swiper-button-next");
      if (!btnPrev) {
        btnPrev = document.createElement("div");
        btnPrev.classList.add("swiper-button", "swiper-button-prev");
        btnPrev.setAttribute("data-fa", "");
        controls.append(btnPrev);
      }
      if (!btnNext) {
        btnNext = document.createElement("div");
        btnNext.classList.add("swiper-button", "swiper-button-next");
        btnNext.setAttribute("data-fa", "");
        controls.append(btnNext);
      }
      btnPrev.classList.add(classes.prevClass);
      btnNext.classList.add(classes.nextClass);
      swiperOptions.navigation = {
        nextEl: `.${classes.nextClass}`,
        prevEl: `.${classes.prevClass}`
      };
    }
    if (options.pagination) {
      let pagination = container == null ? void 0 : container.querySelector(".swiper-pagination");
      if (!pagination) {
        pagination = document.createElement("div");
        pagination.classList.add("swiper-pagination");
        controls.appendChild(pagination);
      }
      pagination.classList.add(classes.paginationClass);
      const paginationType = String(options.pagination);
      swiperOptions.pagination = {
        el: `.${classes.paginationClass}`,
        clickable: true,
        ...paginationType === "bullets" && { dynamicBullets: true, type: "bullets" },
        ...paginationType === "fraction" && { type: "fraction" },
        ...paginationType === "progressbar" && { type: "progressbar" },
        ...paginationType === "custom" && {
          renderBullet: (index, className) => `<span class="${className}">${index + 1}</span>`
        }
      };
    }
    if (options.scrollbar) {
      let scrollbar = container == null ? void 0 : container.querySelector(".swiper-scrollbar");
      if (!scrollbar) {
        scrollbar = document.createElement("div");
        scrollbar.classList.add("swiper-scrollbar");
        controls.appendChild(scrollbar);
      }
      scrollbar.classList.add(classes.scrollbarClass);
      swiperOptions.scrollbar = {
        el: `.${classes.scrollbarClass}`,
        hide: true,
        draggable: true
      };
    }
    if (options._observer) {
      swiperOptions.observer = true;
      swiperOptions.observeParents = true;
      swiperOptions.observeSlideChildren = true;
    }
    if (options._centered) {
      swiperOptions.centeredSlides = true;
      swiperOptions.centeredSlidesBounds = true;
    }
    if (options._marquee) {
      swiperOptions.centeredSlides = false;
      swiperOptions.autoplay = {
        delay: 1,
        disableOnInteraction: true
      };
      swiperOptions.loop = true;
      swiperOptions.speed = 6e3;
      swiperOptions.allowTouchMove = true;
    }
    if (options._gap) {
      swiperOptions.spaceBetween = 20;
      swiperOptions.breakpoints = {
        768: { spaceBetween: 28 }
      };
    }
    if (options._breakpoints) {
      swiperOptions.breakpoints = {
        0: (options == null ? void 0 : options._mobile) || {},
        768: (options == null ? void 0 : options._tablet) || {},
        1024: (options == null ? void 0 : options._desktop) || {}
      };
    }
    initializeSwiper(el, `.${classes.swiperClass}`, swiperOptions);
  });
};
const spgSwipers = () => {
  const swiperElements = [...document == null ? void 0 : document.querySelectorAll(".swiper-product-gallery")];
  swiperElements.forEach((el, index) => {
    const classes = generateClasses();
    el.classList.add(classes.swiperClass);
    const w_images = el == null ? void 0 : el.querySelector(".swiper-images");
    const w_thumbs = el == null ? void 0 : el.querySelector(".swiper-thumbs");
    let swiper_images = false;
    let swiper_thumbs = false;
    if (w_thumbs) {
      w_thumbs == null ? void 0 : w_thumbs.querySelector(".swiper-button-prev").classList.add("prev-thumbs-" + classes.rand);
      w_thumbs == null ? void 0 : w_thumbs.querySelector(".swiper-button-next").classList.add("next-thumbs-" + classes.rand);
      w_thumbs.classList.add("thumbs-" + classes.rand);
      let thumbs_options = { ...getDefaultOptions() };
      thumbs_options.breakpoints = {
        0: {
          spaceBetween: 5,
          slidesPerView: 3
        },
        768: {
          spaceBetween: 10,
          slidesPerView: 3
        },
        1024: {
          spaceBetween: 10,
          slidesPerView: 5
        }
      };
      thumbs_options.navigation = {
        prevEl: ".prev-thumbs-" + classes.rand,
        nextEl: ".next-thumbs-" + classes.rand
      };
      swiper_thumbs = initializeSwiper(w_thumbs, ".thumbs-" + classes.rand, thumbs_options);
    }
    if (w_images) {
      w_images == null ? void 0 : w_images.querySelector(".swiper-button-prev").classList.add("prev-images-" + classes.rand);
      w_images == null ? void 0 : w_images.querySelector(".swiper-button-next").classList.add("next-images-" + classes.rand);
      w_images.classList.add("images-" + classes.rand);
      let images_options = { ...getDefaultOptions() };
      images_options.slidesPerView = "auto";
      images_options.spaceBetween = 10;
      images_options.watchSlidesProgress = true;
      images_options.navigation = {
        prevEl: ".prev-images-" + classes.rand,
        nextEl: ".next-images-" + classes.rand
      };
      if (swiper_thumbs) {
        images_options.thumbs = {
          swiper: swiper_thumbs
        };
      }
      swiper_images = initializeSwiper(w_images, ".images-" + classes.rand, images_options);
    }
    let firstImage = w_images == null ? void 0 : w_images.querySelector(".swiper-images-first img");
    w_images == null ? void 0 : w_images.querySelector(".swiper-images-video iframe");
    w_images == null ? void 0 : w_images.querySelector(".swiper-images-video");
    firstImage.removeAttribute("srcset");
    let firstImageSrc = firstImage.getAttribute("src");
    let imagePopupSrc = w_images == null ? void 0 : w_images.querySelector(".swiper-images-first .image-popup");
    let firstThumb = false;
    let firstThumbSrc = false;
    let dataLargeImage = false;
    if (swiper_thumbs) {
      firstThumb = w_thumbs == null ? void 0 : w_thumbs.querySelector(".swiper-thumbs-first img");
      firstThumb.removeAttribute("srcset");
      firstThumbSrc = firstThumb.getAttribute("src");
      dataLargeImage = firstThumb.getAttribute("data-large_image");
    }
    let lastAddedSlideIndex = null;
    let galleryWrappers = document.querySelectorAll(".woocommerce-product-gallery__wrapper .swiper-slide");
    galleryWrappers.forEach(function(wrapper) {
      wrapper.classList.add("active");
    });
    const variations_form = $("form.variations_form");
    variations_form.on("found_variation", function(event, variation) {
      if (variation.image.src) {
        if (lastAddedSlideIndex !== null) {
          swiper_images.removeSlide(lastAddedSlideIndex);
        }
        let newSlide = document.createElement("div");
        newSlide.classList.add("swiper-slide");
        let newImg = document.createElement("img");
        newImg.setAttribute("src", variation.image.src);
        newImg.setAttribute("data-src", variation.image.src);
        newImg.setAttribute("data-large_image", variation.image.src);
        newImg.setAttribute("alt", variation.image.alt);
        newImg.classList.add("wp-post-image");
        let newLink = document.createElement("a");
        newLink.classList.add("res", "ar[1-1]", "fcy-popup");
        newLink.setAttribute("href", variation.image.src);
        newLink.appendChild(newImg);
        let newDiv = document.createElement("div");
        newDiv.setAttribute("data-thumb", variation.image.src);
        newDiv.classList.add("wpg__image", "cover");
        newDiv.appendChild(newLink);
        let newSpan = document.createElement("span");
        newSpan.setAttribute("data-rel", "lightbox");
        newSpan.classList.add("image-popup");
        newSpan.setAttribute("data-src", variation.image.src);
        newSpan.setAttribute("data-fa", "");
        newDiv.appendChild(newSpan);
        newSlide.appendChild(newDiv);
        swiper_images.appendSlide(newSlide);
        lastAddedSlideIndex = swiper_images.slides.length - 1;
        swiper_images.slideTo(lastAddedSlideIndex);
      }
    });
    variations_form.on("reset_image", function() {
      firstImage.setAttribute("src", firstImageSrc);
      imagePopupSrc.setAttribute("data-src", dataLargeImage);
      if (swiper_thumbs) {
        firstThumb.setAttribute("src", firstThumbSrc);
      }
      swiper_images.slideTo(0);
    });
  });
};
document.addEventListener("DOMContentLoaded", initializeSwipers);
document.addEventListener("DOMContentLoaded", spgSwipers);
//# sourceMappingURL=swiper.js.map
