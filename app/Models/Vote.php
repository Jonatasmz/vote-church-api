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
        'voted_member_id', // ID do membro que recebeu o voto (candidato)
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
     * Get the candidate (member) that was voted for.
     */
    public function candidate()
    {
        return $this->belongsTo(Member::class, 'voted_member_id');
    }

    /**
     * Get the member that was voted for (candidate).
     */
    public function votedMember()
    {
        return $this->belongsTo(Member::class, 'voted_member_id');
    }

    /**
     * Get the member that voted (voter).
     */
    public function member()
    {
        return $this->belongsTo(Member::class, 'member_id');
    }
}
