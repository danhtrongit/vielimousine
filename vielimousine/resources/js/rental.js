jQuery(document).ready(function () {
    // gallery car-rental
    jQuery('.gallery-wrapper').each(function() {
        const $wrapper = jQuery(this);
        const forSlider = $wrapper.find('.slider-for');
        const navSlider = $wrapper.find('.slider-nav');
        forSlider.slick({
            infinite: true,
            slidesToShow: 1,
            slidesToScroll: 1,
            arrows: false,
            fade: true,
            asNavFor: navSlider
        });
        navSlider.slick({
            slidesToShow: 3,
            slidesToScroll: 1,
            asNavFor: forSlider,
            dots: false,
            arrows: false,
            infinite: true,
            // centerMode: true,
            focusOnSelect: true
        });
    });
});

// Js button xem them - rut gon noi dung
// jQuery(document).ready(function($) {
//     var $content = $('.rental-policy .content-rental');
//     var $button = $('.toggle-button');
//     var $button_text = $('.toggle-button span');
//     var $mask = $('.content-toggle-wrapper .mask');
//     var collapsedHeight = 300;
//     if ($content.prop('scrollHeight') > collapsedHeight) {
//         $content.css({
//             'height': collapsedHeight,
//             'overflow': 'hidden'
//         }).data('expanded', false);
//     } else {
//         $button.hide();
//     }

//     $button.on('click', function() {
//         if (!$content.data('expanded')) {
//             $content.stop().animate({
//                 height: $content.prop('scrollHeight')
//             }, 400, function() {
//                 $content.css('height', 'auto');
//             });
//             $content.data('expanded', true);
//             $button_text.text('Thu gọn');
//             $button.addClass('active');
//             $mask.addClass('hidden');
//         } else {
//             $content.stop().animate({
//                 height: collapsedHeight
//             }, 400);
//             $content.data('expanded', false);
//             $button_text.text('Xem thêm');
//             $button.removeClass('active');
//             $mask.removeClass('hidden');
            
//         }
//     });
// });
// Countdown promotion
document.addEventListener("DOMContentLoaded", function () {
    const countdown = document.getElementById("countdown");
    const countdownWrapper = document.getElementById("countdown-wrapper");
    const parentcountdownWrapper = countdownWrapper.closest('.col-promotion');
    const endTimeStr = countdown.getAttribute("data-endtime");
    const endTime = new Date(endTimeStr.replace(' ', 'T'));

    function updateCountdown() {
        const now = new Date();
        const diff = endTime - now;

        if (diff <= 0) {
            // Ẩn toàn bộ countdown nếu hết hạn
            parentcountdownWrapper.style.display = "none";
            return;
        }

        const days = Math.floor(diff / (1000 * 60 * 60 * 24));
        const hours = Math.floor((diff / (1000 * 60 * 60)) % 24);
        const minutes = Math.floor((diff / (1000 * 60)) % 60);
        const seconds = Math.floor((diff / 1000) % 60);

        document.getElementById("days").textContent = String(days).padStart(2, '0');
        document.getElementById("hours").textContent = String(hours).padStart(2, '0');
        document.getElementById("minutes").textContent = String(minutes).padStart(2, '0');
        document.getElementById("seconds").textContent = String(seconds).padStart(2, '0');
    }

    updateCountdown();
    setInterval(updateCountdown, 1000);
});