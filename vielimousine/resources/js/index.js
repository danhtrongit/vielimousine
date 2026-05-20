import $ from 'jquery';
import Foundation from './3rd/_zf.js';
import { hdConfig } from './components/config.js';

import './components/global.js';
import './components/lighthouse.js';
import './components/back-to-top.js';
import './components/script-loader.js';
import { initMenu } from './components/menu.js';
import { initSocialShare } from './components/social-share.js';
//import SimpleBar from 'simplebar';
//import ResizeObserver from 'resize-observer-polyfill';
import { Fancybox } from '@fancyapps/ui';
//import select2 from 'select2';

//window.ResizeObserver = ResizeObserver;
//select2();

// Styles
import '../sass/3rd/_index.scss';
//import 'simplebar/dist/simplebar.css';
import '@fancyapps/ui/dist/fancybox/fancybox.css';

// DOMContentLoaded
document.addEventListener('DOMContentLoaded', () => {
    initMenu('nav.nav', '.main-nav');
    initSocialShare('[data-social-share]', { intents: [ 'facebook', 'x', 'print', 'send-email', 'copy-link', 'web-share' ] });
});

Fancybox.bind('.fcy-popup, .fcy-video, .banner-video a', {});
Fancybox.bind('.wp-block-gallery .wp-block-image a, [id^="gallery-"] a, [data-rel="lightbox"]', {
    groupAll: true, // Group all items
});
Fancybox.bind("[data-fancybox]", {});

//add sticky
jQuery(function ($) {
    let header = $('#inside-header');
    let bottomHeader = $('#bottom-header');
    let headerHeight = header.outerHeight();
    $(window).on('scroll', function () {
        let scrollTop = $(this).scrollTop();
        if (scrollTop > headerHeight + 150) {
            bottomHeader.addClass('w-sticky');
        } else {
            bottomHeader.removeClass('w-sticky');
        }
    });
});

jQuery(document).ready(function () {
    jQuery(".menu-expend > i").click(function () {
        jQuery(this).closest(".menu-expend").toggleClass("active");
    });
    // faq
    jQuery('.lists-faq .tab-title').click(function () {
        var $tabContent = jQuery(this).next('.tab-content');
        var $toggleItem = jQuery(this).closest('.toggle-item');
        // Toggle active class
        $toggleItem.toggleClass('active');
        if ($toggleItem.hasClass('active')) {
            var scrollHeight = $tabContent[0].scrollHeight;
            $tabContent.css('max-height', scrollHeight + 'px');
        } else {
            $tabContent.css('max-height', '0');
        }
    });
    // home partner
    // jQuery(".home-partner .gallery-mb .wrapper").slick({
    //     infinite: true,
    //     autoplay: true,
    //     autoplaySpeed: 4000,
    //     slidesToShow: 2,
    //     slidesToScroll: 1,
    //     arrows: false,
    //     dots: true,
    // });
});


// Js button xem them - rut gon noi dung
jQuery(document).ready(function($) {
    var $content = $('.rental-policy .content-rental');
    var $button = $('.toggle-button');
    var $button_text = $('.toggle-button span');
    var $mask = $('.content-toggle-wrapper .mask');
    var collapsedHeight = 300;
    if ($content.prop('scrollHeight') > collapsedHeight) {
        $content.css({
            'height': collapsedHeight,
            'overflow': 'hidden'
        }).data('expanded', false);
    } else {
        $button.hide();
    }

    $button.on('click', function() {
        if (!$content.data('expanded')) {
            $content.stop().animate({
                height: $content.prop('scrollHeight')
            }, 400, function() {
                $content.css('height', 'auto');
            });
            $content.data('expanded', true);
            $button_text.text('Thu gọn');
            $button.addClass('active');
            $mask.addClass('hidden');
        } else {
            $content.stop().animate({
                height: collapsedHeight
            }, 400);
            $content.data('expanded', false);
            $button_text.text('Xem thêm');
            $button.removeClass('active');
            $mask.removeClass('hidden');
            
        }
    });
});

jQuery(document).ready(function () {
    jQuery("#tour_gallery-list").slick({
        slidesToShow: 1,
        slidesToScroll: 1,
        arrows: true,
        fade: true,
        asNavFor: ".tour_gallery-nav",
        prevArrow:
        '<button type="button" class="slick-prev"><i class="fas fa-angle-left"></i></button>',
        nextArrow:
        '<button type="button" class="slick-next"><i class="fas fa-angle-right"></i></button>',
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
                slidesToScroll: 1,
            },
                breakpoint: 641,
                settings: {
                slidesToShow: 3,
                slidesToScroll: 1,
            },
        },
        ],
    });
});