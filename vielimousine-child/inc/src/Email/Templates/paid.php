<?php
/** @var array $ctx */
require_once __DIR__ . '/_partials.php';
?>
<h2 style="margin:0 0 12px;color:#007A3D;font-size:18px;">Đã nhận đủ thanh toán</h2>
<p style="margin:0 0 16px;">
  Vielimousine đã nhận được khoản thanh toán <strong><?php echo esc_html((string) ($ctx['paid_amount'] ?? '')); ?></strong>
  cho đơn <strong>#<?php echo esc_html((string) ($ctx['order_code'] ?? '')); ?></strong>.
</p>
<?php echo vie_email_render_items($ctx['items'] ?? [], false); ?>
<?php echo vie_email_render_hotel_info($ctx); ?>
<?php echo vie_email_render_pickup_dropoff($ctx); ?>
<?php echo vie_email_render_vat($ctx); ?>
<?php echo vie_email_render_totals($ctx, false); ?>
<?php echo vie_email_render_policies($ctx); ?>
<p style="margin:18px 0 0;">Chúng tôi mong được phục vụ bạn tại Vielimousine. Hẹn gặp bạn ngày <?php echo esc_html((string) ($ctx['checkin'] ?? '')); ?>!</p>
