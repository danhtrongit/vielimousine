jQuery(document).ready(function() {
  jQuery(".gallery-wrapper").each(function() {
    const $wrapper = jQuery(this);
    const forSlider = $wrapper.find(".slider-for");
    const navSlider = $wrapper.find(".slider-nav");
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
document.addEventListener("DOMContentLoaded", function() {
  const countdown = document.getElementById("countdown");
  const countdownWrapper = document.getElementById("countdown-wrapper");
  const parentcountdownWrapper = countdownWrapper.closest(".col-promotion");
  const endTimeStr = countdown.getAttribute("data-endtime");
  const endTime = new Date(endTimeStr.replace(" ", "T"));
  function updateCountdown() {
    const now = /* @__PURE__ */ new Date();
    const diff = endTime - now;
    if (diff <= 0) {
      parentcountdownWrapper.style.display = "none";
      return;
    }
    const days = Math.floor(diff / (1e3 * 60 * 60 * 24));
    const hours = Math.floor(diff / (1e3 * 60 * 60) % 24);
    const minutes = Math.floor(diff / (1e3 * 60) % 60);
    const seconds = Math.floor(diff / 1e3 % 60);
    document.getElementById("days").textContent = String(days).padStart(2, "0");
    document.getElementById("hours").textContent = String(hours).padStart(2, "0");
    document.getElementById("minutes").textContent = String(minutes).padStart(2, "0");
    document.getElementById("seconds").textContent = String(seconds).padStart(2, "0");
  }
  updateCountdown();
  setInterval(updateCountdown, 1e3);
});
//# sourceMappingURL=rental.js.map
