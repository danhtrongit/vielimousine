<?php
declare(strict_types=1);

namespace Vie\Service\Auth;

final class ForbiddenException extends AuthException
{
    public function __construct(public readonly string $requiredCap, string $message = '')
    {
        parent::__construct($message !== '' ? $message : "Bạn không có quyền: {$requiredCap}");
    }
}
