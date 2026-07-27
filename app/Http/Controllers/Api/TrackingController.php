<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Member;
use Illuminate\Http\Request;

/**
 * Registra a navegação dos usuários pela plataforma (área do membro e telas
 * públicas). Grava no activity_log via spatie/activitylog. Endpoint público:
 * o member_id vem do front (não autenticado), então é indicativo, não confiável
 * para autorização — aqui serve só como recorte de analytics.
 */
class TrackingController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'path'      => ['required', 'string', 'max:255'],
            'member_id' => ['nullable', 'integer'],
        ]);

        $member = ! empty($data['member_id'])
            ? Member::withTrashed()->find($data['member_id'])
            : null;

        activity('navigation')
            ->causedBy($member)
            ->withProperties([
                'path'       => $data['path'],
                'type'       => $member ? 'private' : 'public',
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
            ])
            ->log('pageview');

        // Resposta mínima: é fire-and-forget do front.
        return response()->json(['success' => true]);
    }
}
