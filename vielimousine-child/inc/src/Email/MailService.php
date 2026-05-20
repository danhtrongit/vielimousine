<?php
declare(strict_types=1);

namespace Vie\Email;

use Vie\Repository\ActivityLogRepository;
use Vie\Service\Settings\EmailSettings;

final class MailService
{
    public function __construct(
        private readonly EmailSettings $settings,
        private readonly ActivityLogRepository $activityRepo,
    ) {
    }

    /**
     * Gửi email HTML qua wp_mail. Không throw — log fail vào activity log.
     *
     * @param string|array<int,string> $to
     * @param array{template?: string, order_id?: int, type?: string} $context
     */
    public function send($to, string $subject, string $body, array $context = []): bool
    {
        if ($subject === '' || $body === '') {
            return false;
        }

        $recipients = is_array($to) ? array_values(array_filter($to)) : ($to !== '' ? [(string) $to] : []);
        if ($recipients === []) {
            return false;
        }

        $headers = [
            'From: ' . $this->settings->fromHeader(),
            'Content-Type: text/html; charset=UTF-8',
        ];
        $replyTo = $this->settings->replyTo();
        if ($replyTo !== '') {
            $headers[] = 'Reply-To: ' . $replyTo;
        }

        $contentTypeFilter = static fn(): string => 'text/html';
        add_filter('wp_mail_content_type', $contentTypeFilter);

        $ok = false;
        $error = null;
        try {
            $ok = (bool) wp_mail($recipients, $subject, $body, $headers);
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        } finally {
            remove_filter('wp_mail_content_type', $contentTypeFilter);
        }

        $this->logActivity($ok, $recipients, $subject, $context, $error);
        return $ok;
    }

    private function logActivity(bool $ok, array $recipients, string $subject, array $context, ?string $error): void
    {
        try {
            $this->activityRepo->create([
                'actor_user_id' => (int) get_current_user_id(),
                'entity_type'   => 'mail',
                'entity_id'     => (int) ($context['order_id'] ?? 0),
                'action'        => $ok ? 'sent' : 'fail',
                'before_json'   => null,
                'after_json'    => [
                    'to'       => $recipients,
                    'subject'  => $subject,
                    'template' => $context['template'] ?? null,
                    'type'     => $context['type'] ?? null,
                    'error'    => $error,
                ],
                'ip'            => null,
                'user_agent'    => null,
            ]);
        } catch (\Throwable) {
            // Nuốt — không cho lỗi log làm gãy gửi mail
        }
    }
}
