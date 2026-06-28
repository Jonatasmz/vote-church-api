<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventEnrollment;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class StripeWebhookController extends Controller
{
    /**
     * Handler único pra eventos Stripe (membros e externos).
     * Por enquanto sem verificação de assinatura — entra quando integrar SDK.
     */
    public function handle(Request $request)
    {
        $event = $request->all();
        $type  = $event['type'] ?? null;

        if ($type !== 'checkout.session.completed') {
            return response()->json(['received' => true]);
        }

        $session = $event['data']['object'] ?? [];
        $metadata = $session['metadata'] ?? [];
        $enrollmentId = $metadata['enrollment_id'] ?? null;

        if (!$enrollmentId) {
            Log::warning('Stripe webhook sem enrollment_id', ['session_id' => $session['id'] ?? null]);
            return response()->json(['received' => true]);
        }

        $enrollment = EventEnrollment::find($enrollmentId);
        if (!$enrollment) {
            Log::warning('Stripe webhook enrollment não encontrado', ['enrollment_id' => $enrollmentId]);
            return response()->json(['received' => true]);
        }

        $enrollment->update([
            'status'                   => 'paid',
            'paid_at'                  => Carbon::now(),
            'stripe_session_id'        => $session['id'] ?? null,
            'stripe_payment_intent_id' => $session['payment_intent'] ?? null,
            'metadata'                 => array_merge($enrollment->metadata ?? [], [
                'webhook_received_at' => Carbon::now()->toIso8601String(),
            ]),
        ]);

        return response()->json(['received' => true]);
    }
}
