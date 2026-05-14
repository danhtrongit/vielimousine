<?php
declare(strict_types=1);

namespace Vie\Service;

final class HookRegistry
{
    public const ORDER_CREATED   = 'vie_order_created';
    public const ORDER_CANCELLED = 'vie_order_cancelled';
    public const ORDER_CONFIRMED = 'vie_order_confirmed';
    public const PAYMENT_LOGGED  = 'vie_payment_logged';

    public const USER_LOGGED_IN       = 'vie_user_logged_in';
    public const TOKEN_REFRESH_ISSUED = 'vie_token_refresh_issued';
    public const TOKEN_REVOKED        = 'vie_token_revoked';
    public const TOKEN_REUSE_DETECTED = 'vie_token_reuse_detected';
}
