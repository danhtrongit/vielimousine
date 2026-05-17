<?php
/**
 * Shortcode [vie_order_success] — Vue 3 SuccessApp mount point.
 *
 * Vue reads ?code=X&phone=Y → GET /orders/lookup → renders + polls.
 * Falls back to legacy vie-public.js renderer if Vue dist not built.
 */
?>
<div class="vh-page" data-vie-public-success>
  <noscript>
    <p>Vui lòng bật JavaScript để xem thông tin đơn hàng.</p>
  </noscript>
</div>

<!-- Legacy fallback (vie-public.js) when Vue isn't loaded -->
<div class="vie-public" data-vie-success style="display:none">
  <h1>Cảm ơn bạn đã đặt phòng</h1>
  <div data-vie-success-body>
    <p class="vie-public__loading">Đang tải thông tin đơn hàng...</p>
  </div>
</div>
<script>
// Show legacy fallback only if Vue mount hasn't started after 800ms.
setTimeout(function () {
  var vueMount = document.querySelector('[data-vie-public-success]');
  var legacy = document.querySelector('[data-vie-success]');
  if (!vueMount || !vueMount.firstElementChild || vueMount.firstElementChild.tagName === 'NOSCRIPT') {
    if (legacy) legacy.style.display = '';
  }
}, 800);
</script>
