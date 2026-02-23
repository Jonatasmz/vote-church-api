<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Member;
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
