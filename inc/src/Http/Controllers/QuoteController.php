<?php
declare(strict_types=1);

namespace Vie\Http\Controllers;

use Vie\Container;
use Vie\DTO\QuoteRequest;
use Vie\Repository\RepositoryException;
use Vie\Service\Pricing\PriceCalculator;
use Vie\Support\ResponseEnvelope;
use Vie\Support\Validator;
use Vie\Validation\Schemas\QuoteValidation;

final class QuoteController
{
    public static function quote(\WP_REST_Request $request): \WP_REST_Response
    {
        $data = $request->get_json_params() ?? [];
        if (!is_array($data)) {
            $data = [];
        }

        $v = Validator::validate($data, QuoteValidation::rules());
        if ($v->fails()) {
            return ResponseEnvelope::error($v->errors(), 422);
        }

        $cross = QuoteValidation::crossValidate($v->validated());
        if ($cross !== []) {
            return ResponseEnvelope::error($cross, 422);
        }

        try {
            $req       = QuoteRequest::fromArray($v->validated());
            $calc      = Container::get(PriceCalculator::class);
            $breakdown = $calc->quote($req);
            return ResponseEnvelope::success($breakdown->toArray());
        } catch (RepositoryException $e) {
            return ResponseEnvelope::notFound('Phòng');
        }
    }
}
