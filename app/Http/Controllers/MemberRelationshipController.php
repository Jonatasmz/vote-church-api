<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\MemberRelationship;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MemberRelationshipController extends Controller
{
    /**
     * Lista todos os parentes do membro, agrupados por tipo,
     * e devolve também grafo (até 2 níveis) para visualização da árvore.
     */
    public function index(Member $member)
    {
        $rels = MemberRelationship::with('relatedMember:id,name,photo')
            ->where('member_id', $member->id)
            ->get()
            ->map(fn ($r) => [
                'id'                => $r->id,
                'relationship_type' => $r->relationship_type,
                'related_member'    => $r->relatedMember ? [
                    'id'    => $r->relatedMember->id,
                    'name'  => $r->relatedMember->name,
                    'photo' => $r->relatedMember->photo,
                ] : null,
            ])
            ->filter(fn ($r) => $r['related_member'] !== null)
            ->values();

        // Construir grafo para árvore (membro central + parentes diretos + parentes dos parentes)
        $directIds = $rels->pluck('related_member.id')->all();
        $secondLevel = MemberRelationship::with('relatedMember:id,name,photo')
            ->whereIn('member_id', $directIds)
            ->where('related_member_id', '!=', $member->id)
            ->get()
            ->map(fn ($r) => [
                'member_id'         => $r->member_id,
                'relationship_type' => $r->relationship_type,
                'related_member'    => $r->relatedMember ? [
                    'id'    => $r->relatedMember->id,
                    'name'  => $r->relatedMember->name,
                    'photo' => $r->relatedMember->photo,
                ] : null,
            ])
            ->filter(fn ($r) => $r['related_member'] !== null)
            ->values();

        return response()->json([
            'success' => true,
            'data'    => [
                'member' => [
                    'id'    => $member->id,
                    'name'  => $member->name,
                    'photo' => $member->photo,
                ],
                'relationships' => $rels,
                'graph'         => $secondLevel,
            ],
        ]);
    }

    /**
     * Cria vínculo + inverso automaticamente.
     */
    public function store(Request $request, Member $member)
    {
        $validated = $request->validate([
            'related_member_id'  => ['required', 'integer', 'exists:members,id', 'different:'.$member->id],
            'relationship_type'  => ['required', 'in:spouse,parent,child,sibling'],
        ]);

        $type = $validated['relationship_type'];
        $relatedId = $validated['related_member_id'];
        $inverse = MemberRelationship::inverseType($type);

        DB::transaction(function () use ($member, $relatedId, $type, $inverse) {
            MemberRelationship::firstOrCreate([
                'member_id'         => $member->id,
                'related_member_id' => $relatedId,
                'relationship_type' => $type,
            ]);

            MemberRelationship::firstOrCreate([
                'member_id'         => $relatedId,
                'related_member_id' => $member->id,
                'relationship_type' => $inverse,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Vínculo familiar criado',
        ], 201);
    }

    /**
     * Remove vínculo + inverso.
     */
    public function destroy(Member $member, MemberRelationship $relationship)
    {
        if ($relationship->member_id !== $member->id) {
            return response()->json([
                'success' => false,
                'message' => 'Vínculo não pertence ao membro informado',
            ], 404);
        }

        DB::transaction(function () use ($relationship) {
            $inverse = MemberRelationship::inverseType($relationship->relationship_type);
            MemberRelationship::where('member_id', $relationship->related_member_id)
                ->where('related_member_id', $relationship->member_id)
                ->where('relationship_type', $inverse)
                ->delete();
            $relationship->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Vínculo removido',
        ]);
    }
}
