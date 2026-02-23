<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ministry;
use App\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ScheduleController extends Controller
{
    public function index(Request $request)
    {
        $query = Schedule::query();

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $schedules = $query
            ->orderByRaw("CASE type WHEN 'recurring' THEN 0 ELSE 1 END")
            ->orderBy('day_of_week')
            ->orderBy('date')
            ->orderBy('time')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $schedules,
        ]);
    }

    public function store(Request $request)
    {
        $request->merge([
            'ministries' => array_values(array_filter($request->input('ministries', []), fn ($v) => $v !== null)),
        ]);

        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:100'],
            'type'           => ['required', Rule::in(['recurring', 'single'])],
            'time'           => ['required', 'date_format:H:i'],
            'day_of_week'    => ['required_if:type,recurring', 'nullable', 'integer', 'min:0', 'max:6'],
            'date'           => ['required_if:type,single', 'nullable', 'date_format:Y-m-d'],
            'description'    => ['nullable', 'string', 'max:255'],
            'ministries'     => ['nullable', 'array'],
            'ministries.*'   => ['integer', Rule::exists('ministries', 'id')->whereNull('deleted_at')],
        ]);

        $schedule = Schedule::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Evento criado com sucesso',
            'data'    => $schedule,
        ], 201);
    }

    public function show(Schedule $schedule)
    {
        return response()->json([
            'success' => true,
            'data'    => $schedule,
        ]);
    }

    public function update(Request $request, Schedule $schedule)
    {
        $request->merge([
            'ministries' => array_values(array_filter($request->input('ministries', []), fn ($v) => $v !== null)),
        ]);

        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:100'],
            'type'           => ['required', Rule::in(['recurring', 'single'])],
            'time'           => ['required', 'date_format:H:i'],
            'day_of_week'    => ['required_if:type,recurring', 'nullable', 'integer', 'min:0', 'max:6'],
            'date'           => ['required_if:type,single', 'nullable', 'date_format:Y-m-d'],
            'description'    => ['nullable', 'string', 'max:255'],
            'ministries'     => ['nullable', 'array'],
            'ministries.*'   => ['integer', Rule::exists('ministries', 'id')->whereNull('deleted_at')],
        ]);

        $schedule->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Evento atualizado com sucesso',
            'data'    => $schedule,
        ]);
    }

    public function destroy(Schedule $schedule)
    {
        $schedule->delete();

        return response()->json([
            'success' => true,
            'message' => 'Evento removido com sucesso',
        ]);
    }
}
