<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vote extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'vote_token_id',
        'member_id',
        'election_id',
        'candidate_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the token that owns the vote.
     */
    public function voteToken()
    {
        return $this->belongsTo(VoteToken::class);
    }

    /**
     * Get the election that owns the vote.
     */
    public function election()
    {
        return $this->belongsTo(Election::class);
    }

    /**
     * Get the candidate that was voted for.
     */
    public function candidate()
    {
        return $this->belongsTo(Candidate::class);
    }

    /**
     * Get the member that voted.
     */
    public function member()
    {
        return $this->belongsTo(Member::class);
    }
}
