<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Occurrence extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'schedule_id',
        'date',
        'end_date',
        'notes',
        'is_paid',
        'price',
        'installments',
        'info_url',
    ];

    protected $casts = [
        'date'         => 'date:Y-m-d',
        'end_date'     => 'date:Y-m-d',
        'is_paid'      => 'boolean',
        'price'        => 'decimal:2',
        'installments' => 'integer',
    ];

    public function schedule()
    {
        return $this->belongsTo(Schedule::class);
    }

    public function duties()
    {
        return $this->hasMany(OccurrenceDuty::class);
    }
}
