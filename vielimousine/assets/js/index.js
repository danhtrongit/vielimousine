import { O as Oe } from "./_vendor.js";
import "./global.js";
import "./lighthouse.js";
import "./back-to-top.js";
import "./script-loader.js";
import { i as initMenu } from "./menu.js";
import { i as initSocialShare } from "./social-share.js";
document.addEventListener("DOMContentLoaded", () => {
  initMenu("nav.nav", ".main-nav");
  initSocialShare("[data-social-share]", { intents: ["facebook", "x", "print", "send-email", "copy-link", "web-share"] });
});
Oe.bind(".fcy-popup, .fcy-video, .banner-video a", {});
Oe.bind('.wp-block-gallery .wp-block-image a, [id^="gallery-"] a, [data-rel="lightbox"]', {
  groupAll: true
  // Group all items
});
Oe.bind("[data-fancybox]", {});
jQuery(function() {
  var header = document.getElementById("inside-header");
  var bottomHeader = document.getElementById("bottom-header");
  if (!header || !bottomHeader) return;
  var headerHeight = header.offsetHeight;
  var ticking = false;
  window.addEventListener("scroll", function() {
    if (!ticking) {
      requestAnimationFrame(function() {
        if (window.scrollY > headerHeight + 150) {
          bottomHeader.classList.add("w-sticky");
        } else {
          bottomHeader.classList.remove("w-sticky");
        }
        ticking = false;
      });
      ticking = true;
    }
  }, { passive: true });
});
jQuery(document).ready(function() {
  jQuery(".menu-expend > i").click(function() {
    jQuery(this).closest(".menu-expend").toggleClass("active");
  });
  jQuery(".lists-faq .tab-title").on("click", function() {
    var $tabContent = jQuery(this).next(".tab-content");
    var $toggleItem = jQuery(this).closest(".toggle-item");
    $toggleItem.toggleClass("active");
    requestAnimationFrame(function() {
      if ($toggleItem.hasClass("active")) {
        $tabContent.css("max-height", $tabContent[0].scrollHeight + "px");
      } else {
        $tabContent.css("max-height", "0");
      }
    });
  });
});
jQuery(document).ready(function($) {
  var $content = $(".rental-policy .content-rental");
  var $button = $(".toggle-button");
  var $button_text = $(".toggle-button span");
  var $mask = $(".content-toggle-wrapper .mask");
  var collapsedHeight = 300;
  if ($content.length && $content.prop("scrollHeight") > collapsedHeight) {
    $content.css({
      "height": collapsedHeight + "px",
      "overflow": "hidden",
      "will-change": "height",
      "transition": "height 0.15s ease"
    }).data("expanded", false);
  } else {
    $button.hide();
  }
  $button.on("click", function() {
    if (!$content.length) return;
    if (!$content.data("expanded")) {
      $content.css("height", $content.prop("scrollHeight") + "px");
      $content.one("transitionend", function() {
        $content.css({ "height": "auto", "overflow": "visible" });
      });
      $content.data("expanded", true);
      $button_text.text("Thu gọn");
      $button.addClass("active");
      $mask.addClass("hidden");
    } else {
      var scrollH = $content.prop("scrollHeight");
      $content.css({ "overflow": "hidden", "height": scrollH + "px", "transition": "none" });
      $content[0].offsetHeight;
      $content.css({ "transition": "height 0.15s ease", "height": collapsedHeight + "px" });
      $content.data("expanded", false);
      $button_text.text("Xem thêm");
      $button.removeClass("active");
      $mask.removeClass("hidden");
    }
  });
});
jQuery(document).ready(function() {
  jQuery("#tour_gallery-list").slick({
    slidesToShow: 1,
    slidesToScroll: 1,
    arrows: true,
    fade: true,
    asNavFor: ".tour_gallery-nav",
    prevArrow: '<button type="button" class="slick-prev"><i class="fas fa-angle-left"></i></button>',
    nextArrow: '<button type="button" class="slick-next"><i class="fas fa-angle-right"></i></button>'
  });
  jQuery(".tour_gallery-nav").slick({
    slidesToShow: 5,
    slidesToScroll: 1,
    asNavFor: "#tour_gallery-list",
    dots: false,
    focusOnSelect: true,
    arrows: false,
    infinite: true,
    responsive: [
      {
        breakpoint: 767,
        settings: {
          slidesToShow: 4,
          slidesToScroll: 1
        },
        breakpoint: 641,
        settings: {
          slidesToShow: 3,
          slidesToScroll: 1
        }
      }
    ]
  });
});
//# sourceMappingURL=index.js.map
