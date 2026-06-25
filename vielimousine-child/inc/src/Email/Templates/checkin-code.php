<?php
/** @var array $ctx */
require_once __DIR__ . '/_partials.php';
?>
<h2 style="margin:0 0 12px;color:#bd3210;font-size:18px;">Mã nhận phòng của bạn</h2>
<p style="margin:0 0 16px;">
  Cảm ơn quý khách đã thanh toán đơn <strong>#<?php echo esc_html((string) ($ctx['order_code'] ?? '')); ?></strong>.
  Khách sạn đã cấp mã nhận phòng để quý khách sử dụng khi check-in.
</p>
<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin:18px 0;">
  <tr>
    <td align="center" style="background:#ecfdf5;border:1px dashed #10b981;border-radius:8px;padding:18px 24px;">
      <div style="font-size:12px;color:#962812;letter-spacing:0.5px;text-transform:uppercase;margin-bottom:6px;">Mã nhận phòng</div>
      <div style="font-size:24px;font-weight:700;color:#bd3210;letter-spacing:2px;font-family:Consolas,Menlo,monospace;">
        <?php echo esc_html((string) ($ctx['checkin_code'] ?? '')); ?>
      </div>
    </td>
  </tr>
</table>
<p style="margin:0 0 8px;"><strong>Lịch trình của quý khách:</strong></p>
<ul style="margin:0 0 16px;padding-left:20px;">
  <li>Check-in: <strong><?php echo esc_html((string) ($ctx['checkin'] ?? '')); ?></strong></li>
  <li>Check-out: <strong><?php echo esc_html((string) ($ctx['checkout'] ?? '')); ?></strong></li>
  <li>Số đêm: <strong><?php echo esc_html((string) ($ctx['nights'] ?? '')); ?></strong></li>
</ul>
<p style="margin:0 0 8px;">Vui lòng xuất trình mã này (cùng CMND/CCCD) tại quầy lễ tân khi đến nhận phòng.</p>
<p style="margin:18px 0 0;">Chúc quý khách có kỳ nghỉ tuyệt vời cùng Vielimousine!</p>
