<?php
/** @var array $ctx */
require_once __DIR__ . '/_partials.php';
?>
<h2 style="margin:0 0 12px;color:#111827;font-size:18px;">Cảm ơn bạn đã sử dụng dịch vụ</h2>
<p style="margin:0 0 16px;">
  Đơn <strong>#<?php echo esc_html((string) ($ctx['order_code'] ?? '')); ?></strong> đã hoàn tất.
  Vielimousine rất vui được phục vụ bạn và mong gặp lại trong lần kế tiếp.
</p>
<?php echo vie_email_render_items($ctx['items'] ?? [], false); ?>
<?php echo vie_email_render_totals($ctx, false); ?>
<p style="margin:18px 0 0;">Nếu bạn có chút thời gian, đánh giá trải nghiệm với chúng tôi sẽ rất quý giá.</p>
