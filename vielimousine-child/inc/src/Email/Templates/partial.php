<?php
/** @var array $ctx */
require_once __DIR__ . '/_partials.php';
?>
<h2 style="margin:0 0 12px;color:#fa541c;font-size:18px;">Đã nhận tiền cọc</h2>
<p style="margin:0 0 16px;">
  Vielimousine đã ghi nhận khoản cọc <strong><?php echo esc_html((string) ($ctx['paid_amount'] ?? '')); ?></strong>
  cho đơn <strong>#<?php echo esc_html((string) ($ctx['order_code'] ?? '')); ?></strong>.
  Còn lại <strong><?php echo esc_html((string) ($ctx['remaining_amount'] ?? '')); ?></strong> sẽ thanh toán khi nhận phòng (hoặc theo thỏa thuận).
</p>
<?php echo vie_email_render_items($ctx['items'] ?? [], false); ?>
<?php echo vie_email_render_totals($ctx, false); ?>
