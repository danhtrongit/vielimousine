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

    public function isSandbox(): bool
    {
        return get_option('vie_sepay_environment', 'sandbox') !== 'production';
    }

    public function autoConfirmOnPaid(): bool
    {
        return (bool) get_option('vie_sepay_auto_confirm_on_paid', true);
    }

    public function checkoutUrl(): string
    {
        return $this->isSandbox()
            ? 'https://pay-sandbox.sepay.vn/v1/checkout/init'
            : 'https://pay.sepay.vn/v1/checkout/init';
    }

    public function update(array $values): void
    {
        $map = [
            'enabled'              => 'vie_sepay_enabled',
            'merchant_id'          => 'vie_sepay_merchant_id',
            'secret_key'           => 'vie_sepay_secret_key',
            'environment'          => 'vie_sepay_environment',
            'auto_confirm_on_paid' => 'vie_sepay_auto_confirm_on_paid',
        ];
        foreach ($map as $key => $option) {
            if (array_key_exists($key, $values)) {
                update_option($option, $values[$key], false);
            }
        }
    }
}
