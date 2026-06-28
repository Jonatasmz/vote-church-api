<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventEnrollment extends Model
{
    use HasFactory;

    protected $fillable = [
        'schedule_id',
        'member_id',
        'name',
        'email',
        'cpf',
        'phone',
        'status',
        'source',
        'amount_cents',
        'stripe_session_id',
        'stripe_payment_intent_id',
        'paid_at',
        'metadata',
    ];

    protected $casts = [
        'paid_at'      => 'datetime',
        'amount_cents' => 'integer',
        'metadata'     => 'array',
    ];

    public function schedule()
    {
        return $this->belongsTo(Schedule::class);
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }
}
