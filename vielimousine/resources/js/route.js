jQuery(document).ready(function () {
  jQuery(".route-gallery__wrapper").slick({
    slidesToShow: 1,
    slidesToScroll: 1,
    arrows: false,
    // fade: true,
    asNavFor: ".route-gallery__dot",
  });
  jQuery(".route-gallery__dot").slick({
    slidesToShow: 5,
    slidesToScroll: 1,
    asNavFor: ".route-gallery__wrapper",
    dots: false,
    focusOnSelect: true,
    arrows: true,
    prevArrow:
      '<button type="button" class="slick-prev"><i class="fas fa-angle-left"></i></button>',
    nextArrow:
      '<button type="button" class="slick-next"><i class="fas fa-angle-right"></i></button>',
    responsive: [
      {
        breakpoint: 641,
        settings: {
          slidesToShow: 3,
          slidesToScroll: 1,
        },
      },
    ],
  });

  jQuery(".sidebar-reviews .wrapper").slick({
    infinite: true,
    autoplay: false,
    autoplaySpeed: 3000,
    slidesToShow: 1,
    slidesToScroll: 1,
    arrows: true,
    prevArrow:
      '<button type="button" class="slick-prev"><i class="fas fa-angle-left"></i></button>',
    nextArrow:
      '<button type="button" class="slick-next"><i class="fas fa-angle-right"></i></button>',
  });
});
// Js button xem them - rut gon noi dung
jQuery(document).ready(function($) {
    var $content = $('.content-route');
    var $button = $('.toggle-button');
    var $button_text = $('.toggle-button span');
    var $mask = $('.content-toggle-wrapper .mask');
    var collapsedHeight = 600;
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
