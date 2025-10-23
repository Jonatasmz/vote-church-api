<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;

class CandidateController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $candidates = Candidate::orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $candidates
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'member_since' => ['required', 'string', 'size:4', 'regex:/^\d{4}$/'],
            'photo' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $candidate = Candidate::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Candidato criado com sucesso',
            'data' => $candidate
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $candidate = Candidate::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $candidate
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $candidate = Candidate::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'member_since' => ['sometimes', 'required', 'string', 'size:4', 'regex:/^\d{4}$/'],
            'photo' => ['nullable', 'string'],
            'status' => ['sometimes', 'required', Rule::in(['active', 'inactive'])],
        ]);

        $candidate->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Candidato atualizado com sucesso',
            'data' => $candidate
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $candidate = Candidate::findOrFail($id);
        
        // Deletar foto se existir
        if ($candidate->photo && Storage::exists('public/' . $candidate->photo)) {
            Storage::delete('public/' . $candidate->photo);
        }

        $candidate->delete();

        return response()->json([
            'success' => true,
            'message' => 'Candidato removido com sucesso'
        ]);
    }
}
