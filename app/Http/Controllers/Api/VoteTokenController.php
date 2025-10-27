<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VoteToken;
use App\Models\TokenGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VoteTokenController extends Controller
{
    /**
     * Display a listing of tokens for a token group.
     */
    public function index(string $tokenGroupId)
    {
        $tokenGroup = TokenGroup::findOrFail($tokenGroupId);
        
        $tokens = VoteToken::where('token_group_id', $tokenGroupId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $tokens
        ]);
    }

    /**
     * Generate tokens for a token group.
     */
    public function store(Request $request, string $tokenGroupId)
    {
        $tokenGroup = TokenGroup::findOrFail($tokenGroupId);

        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:1000'],
        ]);

        $tokens = [];
        for ($i = 0; $i < $validated['quantity']; $i++) {
            $token = VoteToken::create([
                'token_group_id' => $tokenGroupId,
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
    public function show(string $tokenGroupId, string $id)
    {
        $token = VoteToken::where('token_group_id', $tokenGroupId)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $token
        ]);
    }

    /**
     * Remove the specified token.
     */
    public function destroy(string $tokenGroupId, string $id)
    {
        $token = VoteToken::where('token_group_id', $tokenGroupId)
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
     * Validate a token and return all active elections in its group.
     */
    public function validate(Request $request)
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
        ]);

        $voteToken = VoteToken::where('token', $validated['token'])
            ->with('tokenGroup.elections.members')
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
                'message' => 'Este grupo de tokens não está ativo ou expirou'
            ], 400);
        }

        $activeElections = $voteToken->getActiveElections();

        if ($activeElections->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Não há eleições ativas para este token'
            ], 400);
        }

        // Buscar eleições em que já votou com este token
        $votedElectionIds = \App\Models\Vote::where('vote_token_id', $voteToken->id)
            ->distinct('election_id')
            ->pluck('election_id')
            ->toArray();

        // Filtrar apenas eleições que ainda não foram votadas
        $pendingElections = $activeElections->filter(function ($election) use ($votedElectionIds) {
            return !in_array($election->id, $votedElectionIds);
        })->values();

        // Se não há eleições pendentes, mas há votos registrados, token foi totalmente usado
        if ($pendingElections->isEmpty() && !empty($votedElectionIds)) {
            return response()->json([
                'success' => false,
                'message' => 'Você já votou em todas as eleições disponíveis com este token'
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $voteToken,
                'token_group' => $voteToken->tokenGroup,
                'elections' => $pendingElections,
                'voted_elections_count' => count($votedElectionIds),
                'total_elections_count' => $activeElections->count()
            ]
        ]);
    }
}
