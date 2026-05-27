<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberRelationship extends Model
{
    protected $fillable = [
        'member_id',
        'related_member_id',
        'relationship_type',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    public function relatedMember()
    {
        return $this->belongsTo(Member::class, 'related_member_id');
    }

    /**
     * Tipo inverso: usado para gravar o lado oposto automaticamente.
     */
    public static function inverseType(string $type): string
    {
        return match ($type) {
            'spouse'  => 'spouse',
            'sibling' => 'sibling',
            'parent'  => 'child',
            'child'   => 'parent',
            default   => $type,
        };
    }
}
