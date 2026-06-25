<?php
/** @var array $ctx */
require_once __DIR__ . '/_partials.php';
?>
<h2 style="margin:0 0 12px;color:#111827;font-size:18px;">Cảm ơn <?php echo esc_html((string) ($ctx['customer_name'] ?? 'Quý khách')); ?> đã đặt phòng</h2>
<p style="margin:0 0 16px;">Đơn đặt phòng <strong>#<?php echo esc_html((string) ($ctx['order_code'] ?? '')); ?></strong> đang ở trạng thái <strong>Chờ thanh toán</strong>.</p>
<?php echo vie_email_render_items($ctx['items'] ?? [], false); ?>
<?php echo vie_email_render_hotel_info($ctx); ?>
<?php echo vie_email_render_pickup_dropoff($ctx); ?>
<?php echo vie_email_render_vat($ctx); ?>
<?php echo vie_email_render_totals($ctx, false); ?>
<?php if (!empty($ctx['lookup_url'])): ?>
  <p style="margin:16px 0;text-align:center;">
    <a href="<?php echo esc_url((string) $ctx['lookup_url']); ?>" style="display:inline-block;background:#00A651;color:#ffffff;padding:10px 20px;border-radius:6px;text-decoration:none;font-weight:600;">Xem chi tiết &amp; thanh toán</a>
  </p>
<?php endif; ?>
<?php echo vie_email_render_policies($ctx); ?>
<p style="margin:18px 0 0;color:#6b7280;font-size:13px;">Vui lòng hoàn tất thanh toán để xác nhận đơn. Nếu bạn đã thanh toán, có thể bỏ qua email này.</p>
