<?php
/** @var array $ctx */
require_once __DIR__ . '/_partials.php';
$refundAmount = (string) ($ctx['refund_amount'] ?? '');
$penaltyAmount = (string) ($ctx['penalty_amount'] ?? '');
?>
<h2 style="margin:0 0 12px;color:#b91c1c;font-size:18px;">Đơn đã được hủy</h2>
<p style="margin:0 0 16px;">
  Đơn <strong>#<?php echo esc_html((string) ($ctx['order_code'] ?? '')); ?></strong> đã được hủy.
</p>
<?php if ($refundAmount !== '' || $penaltyAmount !== ''): ?>
<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background:#fef2f2;border:1px solid #fecaca;border-radius:6px;border-collapse:separate;margin:0 0 16px;">
  <?php if ($penaltyAmount !== ''): ?>
    <tr><td style="padding:8px 12px;color:#6b7280;">Phí hủy</td><td style="padding:8px 12px;text-align:right;"><?php echo esc_html($penaltyAmount); ?></td></tr>
  <?php endif; ?>
  <?php if ($refundAmount !== ''): ?>
    <tr><td style="padding:8px 12px;color:#6b7280;">Số tiền hoàn lại</td><td style="padding:8px 12px;text-align:right;font-weight:600;color:#007A3D;"><?php echo esc_html($refundAmount); ?></td></tr>
  <?php endif; ?>
</table>
<?php endif; ?>
<?php echo vie_email_render_items($ctx['items'] ?? [], false); ?>
<p style="margin:18px 0 0;color:#6b7280;font-size:13px;">Thông tin hoàn tiền (nếu có) sẽ được xử lý trong 3–5 ngày làm việc.</p>
