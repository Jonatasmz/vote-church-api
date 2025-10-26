<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Election extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'description',
        'election_date',
        'status',
        'max_votes',
        'seats_available',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'election_date' => 'date:Y-m-d',
            'max_votes' => 'integer',
            'seats_available' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the candidates (members) for the election.
     */
    public function candidates()
    {
        return $this->belongsToMany(Member::class, 'election_member')
            ->withTimestamps();
    }

    /**
     * Alias for candidates - returns members who are candidates
     */
    public function members()
    {
        return $this->belongsToMany(Member::class, 'election_member')
            ->withTimestamps();
    }

    /**
     * Get the vote tokens for the election.
     */
    public function voteTokens()
    {
        return $this->hasMany(VoteToken::class);
    }

    /**
     * Get the votes for the election.
     */
    public function votes()
    {
        return $this->hasMany(Vote::class);
    }

    /**
     * Check if election is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active' 
            && now()->isSameDay($this->election_date);
    }

    /**
     * Check if election has finished.
     */
    public function hasFinished(): bool
    {
        return $this->status === 'finished' || now()->greaterThan($this->election_date);
    }
}
