jQuery(document).ready(function () {
    jQuery('.gallery-wrapper.gallery-pc').each(function() {
        const $wrapper = jQuery(this);
        const forSlider = $wrapper.find('.slider-for');
        const navSlider = $wrapper.find('.slider-nav');
        forSlider.slick({
            infinite: false,
            slidesToShow: 1,
            slidesToScroll: 1,
            arrows: false,
            fade: true,
            asNavFor: navSlider
        });
        navSlider.slick({
            slidesToShow: 4,
            slidesToScroll: 1,
            asNavFor: forSlider,
            dots: false,
            arrows: false,
            infinite: false,
            accessibility: false,
            focusOnSelect: true,
            swipe: false,
            touchMove: false,
            variableWidth: false,
            centerMode: false,
            responsive: [
                {
                breakpoint: 768,
                    settings: {
                        slidesToShow: 3
                    }
                },
            ]
        });
    });
});