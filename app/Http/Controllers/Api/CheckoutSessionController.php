<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventEnrollment;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class CheckoutSessionController extends Controller
{
    /**
     * Mock Stripe Checkout: GET retorna dados pra UI exibir.
     * Em produção isso seria a página hospedada do Stripe.
     */
    public function show(Request $request, EventEnrollment $enrollment)
    {
        $this->assertToken($request, $enrollment);
        $enrollment->load('schedule:id,name,date,end_date,price,installments,time');

        return response()->json([
            'success' => true,
            'data'    => [
                'enrollment_id' => $enrollment->id,
                'name'          => $enrollment->name,
                'email'         => $enrollment->email,
                'amount_cents'  => $enrollment->amount_cents,
                'status'        => $enrollment->status,
                'installments'  => $enrollment->schedule?->installments,
                'event' => [
                    'name'     => $enrollment->schedule?->name,
                    'date'     => $enrollment->schedule?->date?->format('Y-m-d'),
                    'end_date' => $enrollment->schedule?->end_date?->format('Y-m-d'),
                    'time'     => substr($enrollment->schedule?->time ?? '', 0, 5),
                ],
            ],
        ]);
    }

    /**
     * Mock pagamento: marca como pago, simula webhook.
     */
    public function confirm(Request $request, EventEnrollment $enrollment)
    {
        $this->assertToken($request, $enrollment);

        if ($enrollment->status === 'paid') {
            return response()->json([
                'success' => true,
                'message' => 'Inscrição já paga.',
                'data'    => ['status' => 'paid'],
            ]);
        }

        $enrollment->update([
            'status'                   => 'paid',
            'paid_at'                  => Carbon::now(),
            'stripe_session_id'        => 'mock_session_' . $enrollment->id,
            'stripe_payment_intent_id' => 'mock_pi_' . $enrollment->id,
            'metadata'                 => array_merge($enrollment->metadata ?? [], [
                'mock_paid_at' => Carbon::now()->toIso8601String(),
                'simulated'    => true,
            ]),
        ]);

        Log::info('Mock checkout confirmado', ['enrollment_id' => $enrollment->id]);

        return response()->json([
            'success' => true,
            'message' => 'Pagamento simulado com sucesso.',
            'data'    => [
                'status'  => 'paid',
                'paid_at' => $enrollment->paid_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Caminho relativo do checkout. Front faz window.location.href e o browser
     * resolve no próprio domínio — não depende de FRONTEND_URL.
     */
    public static function checkoutPath(EventEnrollment $enrollment): string
    {
        $token = self::tokenFor($enrollment);
        return "/checkout/{$enrollment->id}?t={$token}";
    }

    /**
     * URL absoluta — só usar quando o destino precisa ser fora (RD redirect).
     */
    public static function checkoutUrl(EventEnrollment $enrollment): string
    {
        $base = config('app.frontend_url') ?: config('app.url');
        return rtrim($base, '/') . self::checkoutPath($enrollment);
    }

    public static function tokenFor(EventEnrollment $enrollment): string
    {
        $payload = $enrollment->id . '|' . $enrollment->amount_cents . '|' . $enrollment->created_at?->toIso8601String();
        return hash_hmac('sha256', $payload, config('app.key'));
    }

    private function assertToken(Request $request, EventEnrollment $enrollment): void
    {
        $expected = self::tokenFor($enrollment);
        $given    = $request->query('t', '');
        if (!hash_equals($expected, $given)) {
            abort(403, 'Token inválido');
        }
    }
}
