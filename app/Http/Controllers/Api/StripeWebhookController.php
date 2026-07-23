<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventEnrollment;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    /**
     * Recebe eventos do Stripe. Verifica a assinatura antes de qualquer coisa —
     * sem STRIPE_WEBHOOK_SECRET configurado, recusa (não dá pra confiar no corpo).
     */
    public function handle(Request $request)
    {
        $secret = config('services.stripe.webhook_secret');
        if (! $secret) {
            Log::warning('Stripe webhook recebido sem STRIPE_WEBHOOK_SECRET configurado');

            return response()->json(['error' => 'webhook não configurado'], 400);
        }

        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                $request->header('Stripe-Signature', ''),
                $secret
            );
        } catch (SignatureVerificationException $e) {
            Log::warning('Stripe webhook com assinatura inválida', ['msg' => $e->getMessage()]);

            return response()->json(['error' => 'assinatura inválida'], 400);
        } catch (\UnexpectedValueException $e) {
            return response()->json(['error' => 'payload inválido'], 400);
        }

        if ($event->type === 'checkout.session.completed') {
            $this->markPaid($event->data->object);
        }

        return response()->json(['received' => true]);
    }

    private function markPaid(object $session): void
    {
        $enrollmentId = $session->metadata->enrollment_id ?? null;
        if (! $enrollmentId) {
            Log::warning('Stripe webhook sem enrollment_id', ['session_id' => $session->id ?? null]);

            return;
        }

        $enrollment = EventEnrollment::find($enrollmentId);
        if (! $enrollment) {
            Log::warning('Stripe webhook: inscrição não encontrada', ['enrollment_id' => $enrollmentId]);

            return;
        }

        // Idempotente: o Stripe pode reenviar o mesmo evento.
        if ($enrollment->status === 'paid') {
            return;
        }

        $enrollment->update([
            'status' => 'paid',
            'paid_at' => Carbon::now(),
            'stripe_session_id' => $session->id ?? $enrollment->stripe_session_id,
            'stripe_payment_intent_id' => $session->payment_intent ?? null,
            'metadata' => array_merge($enrollment->metadata ?? [], [
                'webhook_received_at' => Carbon::now()->toIso8601String(),
            ]),
        ]);

        Log::info('Inscrição paga via Stripe', ['enrollment_id' => $enrollment->id]);
    }
}
