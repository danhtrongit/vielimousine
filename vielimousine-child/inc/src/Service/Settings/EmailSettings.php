<?php
declare(strict_types=1);

namespace Vie\Service\Settings;

final class EmailSettings
{
    private const OPTION_KEY = 'vie_email_settings';

    public const TEMPLATE_KEYS = [
        'pending_payment',
        'paid',
        'partial',
        'confirmed',
        'completed',
        'cancelled',
        'admin_notification',
        'admin_paid',
        'admin_cancelled',
        'checkin_code',
    ];

    /**
     * @return array{
     *   from_name: string,
     *   from_email: string,
     *   reply_to: string,
     *   logo_url: string,
     *   admin_recipients: array<int,string>,
     *   templates: array<string, array{enabled: bool, subject: string, body: string}>
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
        $adminEmail = (string) get_option('admin_email', '');
        $defaults = [
            'from_name'        => (string) get_option('blogname', 'Vielimousine'),
            'from_email'       => $adminEmail,
            'reply_to'         => '',
            'logo_url'         => '',
            'admin_recipients' => $adminEmail !== '' ? [$adminEmail] : [],
            'templates'        => [],
        ];
        foreach (self::TEMPLATE_KEYS as $key) {
            $defaults['templates'][$key] = [
                'enabled' => true,
                'subject' => '',
                'body'    => '',
            ];
        }
        return $defaults;
    }

    public function update(array $values): array
    {
        $current  = $this->all();
        $sanitized = $current;

        if (array_key_exists('from_name', $values)) {
            $sanitized['from_name'] = sanitize_text_field((string) $values['from_name']);
        }
        if (array_key_exists('from_email', $values)) {
            $email = sanitize_email((string) $values['from_email']);
            if ($email !== '') {
                $sanitized['from_email'] = $email;
            }
        }
        if (array_key_exists('reply_to', $values)) {
            $reply = sanitize_email((string) $values['reply_to']);
            $sanitized['reply_to'] = $reply;
        }
        if (array_key_exists('logo_url', $values)) {
            $sanitized['logo_url'] = esc_url_raw((string) $values['logo_url']);
        }
        if (array_key_exists('admin_recipients', $values)) {
            $list = is_array($values['admin_recipients'])
                ? $values['admin_recipients']
                : preg_split('/[,\n\r]+/', (string) $values['admin_recipients']);
            $emails = [];
            foreach ((array) $list as $item) {
                $email = sanitize_email((string) $item);
                if ($email !== '' && !in_array($email, $emails, true)) {
                    $emails[] = $email;
                }
            }
            $sanitized['admin_recipients'] = $emails;
        }
        if (array_key_exists('templates', $values) && is_array($values['templates'])) {
            foreach (self::TEMPLATE_KEYS as $key) {
                if (!isset($values['templates'][$key]) || !is_array($values['templates'][$key])) {
                    continue;
                }
                $tpl = $values['templates'][$key];
                $sanitized['templates'][$key] = [
                    'enabled' => isset($tpl['enabled']) ? (bool) $tpl['enabled'] : true,
                    'subject' => isset($tpl['subject']) ? sanitize_text_field((string) $tpl['subject']) : '',
                    'body'    => isset($tpl['body']) ? wp_kses_post((string) $tpl['body']) : '',
                ];
            }
        }

        update_option(self::OPTION_KEY, wp_json_encode($sanitized), false);
        return $sanitized;
    }

    public function templateOverride(string $key): ?array
    {
        $cfg = $this->all();
        return $cfg['templates'][$key] ?? null;
    }

    public function isTemplateEnabled(string $key): bool
    {
        $tpl = $this->templateOverride($key);
        if ($tpl === null) {
            return true;
        }
        return (bool) ($tpl['enabled'] ?? true);
    }

    public function adminRecipients(): array
    {
        return $this->all()['admin_recipients'];
    }

    public function fromHeader(): string
    {
        $cfg = $this->all();
        $name  = $cfg['from_name']  !== '' ? $cfg['from_name']  : 'Vielimousine';
        $email = $cfg['from_email'] !== '' ? $cfg['from_email'] : (string) get_option('admin_email');
        return sprintf('%s <%s>', $name, $email);
    }

    public function replyTo(): string
    {
        return $this->all()['reply_to'];
    }

    public function logoUrl(): string
    {
        return $this->all()['logo_url'];
    }

    private function mergeDefaults(array $data): array
    {
        $defaults = $this->defaults();
        $merged = array_merge($defaults, $data);
        $merged['templates'] = $defaults['templates'];
        if (isset($data['templates']) && is_array($data['templates'])) {
            foreach (self::TEMPLATE_KEYS as $key) {
                if (isset($data['templates'][$key]) && is_array($data['templates'][$key])) {
                    $merged['templates'][$key] = array_merge(
                        $defaults['templates'][$key],
                        $data['templates'][$key]
                    );
                }
            }
        }
        $merged['admin_recipients'] = is_array($merged['admin_recipients'] ?? null)
            ? array_values(array_filter($merged['admin_recipients']))
            : $defaults['admin_recipients'];
        return $merged;
    }
}
