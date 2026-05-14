<?php
declare(strict_types=1);

namespace Vie\Http\Controllers;

use Vie\Container;
use Vie\Email\Fixtures;
use Vie\Email\OrderEmailService;
use Vie\Service\Settings\EmailSettings;
use Vie\Service\Settings\SepaySettings;
use Vie\Support\ResponseEnvelope;

final class SettingsController
{
    // ---------- General ----------

    public static function getGeneral(\WP_REST_Request $request): \WP_REST_Response
    {
        return ResponseEnvelope::success([
            'site_name'            => (string) get_option('blogname'),
            'site_url'             => home_url('/'),
            'admin_email'          => (string) get_option('admin_email'),
            'timezone'             => (string) wp_timezone_string(),
            'currency'             => 'VND',
        ]);
    }

    public static function updateGeneral(\WP_REST_Request $request): \WP_REST_Response
    {
        return ResponseEnvelope::success([
            'message' => 'Cài đặt chung là read-only. Đổi qua trang Tổng quan WordPress.',
        ]);
    }

    // ---------- Email ----------

    public static function getEmail(\WP_REST_Request $request): \WP_REST_Response
    {
        $settings = Container::get(EmailSettings::class);
        return ResponseEnvelope::success([
            'config'         => $settings->all(),
            'template_keys'  => EmailSettings::TEMPLATE_KEYS,
        ]);
    }

    public static function updateEmail(\WP_REST_Request $request): \WP_REST_Response
    {
        $data = $request->get_json_params();
        if (!is_array($data)) {
            return ResponseEnvelope::error([
                ['code' => 'validation_error', 'field' => null, 'message' => 'Body phải là JSON object'],
            ], 422);
        }
        $settings = Container::get(EmailSettings::class);
        $updated  = $settings->update($data);
        return ResponseEnvelope::success([
            'config' => $updated,
        ]);
    }

    public static function testEmail(\WP_REST_Request $request): \WP_REST_Response
    {
        $type = trim((string) $request->get_param('template'));
        if ($type === '' || !in_array($type, EmailSettings::TEMPLATE_KEYS, true)) {
            return ResponseEnvelope::error([
                ['code' => 'validation_error', 'field' => 'template', 'message' => 'Template không hợp lệ'],
            ], 422);
        }
        $to = trim((string) $request->get_param('to'));
        if ($to === '' || !is_email($to)) {
            $to = (string) get_option('admin_email');
        }

        $ctx  = Container::get(Fixtures::class)->sampleOrder();
        $mail = Container::get(OrderEmailService::class);
        $ok   = $mail->sendByType($type, $ctx, $to);

        if (!$ok) {
            return ResponseEnvelope::error([
                ['code' => 'mail_failed', 'field' => null, 'message' => "Không gửi được mail ({$type})"],
            ], 500);
        }
        return ResponseEnvelope::success([
            'sent'     => true,
            'template' => $type,
            'to'       => $to,
        ]);
    }

    // ---------- SePay ----------

    public static function getSepay(\WP_REST_Request $request): \WP_REST_Response
    {
        $s = Container::get(SepaySettings::class);
        return ResponseEnvelope::success([
            'enabled'              => $s->enabled(),
            'merchant_id'          => $s->merchantId(),
            'secret_key_set'       => $s->secretKey() !== '',
            'environment'          => $s->isSandbox() ? 'sandbox' : 'production',
            'auto_confirm_on_paid' => $s->autoConfirmOnPaid(),
        ]);
    }

    public static function updateSepay(\WP_REST_Request $request): \WP_REST_Response
    {
        $data = $request->get_json_params();
        if (!is_array($data)) {
            return ResponseEnvelope::error([
                ['code' => 'validation_error', 'field' => null, 'message' => 'Body phải là JSON object'],
            ], 422);
        }
        $allowed = ['enabled', 'merchant_id', 'secret_key', 'environment', 'auto_confirm_on_paid'];
        $clean = [];
        foreach ($allowed as $key) {
            if (!array_key_exists($key, $data)) continue;
            $val = $data[$key];
            if ($key === 'environment') {
                $clean[$key] = in_array($val, ['sandbox', 'production'], true) ? $val : 'sandbox';
            } elseif (in_array($key, ['enabled', 'auto_confirm_on_paid'], true)) {
                $clean[$key] = (bool) $val;
            } else {
                $clean[$key] = sanitize_text_field((string) $val);
            }
        }
        // Không lưu secret_key nếu rỗng (giữ giá trị cũ)
        if (isset($clean['secret_key']) && $clean['secret_key'] === '') {
            unset($clean['secret_key']);
        }

        $s = Container::get(SepaySettings::class);
        $s->update($clean);

        return self::getSepay($request);
    }
}
