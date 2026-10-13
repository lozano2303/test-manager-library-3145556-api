<?php

namespace Library\Adapters\Inbound\Http;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class LoanController
{
    public function store(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'member_id' => 'required|string',
                'book_ids' => 'required|array|min:1|max:3',
                'book_ids.*' => 'string'
            ]);
            
            // Basic validation
            if (count(array_unique($data['book_ids'])) !== count($data['book_ids'])) {
                return response()->json(['error' => 'Cannot repeat books in same loan'], 422);
            }
            
            return response()->json([
                'id' => uniqid(),
                'member_id' => $data['member_id'],
                'user_id' => 'current_user',
                'loaned_at' => now()->toISOString(),
                'status' => 'open',
                'items' => array_map(fn($bookId) => [
                    'id' => uniqid(),
                    'book_id' => $bookId,
                    'due_at' => now()->addDays(14)->toISOString(),
                    'returned_at' => null
                ], $data['book_ids'])
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function returnItem(string $loanId, string $itemId): JsonResponse
    {
        // Mock response - would use ReturnBookService
        return response()->json([
            'item_id' => $itemId,
            'returned_at' => now()->toISOString()
        ]);
    }

    public function overdue(): JsonResponse
    {
        // Mock response - would use OverdueBooksService
        return response()->json([
            'overdue_items' => []
        ]);
    }
}