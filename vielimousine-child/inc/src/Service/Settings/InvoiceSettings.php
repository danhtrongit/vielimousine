<?php
declare(strict_types=1);

namespace Vie\Service\Settings;

final class InvoiceSettings
{
    private const OPTION_KEY = 'vie_invoice_settings';
    private const LOCK_KEY   = 'vie_invoice_alloc_lock';

    public const TEMPLATES = ['receipt', 'tax_invoice'];

    /**
     * @return array{
     *   company_name:string, company_tax_id:string, company_address:string,
     *   company_phone:string, company_email:string,
     *   bank_name:string, bank_account:string, bank_holder:string,
     *   logo_url:string,
     *   invoice_prefix:string, invoice_format:string,
     *   next_seq:int, reset_yearly:bool, last_seq_year:?int,
     *   vat_rate:float, footer_note:string
     * }
     */
    public function all(): array
    {
        $raw = get_option(self::OPTION_KEY, '');
        $data = [];
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        } elseif (is_array($raw)) {
            $data = $raw;
        }
        return $this->mergeDefaults($data);
    }

    public function defaults(): array
    {
        return [
            'company_name'    => (string) get_option('blogname', 'Vielimousine'),
            'company_tax_id'  => '',
            'company_address' => '',
            'company_phone'   => '',
            'company_email'   => (string) get_option('admin_email', ''),
            'bank_name'       => '',
            'bank_account'    => '',
            'bank_holder'     => '',
            'logo_url'        => '',
            'invoice_prefix'  => 'HD',
            'invoice_format'  => '{prefix}-{year}-{seq:5}',
            'next_seq'        => 1,
            'reset_yearly'    => true,
            'last_seq_year'   => null,
            'vat_rate'        => 8.0,
            'footer_note'     => 'Cảm ơn Quý khách đã sử dụng dịch vụ!',
        ];
    }

    public function update(array $values): array
    {
        $current   = $this->all();
        $sanitized = $current;

        $textFields = [
            'company_name', 'company_tax_id', 'company_address',
            'company_phone', 'company_email',
            'bank_name', 'bank_account', 'bank_holder',
            'invoice_prefix', 'invoice_format', 'footer_note',
        ];
        foreach ($textFields as $key) {
            if (array_key_exists($key, $values)) {
                $sanitized[$key] = sanitize_text_field((string) $values[$key]);
            }
        }
        if (array_key_exists('company_email', $values)) {
            $email = sanitize_email((string) $values['company_email']);
            $sanitized['company_email'] = $email;
        }
        if (array_key_exists('logo_url', $values)) {
            $sanitized['logo_url'] = esc_url_raw((string) $values['logo_url']);
        }
        if (array_key_exists('next_seq', $values)) {
            $sanitized['next_seq'] = max(1, (int) $values['next_seq']);
        }
        if (array_key_exists('reset_yearly', $values)) {
            $sanitized['reset_yearly'] = (bool) $values['reset_yearly'];
        }
        if (array_key_exists('vat_rate', $values)) {
            $rate = (float) $values['vat_rate'];
            $sanitized['vat_rate'] = max(0.0, min(100.0, $rate));
        }
        if (array_key_exists('footer_note', $values)) {
            $sanitized['footer_note'] = sanitize_textarea_field((string) $values['footer_note']);
        }

        update_option(self::OPTION_KEY, wp_json_encode($sanitized), false);
        return $sanitized;
    }

    /**
     * Atomically allocate the next invoice number, format it, persist incremented seq.
     * Best-effort race protection via wp_cache_add mutex (single-host caches only).
     */
    public function allocateNumber(): string
    {
        // Best-effort mutex; brief retry if locked.
        for ($i = 0; $i < 5; $i++) {
            if (wp_cache_add(self::LOCK_KEY, 1, '', 5)) {
                break;
            }
            usleep(50_000);
        }

        try {
            $cfg  = $this->all();
            $year = (int) wp_date('Y');

            if (!empty($cfg['reset_yearly']) && (int) ($cfg['last_seq_year'] ?? 0) !== $year) {
                $cfg['next_seq']      = 1;
                $cfg['last_seq_year'] = $year;
            } elseif (empty($cfg['last_seq_year'])) {
                $cfg['last_seq_year'] = $year;
            }

            $seq       = max(1, (int) $cfg['next_seq']);
            $formatted = $this->formatNumber($cfg['invoice_format'], $cfg['invoice_prefix'], $year, $seq);

            $cfg['next_seq'] = $seq + 1;
            update_option(self::OPTION_KEY, wp_json_encode($cfg), false);

            return $formatted;
        } finally {
            wp_cache_delete(self::LOCK_KEY);
        }
    }

    public function formatNumber(string $format, string $prefix, int $year, int $seq): string
    {
        $out = $format !== '' ? $format : '{prefix}-{year}-{seq:5}';
        $out = str_replace(['{prefix}', '{year}'], [$prefix, (string) $year], $out);
        $out = preg_replace_callback('/\{seq(?::(\d+))?\}/', static function ($m) use ($seq) {
            $width = isset($m[1]) ? (int) $m[1] : 1;
            return str_pad((string) $seq, max(1, $width), '0', STR_PAD_LEFT);
        }, $out);
        return (string) $out;
    }

    public function isConfigured(): bool
    {
        $cfg = $this->all();
        return $cfg['company_name'] !== '';
    }

    private function mergeDefaults(array $data): array
    {
        return array_merge($this->defaults(), $data);
    }
}
