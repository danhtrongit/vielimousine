<?php
declare(strict_types=1);

namespace Vie\Service\Auth;

final class TokenReuseException extends AuthException
{
    public function __construct(public readonly array $context, string $message = '')
    {
        parent::__construct($message !== '' ? $message : 'Refresh token bị reuse — toàn bộ session đã bị thu hồi');
    }
}
