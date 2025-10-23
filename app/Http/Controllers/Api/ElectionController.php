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
        $elections = Election::with('candidates')
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
            'candidate_ids' => ['nullable', 'array'],
            'candidate_ids.*' => ['exists:candidates,id'],
        ]);

        $candidateIds = $validated['candidate_ids'] ?? [];
        unset($validated['candidate_ids']);

        $election = Election::create($validated);

        // Vincular candidatos se fornecidos
        if (!empty($candidateIds)) {
            $election->candidates()->attach($candidateIds);
        }

        $election->load('candidates');

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
        $election = Election::with('candidates')->findOrFail($id);

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
            'candidate_ids' => ['nullable', 'array'],
            'candidate_ids.*' => ['exists:candidates,id'],
        ]);

        if (isset($validated['candidate_ids'])) {
            $candidateIds = $validated['candidate_ids'];
            unset($validated['candidate_ids']);
            
            // Sincronizar candidatos
            $election->candidates()->sync($candidateIds);
        }

        $election->update($validated);
        $election->load('candidates');

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
     * Add candidates to election.
     */
    public function addCandidates(Request $request, string $id)
    {
        $election = Election::findOrFail($id);

        $validated = $request->validate([
            'candidate_ids' => ['required', 'array'],
            'candidate_ids.*' => ['exists:candidates,id'],
        ]);

        // Sync vai substituir completamente os candidatos vinculados
        $election->candidates()->sync($validated['candidate_ids']);
        $election->load('candidates');

        return response()->json([
            'success' => true,
            'message' => 'Candidatos vinculados com sucesso',
            'data' => $election
        ]);
    }

    /**
     * Remove candidate from election.
     */
    public function removeCandidate(string $electionId, string $candidateId)
    {
        $election = Election::findOrFail($electionId);
        $election->candidates()->detach($candidateId);
        $election->load('candidates');

        return response()->json([
            'success' => true,
            'message' => 'Candidato removido com sucesso',
            'data' => $election
        ]);
    }
}
