<?php
declare(strict_types=1);

namespace Vie\DTO;

final readonly class LoginRequest
{
    public function __construct(
        public string  $username,
        public string  $password,
        public bool    $remember = false,
        public ?string $ip = null,
        public ?string $userAgent = null,
    ) {
    }

    public static function fromArray(array $data, ?string $ip, ?string $ua): self
    {
        return new self(
            username:  (string) ($data['username'] ?? ''),
            password:  (string) ($data['password'] ?? ''),
            remember:  (bool) ($data['remember'] ?? false),
            ip:        $ip,
            userAgent: $ua,
        );
    }
}
