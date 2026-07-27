<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ministry;
use App\Models\Occurrence;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
            'description'    => ['nullable', 'string', 'max:5000'],
            'ministries'     => ['nullable', 'array'],
            'ministries.*'   => ['integer', Rule::exists('ministries', 'id')->whereNull('deleted_at')],
            'is_paid'        => ['boolean'],
            'price'          => ['nullable', 'numeric', 'min:0', 'required_if:is_paid,true'],
            'installments'   => ['nullable', 'integer', 'min:1', 'max:36'],
            'info_url'          => ['nullable', 'url', 'max:500'],
            'allow_non_members' => ['boolean'],
            'rd_station_enabled' => ['boolean'],
        ]);

        // RD Station pressupõe visitantes: só faz sentido com allow_non_members.
        $validated['rd_station_enabled'] = !empty($validated['allow_non_members']) && !empty($validated['rd_station_enabled']);

        $this->ensurePaymentAllowed($validated);

        $schedule = Schedule::create($validated);

        if ($schedule->type === 'single') {
            $this->syncOccurrencesForRange($schedule);
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

    /**
     * Garante que as ocorrências do evento avulso batem com o range [date, end_date].
     * Cria as que faltam e remove as que ficaram fora do range — mas só se não
     * tiverem escala, pra não perder duties já montadas.
     */
    private function syncOccurrencesForRange(Schedule $schedule): void
    {
        if (!$schedule->date) {
            return;
        }
        $start = Carbon::parse($schedule->date->format('Y-m-d'));
        $end   = $schedule->end_date ? Carbon::parse($schedule->end_date->format('Y-m-d')) : $start;

        $range = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $day = $d->format('Y-m-d');
            $range[] = $day;
            Occurrence::firstOrCreate([
                'schedule_id' => $schedule->id,
                'date'        => $day,
            ]);
        }

        Occurrence::where('schedule_id', $schedule->id)
            ->whereNotIn('date', $range)
            ->whereDoesntHave('duties')
            ->delete();
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
            'description'    => ['nullable', 'string', 'max:5000'],
            'ministries'     => ['nullable', 'array'],
            'ministries.*'   => ['integer', Rule::exists('ministries', 'id')->whereNull('deleted_at')],
            'is_paid'        => ['boolean'],
            'price'          => ['nullable', 'numeric', 'min:0', 'required_if:is_paid,true'],
            'installments'   => ['nullable', 'integer', 'min:1', 'max:36'],
            'info_url'          => ['nullable', 'url', 'max:500'],
            'allow_non_members' => ['boolean'],
            'rd_station_enabled' => ['boolean'],
        ]);

        // RD Station pressupõe visitantes: só faz sentido com allow_non_members.
        if (array_key_exists('rd_station_enabled', $validated) || array_key_exists('allow_non_members', $validated)) {
            $allow = $validated['allow_non_members'] ?? $schedule->allow_non_members;
            $validated['rd_station_enabled'] = (bool) $allow && !empty($validated['rd_station_enabled']);
        }

        $this->ensurePaymentAllowed($validated);

        $schedule->update($validated);

        if ($schedule->type === 'single') {
            $this->syncOccurrencesForRange($schedule);
        }

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

    /**
     * Upload da imagem do evento (opcional). Substitui a anterior se houver.
     */
    public function uploadImage(Request $request, Schedule $schedule)
    {
        $request->validate([
            'image' => ['required', 'file', 'mimes:jpeg,png,jpg,webp', 'max:8192'],
        ]);

        $this->deleteStoredImage($schedule);

        $path = $request->file('image')->store('schedules', 'public');
        $schedule->update(['image' => $path]);

        return response()->json([
            'success' => true,
            'data'    => ['image' => $schedule->image],
        ]);
    }

    /**
     * Remove a imagem do evento.
     */
    public function deleteImage(Schedule $schedule)
    {
        $this->deleteStoredImage($schedule);
        $schedule->update(['image' => null]);

        return response()->json([
            'success' => true,
            'message' => 'Imagem removida.',
        ]);
    }

    private function deleteStoredImage(Schedule $schedule): void
    {
        $raw = $schedule->getRawOriginal('image');
        if (! $raw) {
            return;
        }
        $path = ltrim(preg_replace('#^/?storage/#', '', $raw), '/');
        if (str_starts_with($path, 'schedules/')) {
            Storage::disk('public')->delete($path);
        }
    }
}
