<?php
declare(strict_types=1);

namespace Vie\Http\Controllers;

use Vie\Container;
use Vie\Service\BookingQuote\BookingQuoteException;
use Vie\Service\BookingQuote\BookingQuotePaymentService;
use Vie\Service\BookingQuote\BookingQuoteService;
use Vie\Service\Order\OrderDraftService;
use Vie\Support\ResponseEnvelope;

final class BookingQuoteController
{
    public static function index(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $result = self::service()->list($request->get_params());
            return ResponseEnvelope::paginated($result['data'], $result['pagination'], [
                'sort' => $result['sort'],
                'filters_applied' => $result['filters_applied'],
            ]);
        } catch (\Throwable) {
            return self::internalError();
        }
    }

    public static function store(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $quote = self::service()->createDraft(self::json($request), (int) get_current_user_id());
            return ResponseEnvelope::success($quote, [], 201);
        } catch (BookingQuoteException $e) {
            return self::exception($e);
        } catch (\Throwable) {
            return self::internalError();
        }
    }

    public static function show(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $service = self::service();
            $quote = $service->getAdmin((int) $request->get_param('id'), (int) get_current_user_id());
            $view = $service->adminView($quote);
            $view['payments'] = Container::get(BookingQuotePaymentService::class)->history((int) $quote['id']);
            return ResponseEnvelope::success($view);
        } catch (BookingQuoteException $e) {
            return self::exception($e);
        } catch (\Throwable) {
            return self::internalError();
        }
    }

    public static function update(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $quote = self::service()->updateDraft(
                (int) $request->get_param('id'),
                self::json($request),
                (int) get_current_user_id(),
            );
            return ResponseEnvelope::success($quote);
        } catch (BookingQuoteException $e) {
            return self::exception($e);
        } catch (\Throwable) {
            return self::internalError();
        }
    }

    public static function publish(\WP_REST_Request $request): \WP_REST_Response
    {
        return self::stateAction($request, 'publish');
    }

    public static function revoke(\WP_REST_Request $request): \WP_REST_Response
    {
        return self::stateAction($request, 'revoke');
    }

    public static function duplicate(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $quote = self::service()->duplicate(
                (int) $request->get_param('id'),
                (int) get_current_user_id(),
            );
            return ResponseEnvelope::success($quote, [], 201);
        } catch (BookingQuoteException $e) {
            return self::exception($e);
        } catch (\Throwable) {
            return self::internalError();
        }
    }

    /** Create an order draft prefilled from the room selections in a quote. */
    public static function orderDraft(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $quoteId = (int) $request->get_param('id');
            $userId = (int) get_current_user_id();
            $payload = self::service()->orderDraftPayload($quoteId, $userId);
            $draft = Container::get(OrderDraftService::class)->save($payload, $userId);
            return ResponseEnvelope::success($draft, [], 201);
        } catch (BookingQuoteException $e) {
            return self::exception($e);
        } catch (\Throwable) {
            return self::internalError();
        }
    }

    private static function stateAction(\WP_REST_Request $request, string $action): \WP_REST_Response
    {
        try {
            $service = self::service();
            $id = (int) $request->get_param('id');
            $userId = (int) get_current_user_id();
            $quote = $action === 'publish'
                ? $service->publish($id, $userId)
                : $service->revoke($id, $userId);
            return ResponseEnvelope::success($quote);
        } catch (BookingQuoteException $e) {
            return self::exception($e);
        } catch (\Throwable) {
            return self::internalError();
        }
    }

    private static function service(): BookingQuoteService
    {
        /** @var BookingQuoteService */
        return Container::get(BookingQuoteService::class);
    }

    private static function json(\WP_REST_Request $request): array
    {
        $data = $request->get_json_params();
        return is_array($data) ? $data : [];
    }

    private static function exception(BookingQuoteException $e): \WP_REST_Response
    {
        return ResponseEnvelope::error([[
            'code' => $e->errorCode,
            'field' => $e->field,
            'message' => $e->getMessage(),
        ]], $e->httpStatus);
    }

    private static function internalError(): \WP_REST_Response
    {
        return ResponseEnvelope::error([[
            'code' => 'booking_quote_error',
            'field' => null,
            'message' => 'Không thể xử lý báo giá lúc này. Vui lòng thử lại.',
        ]], 500);
    }
}
