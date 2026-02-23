<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Schedule extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'day_of_week',
        'date',
        'time',
        'description',
        'ministries',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'date'        => 'date:Y-m-d',
        'ministries'  => 'array',
    ];

    public function occurrences()
    {
        return $this->hasMany(Occurrence::class);
    }
}
