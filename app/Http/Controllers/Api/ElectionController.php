<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Election;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ElectionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $elections = Election::with('members')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $elections
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'election_date' => ['required', 'date'],
            'status' => ['required', Rule::in(['draft', 'active', 'finished', 'cancelled'])],
            'max_votes' => ['required', 'integer', 'min:1'],
            'seats_available' => ['required', 'integer', 'min:1'],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['exists:members,id'],
        ]);

        $memberIds = $validated['member_ids'] ?? [];
        unset($validated['member_ids']);

        $election = Election::create($validated);

        // Vincular membros como candidatos se fornecidos
        if (!empty($memberIds)) {
            $election->members()->attach($memberIds);
        }

        $election->load('members');

        return response()->json([
            'success' => true,
            'message' => 'Eleição criada com sucesso',
            'data' => $election
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $election = Election::with('members')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $election
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $election = Election::findOrFail($id);

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'election_date' => ['sometimes', 'required', 'date'],
            'status' => ['sometimes', 'required', Rule::in(['draft', 'active', 'finished', 'cancelled'])],
            'max_votes' => ['sometimes', 'required', 'integer', 'min:1'],
            'seats_available' => ['sometimes', 'required', 'integer', 'min:1'],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['exists:members,id'],
        ]);

        if (isset($validated['member_ids'])) {
            $memberIds = $validated['member_ids'];
            unset($validated['member_ids']);
            
            // Sincronizar membros como candidatos
            $election->members()->sync($memberIds);
        }

        $election->update($validated);
        $election->load('members');

        return response()->json([
            'success' => true,
            'message' => 'Eleição atualizada com sucesso',
            'data' => $election
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $election = Election::findOrFail($id);
        $election->delete();

        return response()->json([
            'success' => true,
            'message' => 'Eleição removida com sucesso'
        ]);
    }

    /**
     * Add members as candidates to election.
     */
    public function addMembers(Request $request, string $id)
    {
        $election = Election::findOrFail($id);

        $validated = $request->validate([
            'member_ids' => ['required', 'array'],
            'member_ids.*' => ['exists:members,id'],
        ]);

        // Sync vai substituir completamente os membros vinculados como candidatos
        $election->members()->sync($validated['member_ids']);
        $election->load('members');

        return response()->json([
            'success' => true,
            'message' => 'Membros vinculados como candidatos com sucesso',
            'data' => $election
        ]);
    }

    /**
     * Remove member (candidate) from election.
     */
    public function removeMember(string $electionId, string $memberId)
    {
        $election = Election::findOrFail($electionId);
        $election->members()->detach($memberId);
        $election->load('members');

        return response()->json([
            'success' => true,
            'message' => 'Membro removido da eleição com sucesso',
            'data' => $election
        ]);
    }
}
