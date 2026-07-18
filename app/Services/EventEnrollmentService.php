<?php

namespace App\Services;

use App\Models\EventEnrollment;
use App\Models\Member;
use App\Models\Schedule;
use Illuminate\Validation\ValidationException;

/**
 * Regras de inscrição em evento pago, compartilhadas pelos três pontos de
 * entrada: área do membro, landing pública e captura externa (RD Station).
 */
class EventEnrollmentService
{
    public static function amountCents(Schedule $schedule): int
    {
        return (int) round(((float) $schedule->price) * 100);
    }

    /**
     * Só dígitos, ou null se vazio.
     */
    public static function normalizeCpf(?string $cpf): ?string
    {
        $digits = preg_replace('/[^0-9]/', '', (string) $cpf);

        return $digits !== '' ? $digits : null;
    }

    /**
     * Busca membro ativo pelo CPF (aceita com ou sem máscara).
     */
    public static function findActiveMemberByCpf(?string $cpf): ?Member
    {
        $digits = self::normalizeCpf($cpf);

        if (!$digits) {
            return null;
        }

        return Member::where(function ($q) use ($digits, $cpf) {
            $q->where('cpf', $digits)->orWhere('cpf', $cpf);
        })
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->first();
    }

    /**
     * Inscrição de membro. Reusa a existente se já houver pending ou paid.
     */
    public static function forMember(Schedule $schedule, Member $member, ?string $email = null): EventEnrollment
    {
        self::assertPaid($schedule);

        $existing = EventEnrollment::where('schedule_id', $schedule->id)
            ->where('member_id', $member->id)
            ->whereIn('status', ['pending', 'paid'])
            ->first();

        if ($existing) {
            return $existing;
        }

        // O e-mail informado no checkout vira o e-mail do membro, se ele ainda não tiver.
        if ($email && !$member->email) {
            $member->update(['email' => $email]);
        }

        return EventEnrollment::create([
            'schedule_id'  => $schedule->id,
            'member_id'    => $member->id,
            'name'         => $member->name,
            'email'        => $email ?: ($member->email ?? ''),
            'cpf'          => self::normalizeCpf($member->cpf),
            'phone'        => null,
            'status'       => 'pending',
            'source'       => 'member',
            'amount_cents' => self::amountCents($schedule),
        ]);
    }

    /**
     * Inscrição de visitante (não-membro). Dedup por CPF dentro do evento.
     *
     * @param  array{name: string, email: string, cpf?: ?string, phone?: ?string}  $data
     */
    public static function forVisitor(Schedule $schedule, array $data, array $metadata = []): EventEnrollment
    {
        self::assertPaid($schedule);

        if (!$schedule->allow_non_members) {
            throw ValidationException::withMessages([
                'schedule' => 'Este evento não aceita inscrições de visitantes.',
            ]);
        }

        $cpfDigits = self::normalizeCpf($data['cpf'] ?? null);

        if ($cpfDigits) {
            $existing = EventEnrollment::where('schedule_id', $schedule->id)
                ->where('cpf', $cpfDigits)
                ->whereIn('status', ['pending', 'paid'])
                ->first();

            if ($existing?->status === 'paid') {
                throw ValidationException::withMessages([
                    'cpf' => 'CPF já inscrito e pago neste evento.',
                ]);
            }

            if ($existing) {
                return $existing;
            }
        }

        return EventEnrollment::create([
            'schedule_id'  => $schedule->id,
            'member_id'    => null,
            'name'         => $data['name'],
            'email'        => $data['email'],
            'cpf'          => $cpfDigits,
            'phone'        => $data['phone'] ?? null,
            'status'       => 'pending',
            'source'       => 'external',
            'amount_cents' => self::amountCents($schedule),
            'metadata'     => $metadata,
        ]);
    }

    private static function assertPaid(Schedule $schedule): void
    {
        if (!$schedule->is_paid) {
            throw ValidationException::withMessages([
                'schedule' => 'Este evento não é pago.',
            ]);
        }
    }
}
