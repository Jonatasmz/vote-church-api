<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\CheckoutSessionController;
use App\Models\EventEnrollment;
use App\Models\Member;
use App\Models\Schedule;
use App\Services\EventEnrollmentService;
use Illuminate\Http\Request;

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

        $member = Member::findOrFail($request->member_id);

        $enrollment = EventEnrollmentService::forMember($schedule, $member);

        if (!$enrollment->wasRecentlyCreated) {
            return response()->json([
                'success' => true,
                'message' => $enrollment->status === 'paid' ? 'Já inscrito e pago.' : 'Inscrição já registrada, pagamento pendente.',
                'data'    => [
                    'enrollment'   => $this->serialize($enrollment),
                    'checkout_url' => $enrollment->status === 'paid' ? null : CheckoutSessionController::checkoutPath($enrollment),
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Inscrição criada. Conduza ao checkout.',
            'data'    => [
                'enrollment'   => $this->serialize($enrollment),
                'checkout_url' => CheckoutSessionController::checkoutPath($enrollment),
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
