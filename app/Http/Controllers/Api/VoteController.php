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
            'votes' => ['required', 'array', 'min:1'],
            'votes.*.election_id' => ['required', 'integer', 'exists:elections,id'],
            'votes.*.voted_member_ids' => ['required', 'array', 'min:1'],
            'votes.*.voted_member_ids.*' => ['integer', 'exists:members,id'],
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
                'message' => 'Este grupo de tokens não está ativo ou expirou'
            ], 400);
        }

        // Obter eleições ativas do grupo do token
        $activeElections = $voteToken->getActiveElections();
        $activeElectionIds = $activeElections->pluck('id')->toArray();

        // Validar que todas as eleições pertencem ao grupo do token
        $requestedElectionIds = collect($validated['votes'])->pluck('election_id')->toArray();
        $invalidElectionIds = array_diff($requestedElectionIds, $activeElectionIds);
        
        if (!empty($invalidElectionIds)) {
            return response()->json([
                'success' => false,
                'message' => 'Uma ou mais eleições não pertencem ao grupo deste token ou não estão ativas'
            ], 400);
        }

        // Verificar se já votou em alguma das eleições com este token
        $alreadyVoted = Vote::where('vote_token_id', $voteToken->id)
            ->whereIn('election_id', $requestedElectionIds)
            ->exists();

        if ($alreadyVoted) {
            return response()->json([
                'success' => false,
                'message' => 'Este token já foi utilizado para votar em uma ou mais destas eleições'
            ], 400);
        }

        // Registrar os votos em uma transação
        DB::beginTransaction();
        try {
            $totalVotesRegistered = 0;
            $electionResults = [];

            foreach ($validated['votes'] as $voteData) {
                $election = Election::findOrFail($voteData['election_id']);

                // Validar quantidade de votos para esta eleição
                $voteCount = count($voteData['voted_member_ids']);
                if ($voteCount > $election->max_votes) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => "Para a eleição '{$election->title}', você pode votar em no máximo {$election->max_votes} candidato(s)"
                    ], 400);
                }

                // Validar se os membros (candidatos) pertencem à eleição
                $electionMemberIds = $election->members()->pluck('members.id')->toArray();
                $invalidMembers = array_diff($voteData['voted_member_ids'], $electionMemberIds);
                
                if (!empty($invalidMembers)) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => "Um ou mais candidatos não pertencem à eleição '{$election->title}'"
                    ], 400);
                }

                // Registrar votos desta eleição
                foreach ($voteData['voted_member_ids'] as $votedMemberId) {
                    Vote::create([
                        'vote_token_id' => $voteToken->id,
                        'election_id' => $election->id,
                        'voted_member_id' => $votedMemberId,
                    ]);
                    $totalVotesRegistered++;
                }

                $electionResults[] = [
                    'election_id' => $election->id,
                    'election_title' => $election->title,
                    'votes_count' => count($voteData['voted_member_ids'])
                ];
            }

            // Marcar token como usado
            $voteToken->markAsUsed();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Voto(s) registrado(s) com sucesso em todas as eleições',
                'data' => [
                    'total_votes' => $totalVotesRegistered,
                    'elections' => $electionResults,
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
        $election = Election::with('tokenGroups')->findOrFail($electionId);

        $stats = DB::table('votes')
            ->join('members', 'votes.voted_member_id', '=', 'members.id')
            ->where('votes.election_id', $electionId)
            ->select(
                'members.id',
                'members.name',
                'members.photo',
                DB::raw('COUNT(*) as vote_count')
            )
            ->groupBy('members.id', 'members.name', 'members.photo')
            ->orderBy('vote_count', 'desc')
            ->get();

        // Obter total de tokens dos grupos vinculados a esta eleição
        $totalTokens = 0;
        $usedTokens = 0;
        
        foreach ($election->tokenGroups as $tokenGroup) {
            $totalTokens += $tokenGroup->getTotalTokensCount();
            $usedTokens += $tokenGroup->getUsedTokensCount();
        }

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
            'voted_member_ids' => ['required', 'array', 'min:1'],
            'voted_member_ids.*' => ['integer', 'exists:members,id'],
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
        $voteCount = count($validated['voted_member_ids']);
        if ($voteCount > $election->max_votes) {
            return response()->json([
                'success' => false,
                'message' => "Você pode votar em no máximo {$election->max_votes} candidato(s)"
            ], 400);
        }

        // Validar se os membros (candidatos) pertencem à eleição
        $electionMemberIds = $election->members()->pluck('members.id')->toArray();
        $invalidMembers = array_diff($validated['voted_member_ids'], $electionMemberIds);
        
        if (!empty($invalidMembers)) {
            return response()->json([
                'success' => false,
                'message' => 'Um ou mais candidatos não pertencem a esta eleição'
            ], 400);
        }

        // Registrar os votos em uma transação
        DB::beginTransaction();
        try {
            $votes = [];
            foreach ($validated['voted_member_ids'] as $votedMemberId) {
                $vote = Vote::create([
                    'member_id' => $member->id,
                    'election_id' => $election->id,
                    'voted_member_id' => $votedMemberId,
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
