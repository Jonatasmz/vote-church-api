<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vote;
use App\Models\VoteToken;
use App\Models\Election;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VoteController extends Controller
{
    /**
     * Submit votes using a token.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'candidate_ids' => ['required', 'array', 'min:1'],
            'candidate_ids.*' => ['integer', 'exists:candidates,id'],
        ]);

        // Buscar o token
        $voteToken = VoteToken::where('token', $validated['token'])->first();

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

        $election = $voteToken->election;

        // Validar quantidade de votos
        $voteCount = count($validated['candidate_ids']);
        if ($voteCount > $election->max_votes) {
            return response()->json([
                'success' => false,
                'message' => "Você pode votar em no máximo {$election->max_votes} candidato(s)"
            ], 400);
        }

        // Validar se os candidatos pertencem à eleição
        $electionCandidateIds = $election->candidates()->pluck('candidates.id')->toArray();
        $invalidCandidates = array_diff($validated['candidate_ids'], $electionCandidateIds);
        
        if (!empty($invalidCandidates)) {
            return response()->json([
                'success' => false,
                'message' => 'Um ou mais candidatos não pertencem a esta eleição'
            ], 400);
        }

        // Registrar os votos em uma transação
        DB::beginTransaction();
        try {
            $votes = [];
            foreach ($validated['candidate_ids'] as $candidateId) {
                $vote = Vote::create([
                    'vote_token_id' => $voteToken->id,
                    'election_id' => $election->id,
                    'candidate_id' => $candidateId,
                ]);
                $votes[] = $vote;
            }

            // Marcar token como usado
            $voteToken->markAsUsed();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Voto(s) registrado(s) com sucesso',
                'data' => [
                    'votes_count' => count($votes),
                    'token_used' => true
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Erro ao registrar votos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get voting statistics for an election.
     */
    public function statistics(string $electionId)
    {
        $election = Election::findOrFail($electionId);

        $stats = DB::table('votes')
            ->join('candidates', 'votes.candidate_id', '=', 'candidates.id')
            ->where('votes.election_id', $electionId)
            ->select(
                'candidates.id',
                'candidates.name',
                'candidates.photo',
                DB::raw('COUNT(*) as vote_count')
            )
            ->groupBy('candidates.id', 'candidates.name', 'candidates.photo')
            ->orderBy('vote_count', 'desc')
            ->get();

        $totalTokens = VoteToken::where('election_id', $electionId)->count();
        $usedTokens = VoteToken::where('election_id', $electionId)->where('used', true)->count();
        $totalVotes = Vote::where('election_id', $electionId)->count();

        return response()->json([
            'success' => true,
            'data' => [
                'statistics' => $stats,
                'summary' => [
                    'total_tokens' => $totalTokens,
                    'used_tokens' => $usedTokens,
                    'unused_tokens' => $totalTokens - $usedTokens,
                    'total_votes' => $totalVotes,
                    'participation_rate' => $totalTokens > 0 ? round(($usedTokens / $totalTokens) * 100, 2) : 0
                ]
            ]
        ]);
    }

    /**
     * Submit votes using CPF (without QR Code).
     */
    public function storeByCpf(Request $request)
    {
        $validated = $request->validate([
            'cpf' => ['required', 'string'],
            'election_id' => ['required', 'integer', 'exists:elections,id'],
            'candidate_ids' => ['required', 'array', 'min:1'],
            'candidate_ids.*' => ['integer', 'exists:candidates,id'],
        ]);

        // Remover formatação do CPF
        $cpf = preg_replace('/[^0-9]/', '', $validated['cpf']);

        // Buscar membro pelo CPF
        $member = Member::where(function($query) use ($cpf, $validated) {
            $query->where('cpf', $validated['cpf'])
                  ->orWhere('cpf', $cpf);
        })->first();

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'CPF não encontrado na base de dados'
            ], 404);
        }

        // Verificar se já votou nesta eleição
        $hasVoted = Vote::where('election_id', $validated['election_id'])
            ->where('member_id', $member->id)
            ->exists();

        if ($hasVoted) {
            return response()->json([
                'success' => false,
                'message' => 'Você já votou nesta eleição'
            ], 400);
        }

        $election = Election::findOrFail($validated['election_id']);

        // Verificar se a eleição está ativa
        if ($election->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Esta eleição não está ativa'
            ], 400);
        }

        // Validar quantidade de votos
        $voteCount = count($validated['candidate_ids']);
        if ($voteCount > $election->max_votes) {
            return response()->json([
                'success' => false,
                'message' => "Você pode votar em no máximo {$election->max_votes} candidato(s)"
            ], 400);
        }

        // Validar se os candidatos pertencem à eleição
        $electionCandidateIds = $election->candidates()->pluck('candidates.id')->toArray();
        $invalidCandidates = array_diff($validated['candidate_ids'], $electionCandidateIds);
        
        if (!empty($invalidCandidates)) {
            return response()->json([
                'success' => false,
                'message' => 'Um ou mais candidatos não pertencem a esta eleição'
            ], 400);
        }

        // Registrar os votos em uma transação
        DB::beginTransaction();
        try {
            $votes = [];
            foreach ($validated['candidate_ids'] as $candidateId) {
                $vote = Vote::create([
                    'member_id' => $member->id,
                    'election_id' => $election->id,
                    'candidate_id' => $candidateId,
                ]);
                $votes[] = $vote;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Voto(s) registrado(s) com sucesso',
                'data' => [
                    'votes_count' => count($votes),
                    'voter_name' => $member->name
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Erro ao registrar votos: ' . $e->getMessage()
            ], 500);
        }
    }
}
