<?php
/**
 * Hoá đơn bán hàng (VAT) — A4 portrait
 *
 * Provided variables: $order, $items, $cfg, $money, $amountInWords, $esc
 *
 * @var array $order
 * @var array $items
 * @var array $cfg
 * @var callable $money
 * @var callable $amountInWords
 * @var callable $esc
 */

defined('ABSPATH') || die;

$cancelled = ($order['status'] ?? '') === 'cancelled';
$issueDate = wp_date('d/m/Y', strtotime((string) ($order['created_at'] ?? 'now')) ?: time());

$total      = (int) ($order['total'] ?? 0);
$discount   = (int) ($order['discount'] ?? 0);
$paid       = (int) ($order['paid_amount'] ?? 0);
$vatRate    = (float) ($cfg['vat_rate'] ?? 0);
// Trên total đã bao gồm VAT (tax field) → tách ngược.
$taxInTotal = (int) ($order['tax'] ?? 0);
if ($taxInTotal === 0 && $vatRate > 0) {
    // Nếu order chưa có tax field, tính tax từ subtotal − discount × vat_rate.
    $base       = max(0, (int) ($order['subtotal'] ?? 0) - $discount);
    $taxInTotal = (int) round($base * $vatRate / 100);
}
$preTax = max(0, $total - $taxInTotal);
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Hoá đơn bán hàng <?= $esc($order['invoice_number'] ?? '') ?></title>
<style>
  @page { margin: 15mm 14mm; }
  * { box-sizing: border-box; }
  body { font-family: "DejaVu Sans", sans-serif; font-size: 10.5pt; color: #111; line-height: 1.45; margin: 0; }
  .topbar { display: table; width: 100%; padding-bottom: 6px; border-bottom: 2px solid #111; margin-bottom: 14px; }
  .topbar .l { display: table-cell; width: 60%; vertical-align: top; }
  .topbar .r { display: table-cell; width: 40%; vertical-align: top; text-align: right; }
  .company-name { font-size: 14pt; font-weight: bold; text-transform: uppercase; margin: 0 0 3px; }
  .meta-line { font-size: 9.5pt; color: #4b5563; margin: 1px 0; }
  .doc-title { text-align: center; font-size: 17pt; font-weight: bold; text-transform: uppercase; margin: 10px 0 2px; letter-spacing: 1.5px; }
  .doc-sub { text-align: center; font-size: 10pt; color: #4b5563; margin-bottom: 14px; }
  .doc-num { font-weight: bold; color: #b91c1c; }
  .customer { padding: 8px 10px; background: #f9fafb; border-left: 3px solid #2563eb; margin: 10px 0 12px; font-size: 10.5pt; }
  .customer p { margin: 2px 0; }
  .customer strong { display: inline-block; min-width: 130px; }
  table.items { width: 100%; border-collapse: collapse; }
  table.items th, table.items td { border: 1px solid #cbd5e1; padding: 6px 8px; font-size: 9.5pt; vertical-align: top; }
  table.items th { background: #e5e7eb; text-align: center; font-weight: bold; }
  table.items td.num { text-align: right; }
  table.items td.center { text-align: center; }
  .totals { width: 55%; float: right; margin-top: 8px; }
  .totals td { padding: 4px 8px; font-size: 10.5pt; }
  .totals td.lbl { text-align: right; color: #374151; }
  .totals td.val { text-align: right; font-weight: bold; min-width: 100px; }
  .totals tr.grand td { border-top: 1.5px solid #111; font-size: 12pt; padding-top: 7px; }
  .totals tr.grand td.val { color: #b91c1c; }
  .clear { clear: both; }
  .words-box { margin-top: 14px; padding: 8px 10px; background: #fffbeb; border-left: 3px solid #f59e0b; font-size: 10.5pt; font-style: italic; }
  .words-box strong { font-style: normal; }
  .bank-box { margin-top: 12px; padding: 8px 10px; background: #f0f9ff; border-left: 3px solid #0284c7; font-size: 10pt; }
  .bank-box p { margin: 2px 0; }
  .footer-note { margin-top: 10px; font-size: 9.5pt; color: #4b5563; font-style: italic; text-align: center; }
  .signatures { display: table; width: 100%; margin-top: 28px; }
  .sig { display: table-cell; width: 33.33%; text-align: center; font-size: 10pt; padding: 0 8px; vertical-align: top; }
  .sig .role { font-weight: bold; text-transform: uppercase; }
  .sig .hint { color: #6b7280; font-style: italic; font-size: 9pt; }
  .sig .sign-space { height: 70px; }
  .cancelled-watermark { position: fixed; top: 38%; left: 0; width: 100%; text-align: center; font-size: 80pt; color: rgba(220, 38, 38, 0.15); transform: rotate(-22deg); font-weight: bold; letter-spacing: 12px; }
</style>
</head>
<body>

<?php if ($cancelled): ?>
  <div class="cancelled-watermark">ĐÃ HUỶ</div>
<?php endif; ?>

<div class="topbar">
  <div class="l">
    <p class="company-name"><?= $esc($cfg['company_name']) ?: 'Công ty' ?></p>
    <?php if ($cfg['company_tax_id']): ?><p class="meta-line"><strong>MST:</strong> <?= $esc($cfg['company_tax_id']) ?></p><?php endif; ?>
    <?php if ($cfg['company_address']): ?><p class="meta-line"><strong>Địa chỉ:</strong> <?= $esc($cfg['company_address']) ?></p><?php endif; ?>
    <?php if ($cfg['company_phone'] || $cfg['company_email']): ?>
      <p class="meta-line">
        <?php if ($cfg['company_phone']): ?><strong>ĐT:</strong> <?= $esc($cfg['company_phone']) ?><?php endif; ?>
        <?php if ($cfg['company_phone'] && $cfg['company_email']): ?> &nbsp;•&nbsp; <?php endif; ?>
        <?php if ($cfg['company_email']): ?><strong>Email:</strong> <?= $esc($cfg['company_email']) ?><?php endif; ?>
      </p>
    <?php endif; ?>
  </div>
  <div class="r">
    <p class="meta-line">Mẫu số: 01GTKT</p>
    <p class="meta-line">Ký hiệu: <?= $esc(strtoupper($cfg['invoice_prefix'] ?: 'HD')) ?>/<?= (int) wp_date('y') ?></p>
    <p class="meta-line">Số: <span class="doc-num"><?= $esc($order['invoice_number']) ?></span></p>
  </div>
</div>

<p class="doc-title">Hoá đơn bán hàng</p>
<p class="doc-sub">Ngày <?= (int) wp_date('d', strtotime((string) $order['created_at']) ?: time()) ?>
   tháng <?= (int) wp_date('m', strtotime((string) $order['created_at']) ?: time()) ?>
   năm <?= (int) wp_date('Y', strtotime((string) $order['created_at']) ?: time()) ?></p>

<div class="customer">
  <p><strong>Họ tên người mua hàng:</strong> <?= $esc($order['customer_name'] ?? '') ?></p>
  <p><strong>Điện thoại:</strong> <?= $esc($order['customer_phone'] ?? '') ?>
    <?php if (!empty($order['customer_email'])): ?>
      &nbsp;•&nbsp; <strong>Email:</strong> <?= $esc($order['customer_email']) ?>
    <?php endif; ?>
  </p>
  <?php
  $cvat = $order['customer_vat'] ?? null;
  if (is_string($cvat) && $cvat !== '') {
      $decoded = json_decode($cvat, true);
      $cvat = is_array($decoded) ? $decoded : ['tax_code' => $cvat];
  }
  if (is_array($cvat) && array_filter($cvat)):
  ?>
    <?php if (!empty($cvat['company_name'])): ?>
      <p><strong>Đơn vị mua hàng:</strong> <?= $esc((string) $cvat['company_name']) ?></p>
    <?php endif; ?>
    <?php if (!empty($cvat['tax_code'])): ?>
      <p><strong>MST khách hàng:</strong> <?= $esc((string) $cvat['tax_code']) ?></p>
    <?php endif; ?>
    <?php if (!empty($cvat['address'])): ?>
      <p><strong>Địa chỉ xuất hoá đơn:</strong> <?= $esc((string) $cvat['address']) ?></p>
    <?php endif; ?>
  <?php endif; ?>
  <p><strong>Hình thức thanh toán:</strong> Chuyển khoản / Tiền mặt</p>
</div>

<table class="items">
  <thead>
    <tr>
      <th style="width:34px;">STT</th>
      <th>Tên hàng hoá, dịch vụ</th>
      <th style="width:50px;">ĐVT</th>
      <th style="width:46px;">SL</th>
      <th style="width:90px;">Đơn giá</th>
      <th style="width:60px;">Thuế suất</th>
      <th style="width:100px;">Thành tiền</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($items as $idx => $it):
      $bookingType = (string) ($it['booking_type'] ?? '');
      $typeLabel   = $bookingType === 'combo' ? 'Combo phòng + vé xe' : 'Chỉ phòng';
      $lineTotal   = (int) ($it['line_total'] ?? 0);
      $qty         = (int) ($it['quantity'] ?? 1);
      $unitPrice   = $qty > 0 ? (int) round($lineTotal / $qty) : $lineTotal;
      ?>
      <tr>
        <td class="center"><?= $idx + 1 ?></td>
        <td>
          <strong><?= $esc($it['hotel_name'] ?? $it['name']) ?></strong> — <?= $esc($it['name']) ?><br>
          <span style="color:#6b7280; font-size:9pt;">
            <?= $esc($typeLabel) ?> · <?= (int) $it['nights'] ?> đêm ·
            <?= $esc(wp_date('d/m/Y', strtotime((string) $it['checkin']) ?: time())) ?>
            → <?= $esc(wp_date('d/m/Y', strtotime((string) $it['checkout']) ?: time())) ?>
          </span>
        </td>
        <td class="center">Đêm</td>
        <td class="center"><?= $qty ?></td>
        <td class="num"><?= $money($unitPrice) ?></td>
        <td class="center"><?= rtrim(rtrim(number_format($vatRate, 2, ',', ''), '0'), ',') ?>%</td>
        <td class="num"><?= $money($lineTotal) ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<table class="totals">
  <tr><td class="lbl">Cộng tiền hàng:</td><td class="val"><?= $money($preTax) ?></td></tr>
  <?php if ($discount > 0): ?>
    <tr><td class="lbl">Giảm giá:</td><td class="val">−<?= $money($discount) ?></td></tr>
  <?php endif; ?>
  <tr><td class="lbl">Thuế GTGT (<?= rtrim(rtrim(number_format($vatRate, 2, ',', ''), '0'), ',') ?>%):</td><td class="val"><?= $money($taxInTotal) ?></td></tr>
  <tr class="grand"><td class="lbl">TỔNG CỘNG THANH TOÁN:</td><td class="val"><?= $money($total) ?></td></tr>
</table>
<div class="clear"></div>

<div class="words-box">
  <strong>Bằng chữ:</strong> <?= $esc($amountInWords($total)) ?>
</div>

<?php if ($cfg['bank_name'] || $cfg['bank_account']): ?>
  <div class="bank-box">
    <p><strong>Thông tin chuyển khoản:</strong></p>
    <?php if ($cfg['bank_name']): ?><p>Ngân hàng: <?= $esc($cfg['bank_name']) ?></p><?php endif; ?>
    <?php if ($cfg['bank_account']): ?><p>Số tài khoản: <?= $esc($cfg['bank_account']) ?></p><?php endif; ?>
    <?php if ($cfg['bank_holder']): ?><p>Chủ tài khoản: <?= $esc($cfg['bank_holder']) ?></p><?php endif; ?>
    <p style="margin-top:4px;">Nội dung CK: <strong><?= $esc($order['code']) ?></strong></p>
  </div>
<?php endif; ?>

<?php if ($cfg['footer_note']): ?>
  <p class="footer-note"><?= $esc($cfg['footer_note']) ?></p>
<?php endif; ?>

<div class="signatures">
  <div class="sig">
    <p class="role">Người mua hàng</p>
    <p class="hint">(Ký, ghi rõ họ tên)</p>
    <div class="sign-space"></div>
  </div>
  <div class="sig">
    <p class="role">Người bán hàng</p>
    <p class="hint">(Ký, ghi rõ họ tên)</p>
    <div class="sign-space"></div>
  </div>
  <div class="sig">
    <p class="role">Thủ trưởng đơn vị</p>
    <p class="hint">(Ký, đóng dấu)</p>
    <div class="sign-space"></div>
  </div>
</div>

</body>
</html>
