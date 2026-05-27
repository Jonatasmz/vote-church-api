<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
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

    public function ministries()
    {
        return $this->belongsToMany(Ministry::class, 'ministry_member')->withTimestamps();
    }

    public function relationships()
    {
        return $this->hasMany(MemberRelationship::class, 'member_id');
    }
}
