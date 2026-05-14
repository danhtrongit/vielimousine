<?php
declare(strict_types=1);

namespace Vie\Service\Auth;

final class InvalidCredentialsException extends AuthException
{
    public function __construct(string $message = '')
    {
        parent::__construct($message !== '' ? $message : 'Tài khoản hoặc mật khẩu không đúng');
    }
}
