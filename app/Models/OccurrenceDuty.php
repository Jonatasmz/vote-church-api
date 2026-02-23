<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OccurrenceDuty extends Model
{
    use HasFactory;

    protected $fillable = [
        'occurrence_id',
        'member_id',
        'ministry_id',
        'role',
    ];

    public function occurrence()
    {
        return $this->belongsTo(Occurrence::class);
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function ministry()
    {
        return $this->belongsTo(Ministry::class);
    }
}
