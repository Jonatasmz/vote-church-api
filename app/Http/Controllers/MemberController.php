<?php

namespace App\Http\Controllers;

use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class MemberController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 15);
        $search = $request->input('search', '');
        
        $query = Member::query()->orderBy('name');
        
        if ($search) {
            $normalized = Member::normalizeName($search);
            $query->where(function($q) use ($search, $normalized) {
                $q->where('name_normalized', 'like', "%{$normalized}%")
                  ->orWhere('cpf', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }
        
        $members = $query->paginate($perPage);
        
        return response()->json([
            'success' => true,
            'data' => $members->items(),
            'pagination' => [
                'current_page' => $members->currentPage(),
                'per_page' => $members->perPage(),
                'total' => $members->total(),
                'last_page' => $members->lastPage(),
                'from' => $members->firstItem(),
                'to' => $members->lastItem(),
            ]
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'cpf' => 'nullable|string|max:14',
            'email' => 'nullable|email|max:255',
            'rg' => 'nullable|string|max:20',
            'birth_date' => 'nullable|date',
            'description' => 'nullable|string',
            'member_since' => 'required|date',
            'photo' => 'nullable|string',
            'status' => 'required|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erro de validação',
                'errors' => $validator->errors()
            ], 422);
        }

        $member = Member::create($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Membro cadastrado com sucesso',
            'data' => $member
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Member $member)
    {
        return response()->json([
            'success' => true,
            'data' => $member
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Member $member)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'cpf' => 'nullable|string|max:14',
            'email' => 'nullable|email|max:255',
            'rg' => 'nullable|string|max:20',
            'birth_date' => 'nullable|date',
            'description' => 'nullable|string',
            'member_since' => 'sometimes|required|date',
            'photo' => 'nullable|string',
            'status' => 'sometimes|required|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erro de validação',
                'errors' => $validator->errors()
            ], 422);
        }

        $member->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Membro atualizado com sucesso',
            'data' => $member
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Member $member)
    {
        $member->delete();

        return response()->json([
            'success' => true,
            'message' => 'Membro removido com sucesso'
        ]);
    }

    /**
     * Lista aniversariantes do mês informado (default: mês atual).
     */
    public function birthdays(Request $request)
    {
        $request->validate([
            'month' => ['nullable', 'integer', 'between:1,12'],
        ]);

        $month = (int) ($request->month ?? now()->month);

        $members = Member::whereNotNull('birth_date')
            ->whereMonth('birth_date', $month)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->get(['id', 'name', 'photo', 'birth_date'])
            ->sortBy(fn ($m) => $m->birth_date->format('d'))
            ->values()
            ->map(fn ($m) => [
                'id'    => $m->id,
                'name'  => $m->name,
                'photo' => $m->photo,
                'birth_date' => $m->birth_date->format('Y-m-d'),
                'day'   => (int) $m->birth_date->format('d'),
            ]);

        return response()->json([
            'success' => true,
            'data'    => $members,
        ]);
    }

    /**
     * Upload de foto do membro. Aceita arquivo de imagem multipart.
     */
    public function uploadPhoto(Request $request, Member $member)
    {
        $request->validate([
            'photo' => ['required', 'file', 'mimes:jpeg,png,jpg,webp,heic,heif', 'max:8192'],
        ]);

        // Apagar foto anterior se for caminho local
        $rawPhoto = $member->getRawOriginal('photo');
        if ($rawPhoto) {
            $oldPath = ltrim(preg_replace('#^/?storage/#', '', $rawPhoto), '/');
            if (str_starts_with($oldPath, 'members/')) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        $path = $request->file('photo')->store('members', 'public');

        $member->update(['photo' => $path]);

        return response()->json([
            'success' => true,
            'data'    => ['photo' => $member->photo],
        ]);
    }

    /**
     * Lista grupos de membros suspeitos de duplicação por nome normalizado.
     */
    public function duplicates()
    {
        $normalizedNames = Member::query()
            ->whereNull('deleted_at')
            ->whereNotNull('name_normalized')
            ->where('name_normalized', '!=', '')
            ->select('name_normalized')
            ->groupBy('name_normalized')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('name_normalized');

        if ($normalizedNames->isEmpty()) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $members = Member::query()
            ->whereIn('name_normalized', $normalizedNames)
            ->whereNull('deleted_at')
            ->withCount('ministries')
            ->orderBy('name_normalized')
            ->orderBy('id')
            ->get();

        $groups = $members
            ->groupBy('name_normalized')
            ->map(fn ($items, $key) => [
                'name_normalized' => $key,
                'members' => $items->map(fn (Member $m) => [
                    'id'               => $m->id,
                    'name'             => $m->name,
                    'cpf'              => $m->cpf,
                    'rg'               => $m->rg,
                    'birth_date'       => $m->birth_date?->format('Y-m-d'),
                    'member_since'     => $m->member_since?->format('Y-m-d'),
                    'photo'            => $m->photo,
                    'status'           => $m->status,
                    'pending_review'   => (bool) $m->pending_review,
                    'ministries_count' => $m->ministries_count,
                    'created_at'       => $m->created_at?->toIso8601String(),
                ])->values(),
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data'    => $groups,
        ]);
    }

    /**
     * Aprovar cadastro pendente (sem merge).
     */
    public function approve(Member $member)
    {
        $member->update(['pending_review' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Cadastro aprovado',
            'data' => $member,
        ]);
    }

    /**
     * Mesclar membro pendente em outro membro existente.
     * Source: o cadastro pendente.
     * Target: o membro existente que receberá os dados/relacionamentos.
     */
    public function merge(Request $request, Member $member)
    {
        $validated = $request->validate([
            'target_id' => ['required', 'integer', 'exists:members,id', 'different:'.$member->id],
        ]);

        $target = Member::findOrFail($validated['target_id']);
        $source = $member;

        DB::transaction(function () use ($source, $target) {
            // Copiar campos do source quando target estiver vazio (preserva dados do target).
            // CPF do source quase sempre vai preencher o target (regra de negócio: pessoa não tinha cpf cadastrado).
            $fields = ['cpf', 'rg', 'birth_date', 'description', 'photo', 'member_since'];
            $updates = [];
            foreach ($fields as $f) {
                if (!empty($source->{$f}) && empty($target->{$f})) {
                    $updates[$f] = $source->{$f};
                }
            }
            // CPF: se target não tem, recebe do source. Se ambos têm, mantém do target.
            if (!empty($source->cpf) && empty($target->cpf)) {
                $updates['cpf'] = $source->cpf;
            }
            $updates['pending_review'] = false;
            $updates['status'] = 'active';

            // Liberar cpf do source antes de gravar no target (unique).
            $source->update(['cpf' => null]);
            $target->update($updates);

            // Reassignar relacionamentos do source para target (evitando duplicatas em pivots).
            DB::table('votes')->where('member_id', $source->id)->update(['member_id' => $target->id]);
            DB::table('occurrence_duties')->where('member_id', $source->id)->update(['member_id' => $target->id]);

            // Pivots: election_member, ministry_member, member_ministry_requests — verificar duplicatas.
            $pivots = [
                ['table' => 'election_member', 'other' => 'election_id'],
                ['table' => 'ministry_member', 'other' => 'ministry_id'],
                ['table' => 'member_ministry_requests', 'other' => 'ministry_id'],
            ];

            foreach ($pivots as $p) {
                $sourceRows = DB::table($p['table'])->where('member_id', $source->id)->get();
                foreach ($sourceRows as $row) {
                    $exists = DB::table($p['table'])
                        ->where('member_id', $target->id)
                        ->where($p['other'], $row->{$p['other']})
                        ->exists();
                    if ($exists) {
                        DB::table($p['table'])
                            ->where('member_id', $source->id)
                            ->where($p['other'], $row->{$p['other']})
                            ->delete();
                    } else {
                        DB::table($p['table'])
                            ->where('member_id', $source->id)
                            ->where($p['other'], $row->{$p['other']})
                            ->update(['member_id' => $target->id]);
                    }
                }
            }

            // member_relationships: reassociar nas duas pontas (member_id e related_member_id).
            foreach (['member_id', 'related_member_id'] as $col) {
                $otherCol = $col === 'member_id' ? 'related_member_id' : 'member_id';
                $rows = DB::table('member_relationships')->where($col, $source->id)->get();
                foreach ($rows as $row) {
                    // Auto-referência após reassign → descarta.
                    if ($row->{$otherCol} === $target->id) {
                        DB::table('member_relationships')->where('id', $row->id)->delete();
                        continue;
                    }
                    $memberId = $col === 'member_id' ? $target->id : $row->member_id;
                    $relatedId = $col === 'related_member_id' ? $target->id : $row->related_member_id;
                    $exists = DB::table('member_relationships')
                        ->where('member_id', $memberId)
                        ->where('related_member_id', $relatedId)
                        ->where('relationship_type', $row->relationship_type)
                        ->where('id', '!=', $row->id)
                        ->exists();
                    if ($exists) {
                        DB::table('member_relationships')->where('id', $row->id)->delete();
                    } else {
                        DB::table('member_relationships')
                            ->where('id', $row->id)
                            ->update([$col => $target->id]);
                    }
                }
            }

            // Apagar source (soft delete já incluso pelo trait).
            $source->delete();
        });

        $target->refresh()->load('ministries:id,name');

        return response()->json([
            'success' => true,
            'message' => 'Cadastro mesclado com sucesso',
            'data' => $target,
        ]);
    }

    /**
     * Validate if CPF exists in database.
     */
    public function validateCpf(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cpf' => 'required|string',
            'election_id' => 'nullable|integer|exists:elections,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'CPF é obrigatório',
                'errors' => $validator->errors()
            ], 422);
        }

        // Remover formatação do CPF (pontos e traço)
        $cpf = preg_replace('/[^0-9]/', '', $request->cpf);

        // Buscar membro pelo CPF (com ou sem formatação)
        $member = Member::where(function($query) use ($cpf, $request) {
            $query->where('cpf', $request->cpf)
                  ->orWhere('cpf', $cpf);
        })->first();

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'CPF não encontrado na base de dados'
            ], 404);
        }

        // Buscar todas as eleições em que já votou
        $votedElectionIds = \App\Models\Vote::where('member_id', $member->id)
            ->distinct('election_id')
            ->pluck('election_id')
            ->toArray();

        // Para compatibilidade com código antigo, verificar eleição específica
        $hasVoted = false;
        if ($request->election_id) {
            $hasVoted = in_array($request->election_id, $votedElectionIds);
        }

        return response()->json([
            'success' => true,
            'message' => 'CPF válido',
            'data' => [
                'member_id' => $member->id,
                'name' => $member->name,
                'has_voted' => $hasVoted,
                'voted_elections' => $votedElectionIds
            ]
        ]);
    }
}
