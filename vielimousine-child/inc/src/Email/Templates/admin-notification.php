<?php
/** @var array $ctx */
require_once __DIR__ . '/_partials.php';
?>
<h2 style="margin:0 0 12px;color:#111827;font-size:18px;">Đơn đặt phòng mới</h2>
<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:separate;border:1px solid #e5e7eb;border-radius:6px;margin:0 0 12px;">
  <tr><td style="padding:6px 12px;color:#6b7280;width:38%;">Mã đơn</td><td style="padding:6px 12px;"><strong>#<?php echo esc_html((string) ($ctx['order_code'] ?? '')); ?></strong></td></tr>
  <tr><td style="padding:6px 12px;color:#6b7280;">Khách hàng</td><td style="padding:6px 12px;">
    <?php echo esc_html((string) ($ctx['customer_name']  ?? '')); ?>
    <?php if (!empty($ctx['customer_phone'])): ?> — <?php echo esc_html((string) $ctx['customer_phone']); ?><?php endif; ?>
    <?php if (!empty($ctx['customer_email'])): ?> — <?php echo esc_html((string) $ctx['customer_email']); ?><?php endif; ?>
  </td></tr>
  <tr><td style="padding:6px 12px;color:#6b7280;">Nguồn</td><td style="padding:6px 12px;"><?php echo esc_html((string) ($ctx['source'] ?? '')); ?></td></tr>
  <tr><td style="padding:6px 12px;color:#6b7280;">Sales</td><td style="padding:6px 12px;"><?php echo esc_html((string) ($ctx['sales_user'] ?? '—')); ?></td></tr>
  <tr><td style="padding:6px 12px;color:#6b7280;">Tổng số vé xe</td><td style="padding:6px 12px;"><strong><?php echo (int) ($ctx['total_seats'] ?? 0); ?></strong> vé<?php $tf = (int) ($ctx['total_free_seats'] ?? 0); echo $tf > 0 ? " (miễn {$tf} bé)" : ''; ?></td></tr>
</table>

<?php echo vie_email_render_items($ctx['items'] ?? [], true); ?>
<?php echo vie_email_render_pickup_dropoff($ctx); ?>
<?php echo vie_email_render_vat($ctx); ?>
<?php echo vie_email_render_totals($ctx, true); ?>

<?php if (!empty($ctx['customer_note'])): ?>
  <p style="margin:8px 0;color:#6b7280;"><strong>Ghi chú khách:</strong> <?php echo esc_html((string) $ctx['customer_note']); ?></p>
<?php endif; ?>
<?php if (!empty($ctx['order_description'])): ?>
  <p style="margin:8px 0;color:#6b7280;"><strong>Mô tả đơn:</strong> <code style="font-family:Consolas,monospace;background:#f3f4f6;padding:2px 6px;border-radius:3px;"><?php echo esc_html((string) $ctx['order_description']); ?></code></p>
<?php endif; ?>
<?php if (!empty($ctx['admin_url'])): ?>
  <p style="margin:16px 0;text-align:right;">
    <a href="<?php echo esc_url((string) $ctx['admin_url']); ?>" style="display:inline-block;background:#00A651;color:#ffffff;padding:8px 16px;border-radius:6px;text-decoration:none;font-weight:600;font-size:13px;">Xem trong admin →</a>
  </p>
<?php endif; ?>
