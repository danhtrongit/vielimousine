jQuery(document).ready(function() {
  jQuery("#hotel_gallery-list").slick({
    slidesToShow: 1,
    slidesToScroll: 1,
    arrows: true,
    fade: true,
    asNavFor: ".hotel_gallery-nav",
    prevArrow: '<button type="button" class="slick-prev"><i class="fas fa-angle-left"></i></button>',
    nextArrow: '<button type="button" class="slick-next"><i class="fas fa-angle-right"></i></button>'
  });
  jQuery(".hotel_gallery-nav").slick({
    slidesToShow: 6,
    slidesToScroll: 1,
    asNavFor: "#hotel_gallery-list",
    dots: false,
    focusOnSelect: true,
    arrows: false,
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
//# sourceMappingURL=hotel.js.map
