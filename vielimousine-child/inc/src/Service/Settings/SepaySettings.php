<?php
declare(strict_types=1);

namespace Vie\Service\Settings;

final class SepaySettings
{
    public function enabled(): bool
    {
        return (bool) get_option('vie_sepay_enabled', false);
    }

    public function merchantId(): string
    {
        return (string) get_option('vie_sepay_merchant_id', '');
    }

    public function secretKey(): string
    {
        return (string) get_option('vie_sepay_secret_key', '');
    }

    /** Dedicated SePay bank-webhook HMAC secret. The Hosted Checkout secret is deprecated. */
    public function webhookSecret(): string
    {
        return (string) get_option('vie_sepay_webhook_secret', '');
    }

    public function webhookUrl(): string
    {
        $namespace = defined('VIE_API_NAMESPACE') ? (string) VIE_API_NAMESPACE : 'vie/v1';
        return function_exists('rest_url') ? rest_url($namespace . '/payments/sepay/webhook') : '/wp-json/' . $namespace . '/payments/sepay/webhook';
    }

    public function isSandbox(): bool
    {
        return get_option('vie_sepay_environment', 'sandbox') !== 'production';
    }

    public function autoConfirmOnPaid(): bool
    {
        return (bool) get_option('vie_sepay_auto_confirm_on_paid', true);
    }

    public function update(array $values): ?\WP_Error
    {
        if (array_key_exists('webhook_secret', $values)) {
            $secret = trim((string) $values['webhook_secret']);
            if ($secret !== '' && (strlen($secret) < 16 || strlen($secret) > 255)) {
                return new \WP_Error('invalid_webhook_secret', 'Webhook secret phải dài từ 16 đến 255 ký tự.');
            }
            $values['webhook_secret'] = $secret;
        }
        $map = [
            'enabled'              => 'vie_sepay_enabled',
            'merchant_id'          => 'vie_sepay_merchant_id',
            'secret_key'           => 'vie_sepay_secret_key',
            'webhook_secret'       => 'vie_sepay_webhook_secret',
            'environment'          => 'vie_sepay_environment',
            'auto_confirm_on_paid' => 'vie_sepay_auto_confirm_on_paid',
        ];
        foreach ($map as $key => $option) {
            if (array_key_exists($key, $values)) {
                update_option($option, $values[$key], false);
            }
        }
        return null;
    }
}
