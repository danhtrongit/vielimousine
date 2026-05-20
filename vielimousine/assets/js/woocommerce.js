jQuery(function($) {
  const wpg__image = $(".wpg__image");
  wpg__image.find("a").on("click", function(e) {
    e.preventDefault();
    $(this).next(".image-popup").trigger("click");
  });
  const wpg__thumb = $(".wpg__thumb");
  wpg__thumb.find("a").on("click", function(e) {
    e.preventDefault();
  });
});
jQuery(document).ready(function() {
  jQuery(".quantity").on("click", ".plus", function(e) {
    var inputQty = jQuery(this).prev("input.qty");
    var val = parseInt(inputQty.val());
    inputQty.val(val + 1).change();
  });
  jQuery(".quantity").on("click", ".minus", function(e) {
    var inputQty = jQuery(this).next("input.qty");
    var val = parseInt(inputQty.val());
    if (val > 0) {
      inputQty.val(val - 1).change();
    }
  });
});
jQuery(document).ajaxComplete(function() {
  jQuery(".quantity").off("click", ".plus").on("click", ".plus", function(e) {
    var inputQty = jQuery(this).prev("input.qty");
    var val = parseInt(inputQty.val());
    inputQty.val(val + 1).change();
  });
  jQuery(".quantity").off("click", ".minus").on(
    "click",
    ".minus",
    function(e) {
      var inputQty = jQuery(this).next("input.qty");
      var val = parseInt(inputQty.val());
      if (val > 1) {
        inputQty.val(val - 1).change();
      }
    }
  );
});
//# sourceMappingURL=woocommerce.js.map
