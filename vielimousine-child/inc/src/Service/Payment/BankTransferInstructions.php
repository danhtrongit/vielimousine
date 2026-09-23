<?php
declare(strict_types=1);

namespace Vie\Service\Payment;

use Vie\Service\Settings\InvoiceSettings;

/** Builds the same bank-transfer instructions for order and quote payments. */
final class BankTransferInstructions
{
    public function __construct(private readonly InvoiceSettings $settings)
    {
    }

    public function isConfigured(): bool
    {
        $bank = $this->settings->all();
        return $bank['bank_name'] !== ''
            && preg_match('/^[A-Z0-9]{2,20}$/', (string) $bank['bank_code']) === 1
            && preg_match('/^[0-9]{6,20}$/', (string) $bank['bank_account']) === 1
            && $bank['bank_holder'] !== '';
    }

    /** @return array{bank_name:string,bank_code:string,bank_account:string,bank_holder:string,amount:int,memo:string,qr_url:string,currency:string} */
    public function build(int $amount, string $memo): array
    {
        if ($amount <= 0 || !$this->isConfigured()) {
            throw new \RuntimeException('Thông tin tài khoản nhận chuyển khoản chưa được cấu hình đầy đủ.');
        }
        $bank = $this->settings->all();
        $query = http_build_query([
            'acc' => $bank['bank_account'],
            'bank' => $bank['bank_code'],
            'amount' => $amount,
            'des' => $memo,
        ], '', '&', PHP_QUERY_RFC3986);
        return [
            'bank_name' => (string) $bank['bank_name'],
            'bank_code' => (string) $bank['bank_code'],
            'bank_account' => (string) $bank['bank_account'],
            'bank_holder' => (string) $bank['bank_holder'],
            'amount' => $amount,
            'memo' => $memo,
            'qr_url' => 'https://vietqr.app/img?' . $query,
            'currency' => 'VND',
        ];
    }
}
