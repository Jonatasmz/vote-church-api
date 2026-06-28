<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\CheckoutSessionController;
use App\Models\EventEnrollment;
use App\Models\Member;
use App\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EventEnrollmentController extends Controller
{
    /**
     * Membro logado se inscreve no evento (cria enrollment pending).
     */
    public function enroll(Request $request, Schedule $schedule)
    {
        $request->validate([
            'member_id' => ['required', 'integer', 'exists:members,id'],
        ]);

        if (!$schedule->is_paid) {
            throw ValidationException::withMessages([
                'schedule' => 'Este evento não é pago.',
            ]);
        }

        $member = Member::findOrFail($request->member_id);

        $existing = EventEnrollment::where('schedule_id', $schedule->id)
            ->where('member_id', $member->id)
            ->whereIn('status', ['pending', 'paid'])
            ->first();

        if ($existing) {
            return response()->json([
                'success' => true,
                'message' => $existing->status === 'paid' ? 'Já inscrito e pago.' : 'Inscrição já registrada, pagamento pendente.',
                'data'    => [
                    'enrollment'   => $this->serialize($existing),
                    'checkout_url' => $existing->status === 'paid' ? null : CheckoutSessionController::checkoutUrl($existing),
                ],
            ]);
        }

        $amountCents = (int) round(((float) $schedule->price) * 100);

        $enrollment = EventEnrollment::create([
            'schedule_id'  => $schedule->id,
            'member_id'    => $member->id,
            'name'         => $member->name,
            'email'        => $member->email ?? '',
            'cpf'          => $member->cpf,
            'phone'        => null,
            'status'       => 'pending',
            'source'       => 'member',
            'amount_cents' => $amountCents,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Inscrição criada. Conduza ao checkout.',
            'data'    => [
                'enrollment'   => $this->serialize($enrollment),
                'checkout_url' => CheckoutSessionController::checkoutUrl($enrollment),
            ],
        ], 201);
    }

    /**
     * Status da inscrição do membro num evento.
     */
    public function show(Request $request, Schedule $schedule)
    {
        $request->validate([
            'member_id' => ['required', 'integer', 'exists:members,id'],
        ]);

        $enrollment = EventEnrollment::where('schedule_id', $schedule->id)
            ->where('member_id', $request->member_id)
            ->latest('id')
            ->first();

        return response()->json([
            'success' => true,
            'data'    => $enrollment ? $this->serialize($enrollment) : null,
        ]);
    }

    private function serialize(EventEnrollment $e): array
    {
        return [
            'id'           => $e->id,
            'status'       => $e->status,
            'source'       => $e->source,
            'amount_cents' => $e->amount_cents,
            'paid_at'      => $e->paid_at?->toIso8601String(),
        ];
    }
}
