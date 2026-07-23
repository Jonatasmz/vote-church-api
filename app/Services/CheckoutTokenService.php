<?php

namespace App\Services;

use App\Models\EventEnrollment;
use Illuminate\Http\Request;

/**
 * Token HMAC que autoriza abrir/pagar uma inscrição sem login. Vinculado ao id,
 * valor e criação — muda se qualquer um mudar. Usado tanto pelo checkout mock
 * quanto pela tela de retorno do Stripe.
 */
class CheckoutTokenService
{
    public static function tokenFor(EventEnrollment $enrollment): string
    {
        $payload = $enrollment->id.'|'.$enrollment->amount_cents.'|'.$enrollment->created_at?->toIso8601String();

        return hash_hmac('sha256', $payload, config('app.key'));
    }

    /**
     * Caminho relativo do checkout mock (resolvido no domínio do front).
     */
    public static function checkoutPath(EventEnrollment $enrollment): string
    {
        return "/checkout/{$enrollment->id}?t=".self::tokenFor($enrollment);
    }

    /**
     * URL absoluta do checkout mock — só quando o destino é externo (RD redirect).
     */
    public static function checkoutUrl(EventEnrollment $enrollment): string
    {
        $base = config('app.frontend_url') ?: config('app.url');

        return rtrim($base, '/').self::checkoutPath($enrollment);
    }

    public static function assertToken(Request $request, EventEnrollment $enrollment): void
    {
        $expected = self::tokenFor($enrollment);
        $given = $request->query('t', '');
        if (! hash_equals($expected, (string) $given)) {
            abort(403, 'Token inválido');
        }
    }
}
