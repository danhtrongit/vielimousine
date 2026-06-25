<?php
/** @var array $ctx */
require_once __DIR__ . '/_partials.php';
?>
<h2 style="margin:0 0 12px;color:#111827;font-size:18px;">Đã thu tiền — #<?php echo esc_html((string) ($ctx['order_code'] ?? '')); ?></h2>
<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:separate;border:1px solid #e5e7eb;border-radius:6px;margin:0 0 12px;">
  <tr><td style="padding:6px 12px;color:#6b7280;width:38%;">Khách hàng</td><td style="padding:6px 12px;"><?php echo esc_html((string) ($ctx['customer_name'] ?? '')); ?> — <?php echo esc_html((string) ($ctx['customer_phone'] ?? '')); ?></td></tr>
  <tr><td style="padding:6px 12px;color:#6b7280;">Số tiền vừa thu</td><td style="padding:6px 12px;font-weight:600;color:#bd3210;"><?php echo esc_html((string) ($ctx['payment_amount'] ?? '')); ?></td></tr>
  <tr><td style="padding:6px 12px;color:#6b7280;">Phương thức</td><td style="padding:6px 12px;"><?php echo esc_html((string) ($ctx['payment_method'] ?? '')); ?></td></tr>
  <tr><td style="padding:6px 12px;color:#6b7280;">Trạng thái thanh toán</td><td style="padding:6px 12px;"><?php echo esc_html((string) ($ctx['payment_status'] ?? '')); ?></td></tr>
  <tr><td style="padding:6px 12px;color:#6b7280;">Tổng đơn</td><td style="padding:6px 12px;"><?php echo esc_html((string) ($ctx['total'] ?? '')); ?></td></tr>
  <tr><td style="padding:6px 12px;color:#6b7280;">Đã thu lũy kế</td><td style="padding:6px 12px;"><?php echo esc_html((string) ($ctx['paid_amount'] ?? '')); ?></td></tr>
  <tr><td style="padding:6px 12px;color:#6b7280;">Còn lại</td><td style="padding:6px 12px;font-weight:600;"><?php echo esc_html((string) ($ctx['remaining_amount'] ?? '')); ?></td></tr>
</table>
<?php echo vie_email_render_vat($ctx); ?>
<?php if (!empty($ctx['admin_url'])): ?>
  <p style="margin:16px 0;text-align:right;">
    <a href="<?php echo esc_url((string) $ctx['admin_url']); ?>" style="display:inline-block;background:#fa541c;color:#ffffff;padding:8px 16px;border-radius:6px;text-decoration:none;font-weight:600;font-size:13px;">Xem chi tiết →</a>
  </p>
<?php endif; ?>
