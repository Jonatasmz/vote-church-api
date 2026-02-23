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
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
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
