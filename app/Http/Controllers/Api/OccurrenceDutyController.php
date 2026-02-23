<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Occurrence;
use App\Models\OccurrenceDuty;
use App\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OccurrenceDutyController extends Controller
{
    public function store(Request $request, Schedule $schedule, Occurrence $occurrence)
    {
        $this->authorizeOccurrence($schedule, $occurrence);

        $validated = $request->validate([
            'member_id'   => ['required', 'integer', Rule::exists('members', 'id')->whereNull('deleted_at')],
            'ministry_id' => ['required', 'integer', Rule::exists('ministries', 'id')->whereNull('deleted_at')],
            'role'        => ['nullable', 'string', 'max:100'],
        ]);

        // Valida que o ministério pertence ao schedule
        $scheduleMinistries = $schedule->ministries ?? [];
        if (!in_array($validated['ministry_id'], $scheduleMinistries)) {
            return response()->json([
                'success' => false,
                'message' => 'O ministério informado não faz parte deste evento.',
            ], 422);
        }

        // Valida que o membro pertence ao ministério
        $memberBelongsToMinistry = DB::table('ministry_member')
            ->where('ministry_id', $validated['ministry_id'])
            ->where('member_id', $validated['member_id'])
            ->exists();

        if (!$memberBelongsToMinistry) {
            return response()->json([
                'success' => false,
                'message' => 'O membro informado não pertence a este ministério.',
            ], 422);
        }

        $duty = $occurrence->duties()->create($validated);

        // Recarrega a ocorrência com todas as escalas para o frontend
        $occurrence->load(['duties.member', 'duties.ministry']);

        return response()->json([
            'success' => true,
            'message' => 'Membro escalado com sucesso',
            'data'    => $occurrence,
        ], 201);
    }

    public function destroy(Schedule $schedule, Occurrence $occurrence, OccurrenceDuty $duty)
    {
        $this->authorizeOccurrence($schedule, $occurrence);

        if ($duty->occurrence_id !== $occurrence->id) {
            abort(404);
        }

        $duty->delete();

        return response()->json([
            'success' => true,
            'message' => 'Escala removida com sucesso',
        ]);
    }

    private function authorizeOccurrence(Schedule $schedule, Occurrence $occurrence): void
    {
        if ($occurrence->schedule_id !== $schedule->id) {
            abort(404);
        }
    }
}
