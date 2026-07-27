<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Schedule extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Token aleatório usado nas URLs públicas do evento — evita expor o id
     * sequencial e a enumeração de eventos.
     */
    protected static function booted(): void
    {
        static::creating(function (Schedule $schedule) {
            if (empty($schedule->public_id)) {
                do {
                    $token = Str::random(12);
                } while (static::where('public_id', $token)->exists());
                $schedule->public_id = $token;
            }
        });
    }

    protected $fillable = [
        'name',
        'type',
        'day_of_week',
        'date',
        'end_date',
        'time',
        'description',
        'image',
        'ministries',
        'is_paid',
        'price',
        'installments',
        'info_url',
        'allow_non_members',
        'rd_station_enabled',
    ];

    protected $casts = [
        'day_of_week'       => 'integer',
        'date'              => 'date:Y-m-d',
        'end_date'          => 'date:Y-m-d',
        'ministries'        => 'array',
        'is_paid'           => 'boolean',
        'price'             => 'decimal:2',
        'installments'      => 'integer',
        'allow_non_members' => 'boolean',
        'rd_station_enabled' => 'boolean',
    ];

    /**
     * Devolve a URL pública da imagem do evento (ou null). Aceita path do
     * storage ou URL absoluta já gravada.
     */
    public function getImageAttribute($value)
    {
        if (! $value) {
            return $value;
        }
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }
        $path = ltrim(preg_replace('#^/?storage/#', '', $value), '/');

        return Storage::disk('public')->url($path);
    }

    public function enrollments()
    {
        return $this->hasMany(EventEnrollment::class);
    }

    public function occurrences()
    {
        return $this->hasMany(Occurrence::class);
    }
}
