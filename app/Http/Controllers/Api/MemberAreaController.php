<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\Ministry;
use Illuminate\Support\Facades\Storage;
use App\Models\MemberMinistryRequest;
use App\Models\Occurrence;
use App\Models\OccurrenceDuty;
use Illuminate\Http\Request;

class MemberAreaController extends Controller
{
    /**
     * Login por CPF — retorna dados básicos do membro.
     */
    public function login(Request $request)
    {
        $request->validate([
            'cpf' => ['required', 'string'],
        ]);

        $cpf = preg_replace('/[^0-9]/', '', $request->cpf);

        $member = Member::where(function ($q) use ($cpf, $request) {
            $q->where('cpf', $request->cpf)
              ->orWhere('cpf', $cpf);
        })
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->first();

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'CPF não encontrado ou membro inativo.',
            ], 404);
        }

        $member->load('ministries:id,name');

        return response()->json([
            'success' => true,
            'data'    => [
                'id'         => $member->id,
                'name'       => $member->name,
                'cpf'        => $member->cpf,
                'photo'      => $member->photo,
                'ministries' => $member->ministries,
            ],
        ]);
    }

    /**
     * Auto-cadastro de membro pelo CPF (público, sem JWT).
     */
    public function register(Request $request)
    {
        $request->validate([
            'cpf'         => ['required', 'string'],
            'name'        => ['required', 'string', 'max:255'],
            'rg'          => ['nullable', 'string', 'max:20'],
            'birth_date'  => ['nullable', 'date'],
            'description' => ['nullable', 'string'],
            'photo'       => ['nullable', 'string'],
        ]);

        $cpfDigits = preg_replace('/[^0-9]/', '', $request->cpf);

        if (strlen($cpfDigits) !== 11) {
            return response()->json([
                'success' => false,
                'message' => 'CPF inválido.',
            ], 422);
        }

        $exists = Member::where('cpf', $request->cpf)
            ->orWhere('cpf', $cpfDigits)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'CPF já cadastrado. Faça login na área do membro.',
            ], 409);
        }

        $member = Member::create([
            'name'           => $request->name,
            'cpf'            => $cpfDigits,
            'rg'             => $request->rg,
            'birth_date'     => $request->birth_date,
            'description'    => $request->description,
            'photo'          => $request->photo,
            'member_since'   => null,
            'status'         => 'active',
            'pending_review' => true,
        ]);

        $member->load('ministries:id,name');

        return response()->json([
            'success' => true,
            'message' => 'Cadastro realizado com sucesso.',
            'data'    => [
                'id'         => $member->id,
                'name'       => $member->name,
                'cpf'        => $member->cpf,
                'photo'      => $member->photo,
                'ministries' => $member->ministries,
            ],
        ], 201);
    }

    /**
     * Retorna o perfil completo do membro (para a tela de atualização cadastral).
     */
    public function getProfile(Request $request)
    {
        $request->validate([
            'member_id' => ['required', 'integer', 'exists:members,id'],
        ]);

        $member = Member::with('ministries:id,name')
            ->findOrFail($request->member_id);

        $pendingRequests = MemberMinistryRequest::where('member_id', $member->id)
            ->where('status', 'pending')
            ->pluck('ministry_id');

        return response()->json([
            'success' => true,
            'data'    => [
                'id'               => $member->id,
                'name'             => $member->name,
                'cpf'              => $member->cpf,
                'rg'               => $member->rg,
                'birth_date'       => $member->birth_date?->format('Y-m-d'),
                'description'      => $member->description,
                'member_since'     => $member->member_since?->format('Y-m-d'),
                'photo'            => $member->photo,
                'status'           => $member->status,
                'ministries'       => $member->ministries,
                'pending_ministry_ids' => $pendingRequests,
            ],
        ]);
    }

    /**
     * Atualiza dados cadastrais do membro.
     */
    public function updateProfile(Request $request)
    {
        $request->validate([
            'member_id'   => ['required', 'integer', 'exists:members,id'],
            'name'        => ['sometimes', 'string', 'max:255'],
            'rg'          => ['sometimes', 'nullable', 'string', 'max:20'],
            'birth_date'  => ['sometimes', 'nullable', 'date'],
            'description' => ['sometimes', 'nullable', 'string'],
            'photo'       => ['sometimes', 'nullable', 'string'],
        ]);

        $member = Member::findOrFail($request->member_id);

        $member->update($request->only(['name', 'rg', 'birth_date', 'description', 'photo']));

        $member->load('ministries:id,name');

        return response()->json([
            'success' => true,
            'message' => 'Cadastro atualizado com sucesso.',
            'data'    => [
                'id'         => $member->id,
                'name'       => $member->name,
                'cpf'        => $member->cpf,
                'photo'      => $member->photo,
                'ministries' => $member->ministries,
            ],
        ]);
    }

    /**
     * Upload de foto via área do membro (sem JWT, exige member_id).
     */
    public function uploadPhoto(Request $request)
    {
        $request->validate([
            'member_id' => ['required', 'integer', 'exists:members,id'],
            'photo'     => ['required', 'file', 'mimes:jpeg,png,jpg,webp,heic,heif', 'max:8192'],
        ]);

        $member = Member::findOrFail($request->member_id);

        $rawPhoto = $member->getRawOriginal('photo');
        if ($rawPhoto) {
            $oldPath = ltrim(preg_replace('#^/?storage/#', '', $rawPhoto), '/');
            if (str_starts_with($oldPath, 'members/')) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        $path = $request->file('photo')->store('members', 'public');

        $member->update(['photo' => $path]);

        return response()->json([
            'success' => true,
            'data'    => ['photo' => $member->photo],
        ]);
    }

    /**
     * Aniversariantes do mês (visível para área do membro).
     */
    public function getBirthdays(Request $request)
    {
        $request->validate([
            'month' => ['nullable', 'integer', 'between:1,12'],
        ]);

        $month = (int) ($request->month ?? now()->month);

        $members = Member::whereNotNull('birth_date')
            ->whereMonth('birth_date', $month)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->get(['id', 'name', 'photo', 'birth_date'])
            ->sortBy(fn ($m) => $m->birth_date->format('d'))
            ->values()
            ->map(fn ($m) => [
                'id'    => $m->id,
                'name'  => $m->name,
                'photo' => $m->photo,
                'birth_date' => $m->birth_date->format('Y-m-d'),
                'day'   => (int) $m->birth_date->format('d'),
            ]);

        return response()->json([
            'success' => true,
            'data'    => $members,
        ]);
    }

    /**
     * Lista todos os ministérios disponíveis.
     */
    public function getMinistries()
    {
        $ministries = Ministry::orderBy('name')->get(['id', 'name']);

        return response()->json([
            'success' => true,
            'data'    => $ministries,
        ]);
    }

    /**
     * Envia solicitações de entrada em ministérios.
     */
    public function requestMinistries(Request $request)
    {
        $request->validate([
            'member_id'    => ['required', 'integer', 'exists:members,id'],
            'ministry_ids' => ['required', 'array'],
            'ministry_ids.*' => ['integer', 'exists:ministries,id'],
        ]);

        $member = Member::with('ministries:id')->findOrFail($request->member_id);
        $alreadyIn = $member->ministries->pluck('id')->toArray();

        $created = [];
        foreach ($request->ministry_ids as $ministryId) {
            if (in_array($ministryId, $alreadyIn)) {
                continue;
            }
            MemberMinistryRequest::updateOrCreate(
                ['member_id' => $member->id, 'ministry_id' => $ministryId],
                ['status' => 'pending']
            );
            $created[] = $ministryId;
        }

        return response()->json([
            'success' => true,
            'message' => 'Solicitação enviada. Aguarde aprovação do administrador.',
            'requested' => $created,
        ]);
    }

    /**
     * Retorna as programações (ocorrências) da igreja — visão geral para todos os membros.
     */
    public function getEvents(\Illuminate\Http\Request $request)
    {
        $memberId = $request->query('member_id');

        $occurrences = Occurrence::where('date', '>=', now()->subDays(7)->toDateString())
            ->whereNull('deleted_at')
            ->with([
                'schedule:id,name,type,time,end_date,is_paid,price,installments,info_url,allow_non_members',
                'duties.member:id,name',
                'duties.ministry:id,name',
            ])
            ->orderBy('date')
            ->get();

        $enrollmentsByScheduleId = [];
        if ($memberId) {
            $enrollmentsByScheduleId = \App\Models\EventEnrollment::where('member_id', $memberId)
                ->whereIn('schedule_id', $occurrences->pluck('schedule_id')->unique()->all())
                ->get()
                ->keyBy('schedule_id');
        }

        $data = $occurrences->map(function ($occ) use ($enrollmentsByScheduleId) {
            $byMinistry = $occ->duties->groupBy('ministry_id')->map(function ($duties) {
                $ministry = $duties->first()->ministry;
                return [
                    'ministry' => $ministry ? ['id' => $ministry->id, 'name' => $ministry->name] : null,
                    'members'  => $duties->map(fn ($d) => [
                        'name' => $d->member?->name,
                        'role' => $d->role,
                    ])->filter(fn ($m) => $m['name'])->values(),
                ];
            })->values();

            $enr = $enrollmentsByScheduleId[$occ->schedule_id] ?? null;

            return [
                'id'                => $occ->id,
                'date'              => $occ->date->format('Y-m-d'),
                'end_date'          => $occ->schedule->end_date?->format('Y-m-d'),
                'notes'             => $occ->notes,
                'is_paid'           => (bool) $occ->schedule->is_paid,
                'price'             => $occ->schedule->price,
                'installments'      => $occ->schedule->installments,
                'info_url'          => $occ->schedule->info_url,
                'allow_non_members' => (bool) $occ->schedule->allow_non_members,
                'enrollment'        => $enr ? [
                    'id'      => $enr->id,
                    'status'  => $enr->status,
                    'paid_at' => $enr->paid_at?->toIso8601String(),
                ] : null,
                'schedule'     => [
                    'id'   => $occ->schedule->id,
                    'name' => $occ->schedule->name,
                    'type' => $occ->schedule->type,
                    'time' => substr($occ->schedule->time, 0, 5),
                ],
                'ministries' => $byMinistry,
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    /**
     * Retorna as próximas escalas do membro (duties com ocorrência futura).
     */
    public function myDuties(Request $request)
    {
        $request->validate([
            'member_id' => ['required', 'integer', 'exists:members,id'],
        ]);

        $memberId = $request->member_id;

        $duties = OccurrenceDuty::where('member_id', $memberId)
            ->whereHas('occurrence', function ($q) {
                $q->where('date', '>=', now()->subDays(7)->toDateString())
                  ->whereNull('deleted_at');
            })
            ->with([
                'occurrence:id,schedule_id,date,notes',
                'occurrence.schedule:id,name,type,time',
                'ministry:id,name',
            ])
            ->get()
            ->sortBy(fn ($d) => $d->occurrence->date)
            ->values()
            ->map(fn ($d) => [
                'id'        => $d->id,
                'role'      => $d->role,
                'ministry'  => $d->ministry ? ['id' => $d->ministry->id, 'name' => $d->ministry->name] : null,
                'occurrence' => [
                    'id'   => $d->occurrence->id,
                    'date' => $d->occurrence->date->format('Y-m-d'),
                    'notes' => $d->occurrence->notes,
                    'schedule' => [
                        'id'   => $d->occurrence->schedule->id,
                        'name' => $d->occurrence->schedule->name,
                        'type' => $d->occurrence->schedule->type,
                        'time' => substr($d->occurrence->schedule->time, 0, 5),
                    ],
                ],
            ]);

        return response()->json([
            'success' => true,
            'data'    => $duties,
        ]);
    }
}
