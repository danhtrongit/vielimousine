<?php
/**
 * Shortcode [vie_checkout]
 *
 * Form đặt phòng public. Prefill room_id / checkin / checkout từ ?room_id=... query.
 */
$roomId   = isset($_GET['room_id'])  ? (int) $_GET['room_id'] : 0;
$checkin  = isset($_GET['checkin'])  ? sanitize_text_field((string) $_GET['checkin'])  : date('Y-m-d');
$checkout = isset($_GET['checkout']) ? sanitize_text_field((string) $_GET['checkout']) : date('Y-m-d', strtotime('+1 day'));
?>
<div class="vie-public" data-vie-checkout>
  <h1>Đặt phòng</h1>
  <div class="vie-public__checkout-grid">
    <form class="vie-public__form" novalidate>
      <fieldset class="vie-public__fieldset">
        <legend>Thông tin chuyến đi</legend>
        <div class="vie-public__row">
          <div class="vie-public__field">
            <label for="vie-room-id">Mã phòng <span class="required">*</span></label>
            <input id="vie-room-id" class="vie-public__input" type="number" name="room_id" min="1" value="<?php echo esc_attr((string) $roomId); ?>" required>
          </div>
          <div class="vie-public__field">
            <label for="vie-booking-type">Loại đặt</label>
            <select id="vie-booking-type" class="vie-public__select" name="booking_type">
              <option value="room" selected>Phòng</option>
              <option value="combo">Combo (phòng + vé limousine)</option>
            </select>
          </div>
        </div>
        <div class="vie-public__row">
          <div class="vie-public__field">
            <label for="vie-checkin">Nhận phòng <span class="required">*</span></label>
            <input id="vie-checkin" class="vie-public__input" type="date" name="checkin" value="<?php echo esc_attr($checkin); ?>" required>
          </div>
          <div class="vie-public__field">
            <label for="vie-checkout">Trả phòng <span class="required">*</span></label>
            <input id="vie-checkout" class="vie-public__input" type="date" name="checkout" value="<?php echo esc_attr($checkout); ?>" required>
          </div>
        </div>
        <div class="vie-public__row">
          <div class="vie-public__field">
            <label for="vie-adults">Số người lớn <span class="required">*</span></label>
            <input id="vie-adults" class="vie-public__input" type="number" name="adults" min="1" max="20" value="2" required>
          </div>
          <div class="vie-public__field">
            <label for="vie-child-ages">Tuổi trẻ em (cách nhau dấu phẩy)</label>
            <input id="vie-child-ages" class="vie-public__input" type="text" name="child_ages" placeholder="VD: 3, 8">
          </div>
        </div>
      </fieldset>

      <fieldset class="vie-public__fieldset">
        <legend>Thông tin liên hệ</legend>
        <div class="vie-public__row">
          <div class="vie-public__field">
            <label for="vie-name">Họ và tên <span class="required">*</span></label>
            <input id="vie-name" class="vie-public__input" type="text" name="name" required>
          </div>
          <div class="vie-public__field">
            <label for="vie-phone">Số điện thoại <span class="required">*</span></label>
            <input id="vie-phone" class="vie-public__input" type="tel" name="phone" required>
          </div>
        </div>
        <div class="vie-public__field">
          <label for="vie-email">Email</label>
          <input id="vie-email" class="vie-public__input" type="email" name="email" placeholder="(tùy chọn, dùng để nhận xác nhận)">
        </div>
        <div class="vie-public__field">
          <label for="vie-note">Ghi chú</label>
          <textarea id="vie-note" class="vie-public__textarea" name="customer_note" placeholder="Yêu cầu đặc biệt..."></textarea>
        </div>
      </fieldset>

      <fieldset class="vie-public__fieldset">
        <legend>Mã giảm giá</legend>
        <div class="vie-public__inline-group">
          <input class="vie-public__input" type="text" name="coupon_code" placeholder="Nhập mã (nếu có)">
          <button type="button" class="vie-public__btn" data-coupon-apply>Áp dụng</button>
        </div>
        <div data-coupon-status></div>
      </fieldset>

      <div data-error class="vie-public__error-inline" style="display: none"></div>

      <div style="text-align: right;">
        <button type="submit" class="vie-public__btn vie-public__btn--primary">Đặt phòng</button>
      </div>
    </form>

    <aside class="vie-public__summary" data-vie-summary>
      <h2>Tổng quan</h2>
      <div data-summary-lines>
        <p class="vie-public__muted">Nhập đủ thông tin chuyến để xem giá.</p>
      </div>
      <div class="vie-public__total-line">
        <span>Tổng cộng</span>
        <strong data-summary-total>—</strong>
      </div>
      <div data-summary-messages></div>
    </aside>
  </div>
</div>
