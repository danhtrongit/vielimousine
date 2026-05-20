<?php
declare(strict_types=1);

namespace Vie\Service\Payment;

use Vie\DTO\PaymentRequest;

final class PaymentService
{
    private const ALLOWED_TYPES   = ['deposit', 'payment', 'refund', 'void'];
    private const ALLOWED_METHODS = ['sepay', 'bank_transfer', 'cash', 'manual'];

    public function __construct(
        private readonly PaymentLedger $ledger,
    ) {
    }

    /**
     * Manual entry by admin/sales (deposit/payment/refund/void).
     */
    public function manualEntry(PaymentRequest $req): array
    {
        if (!in_array($req->type, self::ALLOWED_TYPES, true)) {
            throw new PaymentException("Loại giao dịch không hợp lệ: {$req->type}");
        }
        if ($req->amount <= 0) {
            throw new PaymentException('Số tiền phải lớn hơn 0');
        }
        if (!in_array($req->method, self::ALLOWED_METHODS, true)) {
            throw new PaymentException("Phương thức không hợp lệ: {$req->method}");
        }

        return $this->ledger->record($req);
    }
}
