# 10 — Payment & SePay

## 10.1. Triết lý: Sổ cái immutable

`vie_payment_log` là **append-only ledger**. Mọi tính toán `paid_amount` / `payment_status` đều derive từ ledger, không cache.

Quy tắc:

```text
paid_amount(order) = max(0, Σ vie_payment_log.amount WHERE order_id=? )

payment_status:
  paid_amount <= 0           → pending
  paid_amount <  total       → partial
  paid_amount >= total       → paid
```

Auto rules:

- `payment_status` chuyển `paid` **và** `status = pending` → set `status = confirmed` + `confirmed_at`.
- `refund` / `void` khiến `paid_amount < total` → hạ về `partial` / `pending`.
- Không sửa row → sửa sai = thêm `void` / `adjustment` bù.

## 10.2. `PaymentLedger` service

Single entry point để ghi log:

```php
final class PaymentLedger {
    public function record(int $orderId, array $entry): int {
        $this->validateEntry($entry);
        $this->ensureNoDuplicate($entry); // UNIQUE(gateway, transaction_id) trong app layer
        $logId = $this->repo->insert([
            'order_id'       => $orderId,
            'type'           => $entry['type'],          // deposit|payment|refund|adjustment|void
            'amount'         => (int)$entry['amount'],
            'method'         => $entry['method'],
            'gateway'        => $entry['gateway']        ?? null,
            'transaction_id' => $entry['transaction_id'] ?? null,
            'note'           => $entry['note']           ?? null,
            'paid_at'        => $entry['paid_at']        ?? current_time('mysql'),
            'created_by'     => $entry['created_by']     ?? get_current_user_id() ?: 0,
            'raw_payload'    => $entry['raw_payload']    ? wp_json_encode($entry['raw_payload']) : null,
        ]);

        $this->recomputeOrder($orderId);   // cập nhật paid_amount, payment_status, auto-confirm
        $this->activity->log('payment_log', $logId, 'create', null, $entry);
        return $logId;
    }
}
```

`recomputeOrder` chạy trong cùng transaction (start transaction → insert log → select sum → update order → commit). Nếu fail → rollback.

## 10.3. SePay flow

### 10.3.1. Init

`POST /quote` → SPA / public form → submit `/orders`. Trong `OrderService::create()`:

- Sinh `code`.
- Insert order pending.
- Nếu `payment_method = 'sepay'` → gọi `SepayCheckout::init($order)` → trả `redirect_url`.

```php
$params = [
    'merchant'             => $cfg['merchant_id'],
    'operation'            => 'order_pay',
    'payment_method'       => 'auto',
    'order_amount'         => (int)$order->total,
    'currency'             => 'VND',
    'order_invoice_number' => $order->code,                    // ← MÃ ĐẶT PHÒNG
    'order_description'    => $description,                    // ← từ OrderDescription
    'customer_id'          => (string)$order->customer_id,
    'success_url'          => add_query_arg(['code'=>$order->code], $this->successUrl()),
    'error_url'            => add_query_arg(['code'=>$order->code], $this->errorUrl()),
    'cancel_url'           => add_query_arg(['code'=>$order->code], $this->cancelUrl()),
];
$params['signature'] = $this->sign($params);
$redirect = $this->checkoutUrl() . '?' . http_build_query($params);
```

### 10.3.2. Sign

HMAC-SHA256 trên các field `SIGNED_FIELDS` (xem `vie-sepay` legacy).

```php
$signFields = ['merchant','operation','payment_method','order_amount','currency',
    'order_invoice_number','order_description','customer_id','success_url','error_url','cancel_url'];
$payload = implode('|', array_map(fn($f) => (string)($params[$f] ?? ''), $signFields));
$signature = hash_hmac('sha256', $payload, $cfg['secret_key']);
```

### 10.3.3. Success / Cancel URL

- `/dat-phong-thanh-cong/?code=VIE...&phone=...` — render trạng thái dựa `GET /orders/lookup?code=...`.
- Cancel URL trỏ về trang checkout với `?code=...&status=cancelled`.

> Không tin success URL để confirm thanh toán! **Chỉ webhook IPN** là nguồn sự thật.

## 10.4. Webhook IPN

### `POST /payments/sepay/webhook`

Public endpoint. Verify HMAC. Idempotent (lookup `UNIQUE(gateway='sepay', transaction_id)`).

```php
public function handle(WP_REST_Request $req): WP_REST_Response {
    $payload = $req->get_json_params();

    // 1. Verify signature
    if (!$this->verifySig($payload)) return $this->ok();   // 200 OK silently — đừng leak

    // 2. Tìm order
    $code  = $payload['order_invoice_number'] ?? null;
    $order = $this->orders->findByCode($code);
    if (!$order) {
        $this->activity->log('sepay_webhook', 0, 'order_not_found', null, $payload);
        return $this->ok();
    }

    // 3. Idempotency
    $txId = $payload['transaction_id'] ?? null;
    if ($txId && $this->ledger->existsBy('sepay', $txId)) {
        return $this->ok();
    }

    // 4. Map type
    $amount = (int)$payload['amount'];
    $status = $payload['status'] ?? 'success';
    if ($status !== 'success') return $this->ok();

    $type = $amount >= (int)$order->total ? 'payment' : 'deposit';

    // 5. Append
    $this->ledger->record($order->id, [
        'type'           => $type,
        'amount'         => $amount,
        'method'         => 'sepay',
        'gateway'        => 'sepay',
        'transaction_id' => $txId,
        'note'           => 'SePay IPN',
        'paid_at'        => $payload['paid_at'] ?? current_time('mysql'),
        'created_by'     => 0,
        'raw_payload'    => $payload,
    ]);

    return $this->ok();
}
```

### Trả lời SePay

Luôn `200 OK` để SePay ngừng retry. Lỗi nội bộ log vào `vie_activity_log`.

## 10.5. Bank transfer manual (chuyển khoản ngoài SePay)

Admin SPA → Order detail → tab Payments → "Thu tiền":

```json
{
  "type": "deposit",
  "amount": 2000000,
  "method": "bank_transfer",
  "transaction_id": "FT26060512345",
  "paid_at": "2026-05-12T14:30:00+07:00",
  "note": "Cọc 50% qua BIDV"
}
```

`PaymentLedger::record()` xử lý y như IPN.

## 10.6. Cash

Sales nhập tay tại quầy → cùng flow, method = `cash`, không có `transaction_id`.

## 10.7. Refund

```json
{
  "type": "refund",
  "amount": -500000,
  "method": "bank_transfer",
  "transaction_id": "FT26070598765",
  "note": "Hoàn cọc do hủy phòng"
}
```

Cấm `amount > 0` cho `type='refund'`. Validator enforce.

## 10.8. Void / Adjustment

`void` = bù trừ 1 dòng nhập sai. `adjustment` = điều chỉnh thủ công (ví dụ discount sau giao dịch).

```json
{ "type":"void", "amount":-4000000, "method":"bank_transfer", "note":"Void dòng #15 nhập sai số tiền" }
```

```json
{ "type":"adjustment", "amount":-100000, "method":"other", "note":"Trừ phụ phí thẻ chạy không thành công" }
```

## 10.9. Settings

| Key | Mô tả |
|---|---|
| `enabled` | bật/tắt SePay |
| `merchant_id` | |
| `secret_key` | |
| `environment` | `production` / `sandbox` |
| `auto_confirm_on_paid` | nếu true: `payment_status=paid` → `status=confirmed`. Default true. |

URL endpoint:

- Production checkout: `https://pay.sepay.vn/v1/checkout/init`
- Sandbox: `https://pay-sandbox.sepay.vn/v1/checkout/init`

## 10.10. Test scenarios

| Scenario | Expected |
|---|---|
| IPN đúng signature, lần đầu | log inserted, `paid_amount` tăng |
| IPN duplicate (cùng `transaction_id`) | bỏ qua, vẫn 200 |
| IPN sai signature | log activity, trả 200 (không leak) |
| Order không tồn tại | log activity, trả 200 |
| Manual deposit + IPN cho remaining | 2 row, status `paid`, auto confirm |
| Refund 1 phần | `payment_status` về `partial` |
| Void dòng nhập sai | 2 row tổng = 0, hoàn về `pending` nếu chỉ có 1 thu sai |

## 10.11. Báo cáo dòng tiền

Báo cáo "Thực thu theo ngày" (xem [11-reporting.md](11-reporting.md)) query `vie_payment_log.paid_at` group by ngày + method.
