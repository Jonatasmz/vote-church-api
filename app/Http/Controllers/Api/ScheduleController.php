<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ministry;
use App\Models\Occurrence;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ScheduleController extends Controller
{
    /**
     * Resolve o array de IDs de ministérios para objetos { id, name }.
     */
    private function resolveMinistries(Schedule $schedule): Schedule
    {
        $ids = $schedule->ministries ?? [];

        if (count($ids) > 0) {
            $schedule->ministries = Ministry::whereIn('id', $ids)
                ->whereNull('deleted_at')
                ->get(['id', 'name'])
                ->toArray();
        } else {
            $schedule->ministries = [];
        }

        return $schedule;
    }

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
            ->get()
            ->map(fn (Schedule $s) => $this->resolveMinistries($s));

        return response()->json([
            'success' => true,
            'data' => $schedules,
        ]);
    }

    public function store(Request $request)
    {
        $request->merge([
            'ministries' => array_values(array_filter($request->input('ministries', []), fn ($v) => $v !== null)),
            'time'       => substr($request->input('time', ''), 0, 5),
        ]);

        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:100'],
            'type'           => ['required', Rule::in(['recurring', 'single'])],
            'time'           => ['required', 'date_format:H:i'],
            'day_of_week'    => ['required_if:type,recurring', 'nullable', 'integer', 'min:0', 'max:6'],
            'date'           => ['required_if:type,single', 'nullable', 'date_format:Y-m-d'],
            'end_date'       => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date'],
            'description'    => ['nullable', 'string', 'max:255'],
            'ministries'     => ['nullable', 'array'],
            'ministries.*'   => ['integer', Rule::exists('ministries', 'id')->whereNull('deleted_at')],
            'is_paid'        => ['boolean'],
            'price'          => ['nullable', 'numeric', 'min:0', 'required_if:is_paid,true'],
            'installments'   => ['nullable', 'integer', 'min:1', 'max:36'],
            'info_url'          => ['nullable', 'url', 'max:500'],
            'allow_non_members' => ['boolean'],
            'rd_station_enabled' => ['boolean'],
        ]);

        if (!empty($validated['allow_non_members']) && empty($validated['info_url'])) {
            throw ValidationException::withMessages([
                'info_url' => 'Link com mais informações é obrigatório quando o evento é público.',
            ]);
        }

        // RD Station pressupõe visitantes: só faz sentido com allow_non_members.
        $validated['rd_station_enabled'] = !empty($validated['allow_non_members']) && !empty($validated['rd_station_enabled']);

        $this->ensurePaymentAllowed($validated);

        $schedule = Schedule::create($validated);

        if ($schedule->type === 'single') {
            $this->createOccurrencesForRange($schedule);
        }

        return response()->json([
            'success' => true,
            'message' => 'Evento criado com sucesso',
            'data'    => $this->resolveMinistries($schedule),
        ], 201);
    }

    private function ensurePaymentAllowed(array $data): void
    {
        if (($data['type'] ?? null) === 'recurring' && !empty($data['is_paid'])) {
            throw ValidationException::withMessages([
                'is_paid' => 'Eventos recorrentes não suportam pagamento.',
            ]);
        }
    }

    private function createOccurrencesForRange(Schedule $schedule): void
    {
        if (!$schedule->date) {
            return;
        }
        $start = Carbon::parse($schedule->date->format('Y-m-d'));
        $end   = $schedule->end_date ? Carbon::parse($schedule->end_date->format('Y-m-d')) : $start;

        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            Occurrence::firstOrCreate([
                'schedule_id' => $schedule->id,
                'date'        => $d->format('Y-m-d'),
            ]);
        }
    }

    public function show(Schedule $schedule)
    {
        return response()->json([
            'success' => true,
            'data'    => $this->resolveMinistries($schedule),
        ]);
    }

    public function update(Request $request, Schedule $schedule)
    {
        $request->merge([
            'ministries' => array_values(array_filter($request->input('ministries', []), fn ($v) => $v !== null)),
            'time'       => substr($request->input('time', ''), 0, 5),
        ]);

        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:100'],
            'type'           => ['required', Rule::in(['recurring', 'single'])],
            'time'           => ['required', 'date_format:H:i'],
            'day_of_week'    => ['required_if:type,recurring', 'nullable', 'integer', 'min:0', 'max:6'],
            'date'           => ['required_if:type,single', 'nullable', 'date_format:Y-m-d'],
            'end_date'       => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date'],
            'description'    => ['nullable', 'string', 'max:255'],
            'ministries'     => ['nullable', 'array'],
            'ministries.*'   => ['integer', Rule::exists('ministries', 'id')->whereNull('deleted_at')],
            'is_paid'        => ['boolean'],
            'price'          => ['nullable', 'numeric', 'min:0', 'required_if:is_paid,true'],
            'installments'   => ['nullable', 'integer', 'min:1', 'max:36'],
            'info_url'          => ['nullable', 'url', 'max:500'],
            'allow_non_members' => ['boolean'],
            'rd_station_enabled' => ['boolean'],
        ]);

        if (!empty($validated['allow_non_members']) && empty($validated['info_url'])) {
            throw ValidationException::withMessages([
                'info_url' => 'Link com mais informações é obrigatório quando o evento é público.',
            ]);
        }

        // RD Station pressupõe visitantes: só faz sentido com allow_non_members.
        if (array_key_exists('rd_station_enabled', $validated) || array_key_exists('allow_non_members', $validated)) {
            $allow = $validated['allow_non_members'] ?? $schedule->allow_non_members;
            $validated['rd_station_enabled'] = (bool) $allow && !empty($validated['rd_station_enabled']);
        }

        $this->ensurePaymentAllowed($validated);

        $schedule->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Evento atualizado com sucesso',
            'data'    => $this->resolveMinistries($schedule),
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
