<?php

namespace App\Services;

use App\Models\EventEnrollment;
use Stripe\Checkout\Session;
use Stripe\StripeClient;

/**
 * Cria a Stripe Checkout Session (checkout hospedado) para uma inscrição.
 *
 * Só entra em ação quando há STRIPE_SECRET configurado. Sem chave, o fluxo
 * cai no checkout mock (CheckoutSessionController) — dev local segue rodando.
 */
class StripeCheckoutService
{
    public static function enabled(): bool
    {
        return (bool) config('services.stripe.secret');
    }

    private function client(): StripeClient
    {
        return new StripeClient(config('services.stripe.secret'));
    }

    /**
     * URL do Stripe Checkout para a inscrição. Cria uma nova session e guarda
     * o id no enrollment. Chamada só para inscrições pending.
     */
    public function checkoutUrl(EventEnrollment $enrollment): string
    {
        $enrollment->loadMissing('schedule:id,name');

        $front = rtrim(config('app.frontend_url') ?: config('app.url'), '/');
        $token = CheckoutTokenService::tokenFor($enrollment);
        $return = "{$front}/pagamento/{$enrollment->id}?t={$token}";

        $session = $this->client()->checkout->sessions->create([
            'mode' => 'payment',
            'locale' => 'pt-BR',
            'customer_email' => $enrollment->email ?: null,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => 'brl',
                    'unit_amount' => $enrollment->amount_cents,
                    'product_data' => [
                        'name' => $enrollment->schedule?->name ?? 'Inscrição em evento',
                    ],
                ],
            ]],
            // enrollment_id na session E no payment_intent: o webhook lê de qualquer um.
            'metadata' => ['enrollment_id' => (string) $enrollment->id],
            'payment_intent_data' => [
                'metadata' => ['enrollment_id' => (string) $enrollment->id],
            ],
            'success_url' => "{$return}&status=sucesso",
            'cancel_url' => "{$return}&status=cancelado",
        ]);

        $enrollment->update(['stripe_session_id' => $session->id]);

        return $session->url;
    }
}
