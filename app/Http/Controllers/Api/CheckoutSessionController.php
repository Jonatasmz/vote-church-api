<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventEnrollment;
use App\Services\CheckoutTokenService;
use App\Services\StripeCheckoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class CheckoutSessionController extends Controller
{
    /**
     * Dados do checkout para a tela intermediária do front.
     *
     * Com Stripe configurado e inscrição pendente, cria a Checkout Session e
     * devolve `stripe_url` — o front redireciona pro checkout hospedado. Sem
     * chave, devolve os dados para a tela de checkout mock (dev local).
     */
    public function show(Request $request, EventEnrollment $enrollment)
    {
        CheckoutTokenService::assertToken($request, $enrollment);
        $enrollment->load('schedule:id,name,date,end_date,price,installments,time');

        $stripeUrl = null;
        if (StripeCheckoutService::enabled() && $enrollment->status === 'pending') {
            try {
                $stripeUrl = app(StripeCheckoutService::class)->checkoutUrl($enrollment);
            } catch (\Throwable $e) {
                Log::error('Falha ao criar Stripe Checkout Session', [
                    'enrollment_id' => $enrollment->id,
                    'msg' => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Não foi possível iniciar o pagamento. Tente novamente em instantes.',
                ], 502);
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'enrollment_id' => $enrollment->id,
                'name' => $enrollment->name,
                'email' => $enrollment->email,
                'amount_cents' => $enrollment->amount_cents,
                'status' => $enrollment->status,
                'installments' => $enrollment->schedule?->installments,
                'stripe_url' => $stripeUrl,
                'event' => [
                    'name' => $enrollment->schedule?->name,
                    'date' => $enrollment->schedule?->date?->format('Y-m-d'),
                    'end_date' => $enrollment->schedule?->end_date?->format('Y-m-d'),
                    'time' => substr($enrollment->schedule?->time ?? '', 0, 5),
                ],
            ],
        ]);
    }

    /**
     * Status atual da inscrição — usado pela tela de retorno do Stripe, que
     * consulta em vez de confiar no query param `status` (a confirmação real
     * chega pelo webhook, que pode ser assíncrono).
     */
    public function status(Request $request, EventEnrollment $enrollment)
    {
        CheckoutTokenService::assertToken($request, $enrollment);

        return response()->json([
            'success' => true,
            'data' => [
                'enrollment_id' => $enrollment->id,
                'status' => $enrollment->status,
                'paid_at' => $enrollment->paid_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Confirmação MOCK de pagamento — só para dev local sem Stripe. Em produção
     * quem marca `paid` é o webhook do Stripe.
     */
    public function confirm(Request $request, EventEnrollment $enrollment)
    {
        CheckoutTokenService::assertToken($request, $enrollment);

        if (StripeCheckoutService::enabled()) {
            abort(404);
        }

        if ($enrollment->status === 'paid') {
            return response()->json([
                'success' => true,
                'message' => 'Inscrição já paga.',
                'data' => ['status' => 'paid'],
            ]);
        }

        $enrollment->update([
            'status' => 'paid',
            'paid_at' => Carbon::now(),
            'stripe_session_id' => 'mock_session_'.$enrollment->id,
            'stripe_payment_intent_id' => 'mock_pi_'.$enrollment->id,
            'metadata' => array_merge($enrollment->metadata ?? [], [
                'mock_paid_at' => Carbon::now()->toIso8601String(),
                'simulated' => true,
            ]),
        ]);

        Log::info('Mock checkout confirmado', ['enrollment_id' => $enrollment->id]);

        return response()->json([
            'success' => true,
            'message' => 'Pagamento simulado com sucesso.',
            'data' => [
                'status' => 'paid',
                'paid_at' => $enrollment->paid_at->toIso8601String(),
            ],
        ]);
    }
}
