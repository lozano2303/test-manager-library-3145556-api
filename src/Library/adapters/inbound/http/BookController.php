<?php

namespace Library\Adapters\Inbound\Http;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BookController
{
    public function index(Request $request): JsonResponse
    {
        $query = $request->query('q');
        $category = $request->query('category');
        $page = (int) $request->query('page', 1);
        $size = (int) $request->query('size', 20);
        
        if ($size > 50) {
            return response()->json(['error' => 'Page size cannot exceed 50'], 400);
        }
        
        // Mock response
        return response()->json([
            'books' => [],
            'total' => 0,
            'page' => $page,
            'size' => $size,
            'totalPages' => 0
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'title' => 'required|string',
                'author' => 'required|string', 
                'isbn' => 'required|string|size:13',
                'category_id' => 'required|string',
                'total_copies' => 'required|integer|min:1'
            ]);
            
            return response()->json([
                'id' => uniqid(),
                'title' => $data['title'],
                'author' => $data['author'],
                'isbn' => $data['isbn'],
                'category_id' => $data['category_id'],
                'total_copies' => $data['total_copies'],
                'available_copies' => $data['total_copies'],
                'cover_key' => null
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function show(string $id): JsonResponse
    {
        // Mock response - would use SearchBooksService
        return response()->json([
            'id' => $id,
            'title' => 'Sample Book',
            'author' => 'Sample Author',
            'isbn' => '1234567890123',
            'category_id' => 'cat-1',
            'total_copies' => 5,
            'available_copies' => 3
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        // Mock response - would use DeleteBookService
        return response()->json(null, 204);
    }
}