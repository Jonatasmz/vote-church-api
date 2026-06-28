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
        'end_date',
        'time',
        'description',
        'ministries',
        'is_paid',
        'price',
        'installments',
        'info_url',
    ];

    protected $casts = [
        'day_of_week'  => 'integer',
        'date'         => 'date:Y-m-d',
        'end_date'     => 'date:Y-m-d',
        'ministries'   => 'array',
        'is_paid'      => 'boolean',
        'price'        => 'decimal:2',
        'installments' => 'integer',
    ];

    public function occurrences()
    {
        return $this->hasMany(Occurrence::class);
    }
}
