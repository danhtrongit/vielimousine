<?php
/**
 * Phiếu thu (Receipt) — A5 portrait
 *
 * Provided variables: $order, $items, $cfg, $money, $amountInWords, $esc
 *
 * @var array $order
 * @var array $items
 * @var array $cfg
 * @var callable $money
 * @var callable $esc
 */

defined('ABSPATH') || die;

$cancelled = ($order['status'] ?? '') === 'cancelled';
$paid      = (int) ($order['paid_amount'] ?? 0);
$total     = (int) ($order['total'] ?? 0);
$remaining = max(0, $total - $paid);
$issueDate = wp_date('d/m/Y', strtotime((string) ($order['created_at'] ?? 'now')) ?: time());
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Phiếu thu <?= $esc($order['invoice_number'] ?? '') ?></title>
<style>
  @page { margin: 12mm 10mm; }
  * { box-sizing: border-box; }
  body { font-family: "DejaVu Sans", sans-serif; font-size: 10.5pt; color: #1f2937; line-height: 1.45; margin: 0; }
  .header { text-align: center; padding-bottom: 8px; border-bottom: 2px solid #111; margin-bottom: 12px; }
  .company-name { font-size: 14pt; font-weight: bold; text-transform: uppercase; margin: 0 0 2px; }
  .company-meta { font-size: 9pt; color: #4b5563; margin: 1px 0; }
  .doc-title { text-align: center; font-size: 16pt; font-weight: bold; text-transform: uppercase; margin: 8px 0 4px; letter-spacing: 1px; }
  .doc-sub { text-align: center; font-size: 9.5pt; color: #6b7280; margin-bottom: 12px; }
  .info-row { display: table; width: 100%; margin: 10px 0; }
  .info-cell { display: table-cell; width: 50%; font-size: 9.5pt; vertical-align: top; }
  .info-cell strong { display: inline-block; min-width: 90px; color: #111; }
  table.items { width: 100%; border-collapse: collapse; margin: 8px 0; }
  table.items th, table.items td { border: 1px solid #cbd5e1; padding: 5px 7px; font-size: 9.5pt; }
  table.items th { background: #f3f4f6; text-align: left; font-weight: bold; }
  table.items td.num { text-align: right; }
  table.items td.center { text-align: center; }
  .totals { width: 100%; margin-top: 8px; }
  .totals td { padding: 3px 6px; font-size: 10pt; }
  .totals td.lbl { text-align: right; color: #4b5563; }
  .totals td.val { text-align: right; width: 32%; font-weight: bold; }
  .totals tr.grand td { border-top: 1.5px solid #111; font-size: 12pt; padding-top: 6px; }
  .totals tr.grand td.val { color: #b91c1c; }
  .footer-note { margin-top: 10px; font-size: 9pt; color: #4b5563; font-style: italic; }
  .signatures { display: table; width: 100%; margin-top: 24px; }
  .sig { display: table-cell; width: 50%; text-align: center; font-size: 9.5pt; padding: 0 10px; vertical-align: top; }
  .sig .role { font-weight: bold; }
  .sig .hint { color: #6b7280; font-style: italic; font-size: 8.5pt; }
  .sig .sign-space { height: 50px; }
  .cancelled-watermark { position: fixed; top: 40%; left: 0; width: 100%; text-align: center; font-size: 56pt; color: rgba(220, 38, 38, 0.18); transform: rotate(-22deg); font-weight: bold; letter-spacing: 8px; }
</style>
</head>
<body>

<?php if ($cancelled): ?>
  <div class="cancelled-watermark">ĐÃ HUỶ</div>
<?php endif; ?>

<div class="header">
  <p class="company-name"><?= $esc($cfg['company_name']) ?: 'Công ty' ?></p>
  <?php if ($cfg['company_address']): ?><p class="company-meta"><?= $esc($cfg['company_address']) ?></p><?php endif; ?>
  <?php if ($cfg['company_phone'] || $cfg['company_email']): ?>
    <p class="company-meta">
      <?php if ($cfg['company_phone']): ?>ĐT: <?= $esc($cfg['company_phone']) ?><?php endif; ?>
      <?php if ($cfg['company_phone'] && $cfg['company_email']): ?> &nbsp;•&nbsp; <?php endif; ?>
      <?php if ($cfg['company_email']): ?>Email: <?= $esc($cfg['company_email']) ?><?php endif; ?>
    </p>
  <?php endif; ?>
</div>

<p class="doc-title">Phiếu thu</p>
<p class="doc-sub">Số: <?= $esc($order['invoice_number']) ?> &nbsp;•&nbsp; Ngày <?= $esc($issueDate) ?></p>

<div class="info-row">
  <div class="info-cell">
    <p><strong>Mã đơn:</strong> <?= $esc($order['code'] ?? '') ?></p>
    <p><strong>Khách hàng:</strong> <?= $esc($order['customer_name'] ?? '') ?></p>
    <p><strong>Điện thoại:</strong> <?= $esc($order['customer_phone'] ?? '') ?></p>
    <?php if (!empty($order['customer_email'])): ?>
      <p><strong>Email:</strong> <?= $esc($order['customer_email']) ?></p>
    <?php endif; ?>
  </div>
  <div class="info-cell">
    <p><strong>Nhận phòng:</strong> <?= $esc(wp_date('d/m/Y', strtotime((string) $order['checkin']) ?: time())) ?></p>
    <p><strong>Trả phòng:</strong> <?= $esc(wp_date('d/m/Y', strtotime((string) $order['checkout']) ?: time())) ?></p>
    <p><strong>Số đêm:</strong> <?= (int) ($order['nights'] ?? 0) ?> đêm</p>
    <p><strong>Số khách:</strong> <?= (int) ($order['adults'] ?? 0) ?> NL<?= ((int) ($order['children'] ?? 0) > 0) ? ', ' . (int) $order['children'] . ' trẻ' : '' ?></p>
  </div>
</div>

<table class="items">
  <thead>
    <tr>
      <th style="width:30px;" class="center">#</th>
      <th>Hạng mục</th>
      <th style="width:50px;" class="center">SL</th>
      <th style="width:80px;" class="num">Thành tiền</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($items as $idx => $it):
      $bookingType = (string) ($it['booking_type'] ?? '');
      $typeLabel   = $bookingType === 'combo' ? 'Combo phòng + vé xe' : 'Chỉ phòng';
      ?>
      <tr>
        <td class="center"><?= $idx + 1 ?></td>
        <td>
          <strong><?= $esc($it['hotel_name'] ?? $it['name']) ?></strong><br>
          <span style="font-size:9pt; color:#4b5563;">
            <?= $esc($it['name']) ?> · <?= $esc($typeLabel) ?> · <?= (int) $it['nights'] ?> đêm
          </span>
        </td>
        <td class="center"><?= (int) ($it['quantity'] ?? 1) ?></td>
        <td class="num"><?= $money((int) ($it['line_total'] ?? 0)) ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<table class="totals">
  <tr><td class="lbl">Tạm tính:</td><td class="val"><?= $money((int) ($order['subtotal'] ?? 0)) ?></td></tr>
  <?php if ((int) ($order['discount'] ?? 0) > 0): ?>
    <tr><td class="lbl">Giảm giá:</td><td class="val">−<?= $money((int) $order['discount']) ?></td></tr>
  <?php endif; ?>
  <?php if ((int) ($order['tax'] ?? 0) > 0): ?>
    <tr><td class="lbl">Thuế:</td><td class="val"><?= $money((int) $order['tax']) ?></td></tr>
  <?php endif; ?>
  <tr class="grand"><td class="lbl">TỔNG CỘNG:</td><td class="val"><?= $money($total) ?></td></tr>
  <tr><td class="lbl">Đã thanh toán:</td><td class="val" style="color:#16a34a;"><?= $money($paid) ?></td></tr>
  <?php if ($remaining > 0): ?>
    <tr><td class="lbl">Còn lại:</td><td class="val" style="color:#dc2626;"><?= $money($remaining) ?></td></tr>
  <?php endif; ?>
</table>

<?php if ($cfg['footer_note']): ?>
  <p class="footer-note"><?= $esc($cfg['footer_note']) ?></p>
<?php endif; ?>

<div class="signatures">
  <div class="sig">
    <p class="role">Người nộp tiền</p>
    <p class="hint">(Ký, ghi rõ họ tên)</p>
    <div class="sign-space"></div>
  </div>
  <div class="sig">
    <p class="role">Người lập phiếu</p>
    <p class="hint">(Ký, ghi rõ họ tên)</p>
    <div class="sign-space"></div>
  </div>
</div>

</body>
</html>
