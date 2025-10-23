<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VoteToken;
use App\Models\Election;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VoteTokenController extends Controller
{
    /**
     * Display a listing of tokens for an election.
     */
    public function index(string $electionId)
    {
        $election = Election::findOrFail($electionId);
        
        $tokens = VoteToken::where('election_id', $electionId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $tokens
        ]);
    }

    /**
     * Generate tokens for an election.
     */
    public function store(Request $request, string $electionId)
    {
        $election = Election::findOrFail($electionId);

        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:1000'],
        ]);

        $tokens = [];
        for ($i = 0; $i < $validated['quantity']; $i++) {
            $token = VoteToken::create([
                'election_id' => $electionId,
                'token' => Str::random(32),
                'used' => false,
            ]);
            $tokens[] = $token;
        }

        return response()->json([
            'success' => true,
            'message' => "{$validated['quantity']} token(s) gerado(s) com sucesso",
            'data' => $tokens
        ], 201);
    }

    /**
     * Display the specified token.
     */
    public function show(string $electionId, string $id)
    {
        $token = VoteToken::where('election_id', $electionId)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $token
        ]);
    }

    /**
     * Remove the specified token.
     */
    public function destroy(string $electionId, string $id)
    {
        $token = VoteToken::where('election_id', $electionId)
            ->findOrFail($id);

        if ($token->used) {
            return response()->json([
                'success' => false,
                'message' => 'Não é possível remover um token já utilizado'
            ], 400);
        }

        $token->delete();

        return response()->json([
            'success' => true,
            'message' => 'Token removido com sucesso'
        ]);
    }

    /**
     * Validate a token.
     */
    public function validate(Request $request)
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
        ]);

        $voteToken = VoteToken::where('token', $validated['token'])
            ->with('election')
            ->first();

        if (!$voteToken) {
            return response()->json([
                'success' => false,
                'message' => 'Token inválido'
            ], 404);
        }

        if ($voteToken->used) {
            return response()->json([
                'success' => false,
                'message' => 'Este token já foi utilizado'
            ], 400);
        }

        if (!$voteToken->isValid()) {
            return response()->json([
                'success' => false,
                'message' => 'Esta eleição não está ativa'
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $voteToken,
                'election' => $voteToken->election->load('candidates')
            ]
        ]);
    }
}
