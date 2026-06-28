<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\CheckoutSessionController;
use App\Models\EventEnrollment;
use App\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EventCheckoutController extends Controller
{
    /**
     * Endpoint público chamado pelo RD Station após captura.
     * Cria enrollment pending e devolve URL pro Stripe Checkout.
     */
    public function externalCheckout(Request $request, Schedule $schedule)
    {
        if (!$schedule->is_paid || !$schedule->allow_non_members) {
            throw ValidationException::withMessages([
                'schedule' => 'Este evento não aceita inscrições externas.',
            ]);
        }

        $validated = $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'cpf'   => ['nullable', 'string', 'max:14'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $cpfDigits = $validated['cpf']
            ? preg_replace('/[^0-9]/', '', $validated['cpf'])
            : null;

        if ($cpfDigits) {
            $paid = EventEnrollment::where('schedule_id', $schedule->id)
                ->where('cpf', $cpfDigits)
                ->where('status', 'paid')
                ->exists();
            if ($paid) {
                return response()->json([
                    'success' => false,
                    'message' => 'CPF já inscrito e pago neste evento.',
                ], 409);
            }
        }

        $amountCents = (int) round(((float) $schedule->price) * 100);

        $enrollment = EventEnrollment::create([
            'schedule_id'  => $schedule->id,
            'member_id'    => null,
            'name'         => $validated['name'],
            'email'        => $validated['email'],
            'cpf'          => $cpfDigits,
            'phone'        => $validated['phone'] ?? null,
            'status'       => 'pending',
            'source'       => 'external',
            'amount_cents' => $amountCents,
            'metadata'     => ['origin' => 'rd-station'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Inscrição criada. Conduza ao checkout.',
            'data'    => [
                'enrollment_id' => $enrollment->id,
                'checkout_url'  => CheckoutSessionController::checkoutUrl($enrollment),
            ],
        ], 201);
    }
}
