<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Member extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'cpf',
        'email',
        'rg',
        'birth_date',
        'description',
        'member_since',
        'photo',
        'status',
        'pending_review',
    ];

    protected static function booted(): void
    {
        static::saving(function (Member $member): void {
            if ($member->isDirty('name')) {
                $member->name_normalized = self::normalizeName($member->name);
            }
        });
    }

    public static function normalizeName(?string $name): string
    {
        return Str::lower(Str::ascii((string) $name));
    }

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'member_since' => 'date',
            'birth_date' => 'date',
            'pending_review' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Prepare the member_since date for serialization.
     */
    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d');
    }

    public function getPhotoAttribute($value)
    {
        if (!$value) {
            return $value;
        }
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }
        $path = ltrim(preg_replace('#^/?storage/#', '', $value), '/');
        return Storage::disk('public')->url($path);
    }

    public function ministries()
    {
        return $this->belongsToMany(Ministry::class, 'ministry_member')->withTimestamps();
    }

    public function relationships()
    {
        return $this->hasMany(MemberRelationship::class, 'member_id');
    }
}
