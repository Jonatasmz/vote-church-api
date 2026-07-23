<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventEnrollment;
use App\Models\Schedule;
use App\Services\CheckoutTokenService;
use App\Services\EventEnrollmentService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Landing pública de evento pago: exibe o evento, identifica o visitante pelo
 * CPF e inscreve membro ou não-membro na mesma tela. Ambos saem pro checkout.
 */
class PublicEventController extends Controller
{
    /**
     * Dados do evento pra montar a landing.
     */
    public function show(Schedule $schedule)
    {
        $this->assertPublicEvent($schedule);

        return response()->json([
            'success' => true,
            'data'    => [
                'id'                => $schedule->id,
                'name'              => $schedule->name,
                'description'       => $schedule->description,
                'date'              => $schedule->date?->format('Y-m-d'),
                'end_date'          => $schedule->end_date?->format('Y-m-d'),
                'time'              => substr($schedule->time ?? '', 0, 5),
                'price'             => $schedule->price,
                'installments'      => $schedule->installments,
                'info_url'          => $schedule->info_url,
                'allow_non_members' => (bool) $schedule->allow_non_members,
            ],
        ]);
    }

    /**
     * Passo 1: o CPF é de membro ativo? Já tem inscrição?
     */
    public function identify(Request $request, Schedule $schedule)
    {
        $this->assertPublicEvent($schedule);

        $request->validate([
            'cpf' => ['required', 'string', 'max:14'],
        ]);

        $cpfDigits = EventEnrollmentService::normalizeCpf($request->cpf);

        if (strlen((string) $cpfDigits) !== 11) {
            throw ValidationException::withMessages([
                'cpf' => 'CPF inválido.',
            ]);
        }

        $member = EventEnrollmentService::findActiveMemberByCpf($cpfDigits);

        $enrollment = EventEnrollment::where('schedule_id', $schedule->id)
            ->where(function ($q) use ($member, $cpfDigits) {
                $q->where('cpf', $cpfDigits);
                if ($member) {
                    $q->orWhere('member_id', $member->id);
                }
            })
            ->whereIn('status', ['pending', 'paid'])
            ->latest('id')
            ->first();

        return response()->json([
            'success' => true,
            'data'    => [
                'is_member'   => (bool) $member,
                // Só o primeiro nome: a tela é pública e o CPF não deve virar consulta de cadastro.
                'first_name'  => $member ? explode(' ', trim($member->name))[0] : null,
                'needs_email' => $member ? !$member->email : true,
                'can_enroll'  => $member ? true : (bool) $schedule->allow_non_members,
                'enrollment'  => $enrollment ? [
                    'status'       => $enrollment->status,
                    'checkout_url' => $enrollment->status === 'pending'
                        ? CheckoutTokenService::checkoutPath($enrollment)
                        : null,
                ] : null,
            ],
        ]);
    }

    /**
     * Passo 2: cria (ou reusa) a inscrição e devolve o caminho do checkout.
     */
    public function enroll(Request $request, Schedule $schedule)
    {
        $this->assertPublicEvent($schedule);

        $request->validate([
            'cpf'   => ['required', 'string', 'max:14'],
            // Obrigatório só pra visitante — membro herda o nome do cadastro.
            'name'  => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $cpfDigits = EventEnrollmentService::normalizeCpf($request->cpf);

        if (strlen((string) $cpfDigits) !== 11) {
            throw ValidationException::withMessages([
                'cpf' => 'CPF inválido.',
            ]);
        }

        $member = EventEnrollmentService::findActiveMemberByCpf($cpfDigits);

        if ($member) {
            $enrollment = EventEnrollmentService::forMember($schedule, $member, $request->email);
        } else {
            if (!$request->filled('name')) {
                throw ValidationException::withMessages([
                    'name' => 'Informe seu nome completo.',
                ]);
            }

            $enrollment = EventEnrollmentService::forVisitor($schedule, [
                'name'  => $request->name,
                'email' => $request->email,
                'cpf'   => $cpfDigits,
                'phone' => $request->phone,
            ], ['origin' => 'landing']);
        }

        if ($enrollment->status === 'paid') {
            throw ValidationException::withMessages([
                'cpf' => 'Esta inscrição já está paga.',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Inscrição registrada. Conduza ao checkout.',
            'data'    => [
                'enrollment_id' => $enrollment->id,
                'is_member'     => (bool) $member,
                'checkout_url'  => CheckoutTokenService::checkoutPath($enrollment),
            ],
        ], 201);
    }

    /**
     * A landing só existe pra evento avulso e pago.
     */
    private function assertPublicEvent(Schedule $schedule): void
    {
        if (!$schedule->is_paid || $schedule->type !== 'single') {
            abort(404);
        }
    }
}
