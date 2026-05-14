<?php
/**
 * Email layout wrapper — inline CSS cho mail client.
 *
 * @var string $body  Inner HTML đã render từ template con
 * @var array  $ctx
 */
$siteName = esc_html((string) ($ctx['site_name'] ?? 'Vielimousine'));
$siteUrl  = esc_url((string) ($ctx['site_url'] ?? home_url('/')));
$logoUrl  = esc_url((string) ($ctx['logo_url'] ?? ''));
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title><?php echo $siteName; ?></title>
</head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:Tahoma,Arial,sans-serif;color:#1f2937;">
<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background:#f3f4f6;padding:24px 0;">
  <tr>
    <td align="center">
      <table role="presentation" cellpadding="0" cellspacing="0" width="600" style="max-width:600px;width:100%;background:#ffffff;border-radius:8px;overflow:hidden;border:1px solid #e5e7eb;">
        <tr>
          <td style="background:#1d4ed8;padding:18px 24px;text-align:left;">
            <?php if ($logoUrl !== ''): ?>
              <img src="<?php echo $logoUrl; ?>" alt="<?php echo $siteName; ?>" height="36" style="display:block;height:36px;width:auto;border:0;">
            <?php else: ?>
              <div style="color:#ffffff;font-size:20px;font-weight:600;letter-spacing:0.5px;"><?php echo $siteName; ?></div>
            <?php endif; ?>
          </td>
        </tr>
        <tr>
          <td style="padding:24px;font-size:14px;line-height:1.6;color:#1f2937;">
            <?php echo $body; // nội dung template đã sanitize/escape phía con ?>
          </td>
        </tr>
        <tr>
          <td style="background:#f9fafb;padding:16px 24px;border-top:1px solid #e5e7eb;font-size:12px;color:#6b7280;text-align:center;">
            <?php echo $siteName; ?> · <a href="<?php echo $siteUrl; ?>" style="color:#1d4ed8;text-decoration:none;"><?php echo esc_html(parse_url($siteUrl, PHP_URL_HOST) ?: $siteUrl); ?></a>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
</body>
</html>
