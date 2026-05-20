<?php
declare(strict_types=1);

namespace Vie\Service\Auth;

final class InvalidTokenException extends AuthException
{
    public function __construct(public readonly string $reason, string $message = '')
    {
        parent::__construct($message !== '' ? $message : "Token không hợp lệ: {$reason}");
    }
}
