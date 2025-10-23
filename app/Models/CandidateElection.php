<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class CandidateElection extends Pivot
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'candidate_id',
        'election_id',
        'vote_count',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'vote_count' => 'integer',
        ];
    }
}
