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
    public function index()
    {
        $members = Member::orderBy('name')->get();
        
        return response()->json([
            'success' => true,
            'data' => $members
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'member_since' => 'required|string|size:4',
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
            'description' => 'nullable|string',
            'member_since' => 'sometimes|required|string|size:4',
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
}
