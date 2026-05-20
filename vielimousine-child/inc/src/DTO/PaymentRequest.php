<?php
declare(strict_types=1);

namespace Vie\DTO;

final readonly class PaymentRequest
{
    public function __construct(
        public int     $orderId,
        public string  $type,
        public int     $amount,
        public string  $method,
        public ?string $gateway,
        public ?string $transactionId,
        public ?string $note,
        public ?string $paidAt,
        public ?int    $createdBy,
        public ?array  $rawPayload,
    ) {
    }

    public static function fromArray(array $data, int $orderId): self
    {
        return new self(
            orderId:       $orderId,
            type:          (string) ($data['type'] ?? 'payment'),
            amount:        (int) ($data['amount'] ?? 0),
            method:        (string) ($data['method'] ?? 'manual'),
            gateway:       isset($data['gateway']) && $data['gateway'] !== '' ? (string) $data['gateway'] : null,
            transactionId: isset($data['transaction_id']) && $data['transaction_id'] !== '' ? (string) $data['transaction_id'] : null,
            note:          isset($data['note']) && $data['note'] !== '' ? (string) $data['note'] : null,
            paidAt:        isset($data['paid_at']) && $data['paid_at'] !== '' ? (string) $data['paid_at'] : null,
            createdBy:     isset($data['created_by']) ? (int) $data['created_by'] : null,
            rawPayload:    isset($data['raw_payload']) && is_array($data['raw_payload']) ? $data['raw_payload'] : null,
        );
    }

    public function toRow(): array
    {
        return [
            'order_id'       => $this->orderId,
            'type'           => $this->type,
            'amount'         => $this->amount,
            'method'         => $this->method,
            'gateway'        => $this->gateway,
            'transaction_id' => $this->transactionId,
            'note'           => $this->note,
            'paid_at'        => $this->paidAt,
            'created_by'     => $this->createdBy,
            'raw_payload'    => $this->rawPayload,
        ];
    }
}
