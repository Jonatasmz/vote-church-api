<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VoteToken extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'election_id',
        'token',
        'used',
        'used_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'used' => 'boolean',
            'used_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the election that owns the token.
     */
    public function election()
    {
        return $this->belongsTo(Election::class);
    }

    /**
     * Get the votes for this token.
     */
    public function votes()
    {
        return $this->hasMany(Vote::class);
    }

    /**
     * Mark token as used.
     */
    public function markAsUsed(): void
    {
        $this->update([
            'used' => true,
            'used_at' => now(),
        ]);
    }

    /**
     * Check if token is valid (not used and belongs to an active election).
     */
    public function isValid(): bool
    {
        return !$this->used && $this->election->isActive();
    }
}
