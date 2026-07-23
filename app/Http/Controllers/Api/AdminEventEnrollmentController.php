<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventEnrollment;
use App\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdminEventEnrollmentController extends Controller
{
    public function destroy(Schedule $schedule, EventEnrollment $enrollment)
    {
        if ($enrollment->schedule_id !== $schedule->id) {
            throw ValidationException::withMessages([
                'enrollment' => 'Inscrição não pertence a este evento.',
            ]);
        }

        $enrollment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Inscrição removida.',
        ]);
    }

    /**
     * Lista inscrições de um evento + resumo.
     */
    public function index(Request $request, Schedule $schedule)
    {
        $query = EventEnrollment::where('schedule_id', $schedule->id);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('source')) {
            $query->where('source', $request->source);
        }

        $enrollments = $query->orderByDesc('id')->get();

        $summary = [
            'total'      => $enrollments->count(),
            'paid'       => $enrollments->where('status', 'paid')->count(),
            'pending'    => $enrollments->where('status', 'pending')->count(),
            'canceled'   => $enrollments->whereIn('status', ['canceled', 'refunded'])->count(),
            'revenue'    => $enrollments->where('status', 'paid')->sum('amount_cents'),
            'by_source'  => [
                'member'   => $enrollments->where('source', 'member')->count(),
                'external' => $enrollments->where('source', 'external')->count(),
            ],
        ];

        return response()->json([
            'success' => true,
            'data'    => [
                'schedule' => [
                    'id'                => $schedule->id,
                    'name'              => $schedule->name,
                    'date'              => $schedule->date?->format('Y-m-d'),
                    'end_date'          => $schedule->end_date?->format('Y-m-d'),
                    'price'             => $schedule->price,
                    'installments'      => $schedule->installments,
                    'is_paid'           => (bool) $schedule->is_paid,
                    'allow_non_members' => (bool) $schedule->allow_non_members,
                    'rd_station_enabled' => (bool) $schedule->rd_station_enabled,
                    'info_url'          => $schedule->info_url,
                ],
                'summary'     => $summary,
                'enrollments' => $enrollments->map(fn (EventEnrollment $e) => [
                    'id'           => $e->id,
                    'name'         => $e->name,
                    'email'        => $e->email,
                    'cpf'          => $e->cpf,
                    'phone'        => $e->phone,
                    'status'       => $e->status,
                    'source'       => $e->source,
                    'amount_cents' => $e->amount_cents,
                    'member_id'    => $e->member_id,
                    'paid_at'      => $e->paid_at?->toIso8601String(),
                    'created_at'   => $e->created_at?->toIso8601String(),
                ])->values(),
            ],
        ]);
    }
}
