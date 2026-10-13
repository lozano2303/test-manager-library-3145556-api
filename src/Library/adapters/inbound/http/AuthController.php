<?php

namespace Library\Adapters\Inbound\Http;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AuthController
{
    public function login(Request $request): JsonResponse
    {
        try {
            // Basic implementation - would use LoginService
            $username = $request->input('username');
            $password = $request->input('password');
            
            if (!$username || !$password) {
                return response()->json(['error' => 'Username and password required'], 400);
            }
            
            // Mock token for now
            $token = 'mock_token_' . time();
            
            return response()->json([
                'token' => $token,
                'expires_in' => 28800 // 8 hours
            ]);
            
        } catch (\Exception $e) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }
    }

    public function createLibrarian(Request $request): JsonResponse
    {
        try {
            // Basic implementation - would use CreateLibrarianService
            $username = $request->input('username');
            $password = $request->input('password');
            $role = $request->input('role');
            
            if ($role === 'admin') {
                return response()->json(['error' => 'Cannot create admin via API'], 422);
            }
            
            return response()->json([
                'id' => uniqid(),
                'username' => strtolower(trim($username)),
                'role' => 'librarian'
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}