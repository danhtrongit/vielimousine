<?php
declare(strict_types=1);

namespace Vie\Http\Controllers;

use Vie\Container;
use Vie\DTO\PaymentRequest;
use Vie\Repository\PaymentLogRepository;
use Vie\Service\Payment\IdempotencyConflictException;
use Vie\Service\Payment\PaymentException;
use Vie\Service\Payment\PaymentService;
use Vie\Support\ResponseEnvelope;
use Vie\Support\Validator;
use Vie\Validation\Schemas\PaymentEntryValidation;

final class PaymentLogController
{
    public static function index(\WP_REST_Request $request): \WP_REST_Response
    {
        $repo   = Container::get(PaymentLogRepository::class);
        $params = $request->get_params();

        // Cache pull báo cáo/bulk (per_page > 100); danh sách tương tác (<=100) luôn tươi.
        $result = ((int) ($params['per_page'] ?? 0) > 100)
            ? \Vie\Support\QueryCache::remember(
                'payments_index|' . get_current_user_id() . '|' . wp_json_encode($params),
                \Vie\Support\QueryCache::TTL,
                static fn() => $repo->all($params)
            )
            : $repo->all($params);

        return ResponseEnvelope::paginated($result['data'], $result['pagination'], [
            'sort'            => $result['sort'],
            'filters_applied' => $result['filters_applied'],
            'available_sorts' => $repo->availableSorts(),
        ]);
    }

    public static function show(\WP_REST_Request $request): \WP_REST_Response
    {
        $repo = Container::get(PaymentLogRepository::class);
        $row  = $repo->find((int) $request->get_param('id'));

        if ($row === null) {
            return ResponseEnvelope::notFound('Payment');
        }

        return ResponseEnvelope::success($row);
    }

    public static function store(\WP_REST_Request $request): \WP_REST_Response
    {
        $data = $request->get_json_params() ?? [];
        if (!is_array($data)) {
            $data = [];
        }

        $v = Validator::validate($data, PaymentEntryValidation::rules());
        if ($v->fails()) {
            return ResponseEnvelope::error($v->errors(), 422);
        }
        $clean = $v->validated();

        try {
            $req     = PaymentRequest::fromArray($clean, (int) $clean['order_id']);
            $service = Container::get(PaymentService::class);
            $result  = $service->manualEntry($req);
            return ResponseEnvelope::success($result, [], 201);
        } catch (IdempotencyConflictException $e) {
            return ResponseEnvelope::error([
                [
                    'code'    => 'duplicate_transaction',
                    'field'   => 'transaction_id',
                    'message' => $e->getMessage(),
                    'meta'    => ['existing_payment_log_id' => $e->existingPaymentLogId],
                ],
            ], 409);
        } catch (PaymentException $e) {
            return ResponseEnvelope::error([
                ['code' => 'payment_error', 'field' => null, 'message' => $e->getMessage()],
            ], 422);
        }
    }
}
