<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class TokenGroup extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
        'valid_from',
        'valid_until',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get the elections associated with this token group.
     */
    public function elections()
    {
        return $this->belongsToMany(Election::class, 'election_token_group');
    }

    /**
     * Get the tokens in this group.
     */
    public function tokens()
    {
        return $this->hasMany(VoteToken::class);
    }

    /**
     * Check if the token group is currently active.
     */
    public function isActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        $now = Carbon::now();

        // Se tem valid_from, verifica se já começou
        if ($this->valid_from && $now->isBefore($this->valid_from)) {
            return false;
        }

        // Se tem valid_until, verifica se não expirou
        if ($this->valid_until && $now->isAfter($this->valid_until)) {
            return false;
        }

        return true;
    }

    /**
     * Check if the token group is valid (active and within date range).
     */
    public function isValid(): bool
    {
        return $this->isActive();
    }

    /**
     * Get all active elections in this group.
     */
    public function getActiveElections()
    {
        return $this->elections()
            ->where('status', 'active')
            ->with('members')
            ->get();
    }

    /**
     * Get unused token count.
     */
    public function getUnusedTokensCount(): int
    {
        return $this->tokens()->where('used', false)->count();
    }

    /**
     * Get used token count.
     */
    public function getUsedTokensCount(): int
    {
        return $this->tokens()->where('used', true)->count();
    }

    /**
     * Get total token count.
     */
    public function getTotalTokensCount(): int
    {
        return $this->tokens()->count();
    }
}
