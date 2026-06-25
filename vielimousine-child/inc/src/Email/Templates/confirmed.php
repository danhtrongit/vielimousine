<?php
/** @var array $ctx */
require_once __DIR__ . '/_partials.php';
?>
<h2 style="margin:0 0 12px;color:#bd3210;font-size:18px;">Đơn đặt phòng đã được xác nhận</h2>
<p style="margin:0 0 16px;">
  Đơn <strong>#<?php echo esc_html((string) ($ctx['order_code'] ?? '')); ?></strong> đã được Vielimousine xác nhận.
  Vui lòng kiểm tra thông tin chi tiết bên dưới.
</p>
<?php echo vie_email_render_items($ctx['items'] ?? [], false); ?>
<?php echo vie_email_render_pickup_dropoff($ctx); ?>
<?php echo vie_email_render_vat($ctx); ?>
<?php echo vie_email_render_totals($ctx, false); ?>
<p style="margin:18px 0 0;">Mọi thắc mắc xin liên hệ Vielimousine. Hẹn gặp bạn!</p>
