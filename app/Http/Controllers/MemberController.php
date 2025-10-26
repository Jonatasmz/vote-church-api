<?php

namespace App\Http\Controllers;

use App\Models\Member;
use Illuminate\Http\Request;
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
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
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
            'rg' => 'nullable|string|max:20',
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
            'rg' => 'nullable|string|max:20',
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

        // Verificar se já votou na eleição específica (se fornecida)
        $hasVoted = false;
        if ($request->election_id) {
            $hasVoted = \App\Models\Vote::where('member_id', $member->id)
                ->where('election_id', $request->election_id)
                ->exists();
        }

        return response()->json([
            'success' => true,
            'message' => 'CPF válido',
            'data' => [
                'member_id' => $member->id,
                'name' => $member->name,
                'has_voted' => $hasVoted
            ]
        ]);
    }
}
