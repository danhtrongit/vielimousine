<?php
/**
 * Shortcode [vie_hotel_search]
 *
 * @var array $atts  Shortcode attributes (hotel_id).
 */
$hotelId = (int) ($atts['hotel_id'] ?? 0);
$today   = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));
?>
<div class="vie-public" data-vie-search data-hotel-id="<?php echo (int) $hotelId; ?>">
  <form class="vie-public__form">
    <fieldset class="vie-public__fieldset">
      <legend>Tìm phòng</legend>
      <div class="vie-public__row vie-public__row--3">
        <div class="vie-public__field">
          <label for="vie-search-checkin">Nhận phòng</label>
          <input id="vie-search-checkin" class="vie-public__input" type="date" name="checkin" value="<?php echo esc_attr($today); ?>" required>
        </div>
        <div class="vie-public__field">
          <label for="vie-search-checkout">Trả phòng</label>
          <input id="vie-search-checkout" class="vie-public__input" type="date" name="checkout" value="<?php echo esc_attr($tomorrow); ?>" required>
        </div>
        <div class="vie-public__field">
          <label for="vie-search-adults">Số khách</label>
          <select id="vie-search-adults" class="vie-public__select" name="adults">
            <?php for ($i = 1; $i <= 6; $i++): ?>
              <option value="<?php echo $i; ?>" <?php echo $i === 2 ? 'selected' : ''; ?>><?php echo $i; ?> người lớn</option>
            <?php endfor; ?>
          </select>
        </div>
      </div>
      <div style="margin-top: 1rem; text-align: right;">
        <button type="submit" class="vie-public__btn vie-public__btn--primary">Tìm phòng</button>
      </div>
    </fieldset>
  </form>
</div>
