<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Occurrence;
use App\Models\Schedule;
use Illuminate\Http\Request;

class OccurrenceController extends Controller
{
    public function index(Schedule $schedule)
    {
        $occurrences = $schedule->occurrences()
            ->with(['duties.member', 'duties.ministry'])
            ->orderBy('date')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $occurrences,
        ]);
    }

    public function store(Request $request, Schedule $schedule)
    {
        $validated = $request->validate([
            'date'  => ['required', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        // Valida que a data bate com o dia da semana do schedule recorrente
        if ($schedule->type === 'recurring') {
            $dayOfWeek = \Carbon\Carbon::parse($validated['date'])->dayOfWeek;
            if ($dayOfWeek !== $schedule->day_of_week) {
                $dayNames = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
                return response()->json([
                    'success' => false,
                    'message' => "A data informada não é uma {$dayNames[$schedule->day_of_week]}.",
                ], 422);
            }
        }

        $occurrence = $schedule->occurrences()->create($validated);
        $occurrence->load(['duties.member', 'duties.ministry']);

        return response()->json([
            'success' => true,
            'message' => 'Ocorrência criada com sucesso',
            'data'    => $occurrence,
        ], 201);
    }

    public function show(Schedule $schedule, Occurrence $occurrence)
    {
        $this->authorizeOccurrence($schedule, $occurrence);

        $occurrence->load(['duties.member', 'duties.ministry', 'schedule']);

        return response()->json([
            'success' => true,
            'data'    => $occurrence,
        ]);
    }

    public function update(Request $request, Schedule $schedule, Occurrence $occurrence)
    {
        $this->authorizeOccurrence($schedule, $occurrence);

        $validated = $request->validate([
            'date'  => ['sometimes', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $occurrence->update($validated);
        $occurrence->load(['duties.member', 'duties.ministry']);

        return response()->json([
            'success' => true,
            'message' => 'Ocorrência atualizada com sucesso',
            'data'    => $occurrence,
        ]);
    }

    public function destroy(Schedule $schedule, Occurrence $occurrence)
    {
        $this->authorizeOccurrence($schedule, $occurrence);

        $occurrence->delete();

        return response()->json([
            'success' => true,
            'message' => 'Ocorrência removida com sucesso',
        ]);
    }

    private function authorizeOccurrence(Schedule $schedule, Occurrence $occurrence): void
    {
        if ($occurrence->schedule_id !== $schedule->id) {
            abort(404);
        }
    }
}
