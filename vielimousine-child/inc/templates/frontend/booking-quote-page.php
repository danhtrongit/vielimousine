<?php
/** @var array|null $assets @var string $publicId @var array $boot @var string $cspNonce */
if (!defined('ABSPATH')) {
    exit;
}
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <meta name="referrer" content="no-referrer">
    <meta name="theme-color" content="#137847">
    <title>Báo giá booking | Vie Limo</title>
    <?php foreach ($assets['css'] ?? [] as $css): ?>
    <link rel="stylesheet" href="<?php echo esc_url($css); ?>">
    <?php endforeach; ?>
    <?php foreach ($assets['imports'] ?? [] as $import): ?>
    <link rel="modulepreload" href="<?php echo esc_url($import); ?>">
    <?php endforeach; ?>
</head>
<body>
    <?php if ($assets === null): ?>
    <main>
        <h1>Báo giá tạm thời chưa khả dụng</h1>
        <p>Vui lòng thử lại sau hoặc liên hệ nhân viên phụ trách để được hỗ trợ.</p>
        <a href="<?php echo esc_url(home_url('/')); ?>">Về trang chủ Vie Limo</a>
    </main>
    <?php else: ?>
    <div data-vie-public-booking data-public-id="<?php echo esc_attr($publicId); ?>">
        <noscript>Vui lòng bật JavaScript để xem báo giá và kiểm tra thanh toán.</noscript>
    </div>
    <script nonce="<?php echo esc_attr($cspNonce); ?>">window.VieRest=<?php echo wp_json_encode($boot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script>
    <script type="module" src="<?php echo esc_url($assets['entry']); ?>"></script>
    <?php endif; ?>
</body>
</html>
