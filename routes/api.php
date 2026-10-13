<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Library\Adapters\Inbound\Http\HealthController;
use Library\Adapters\Inbound\Http\AuthController;
use Library\Adapters\Inbound\Http\BookController;
use Library\Adapters\Inbound\Http\LoanController;

/*
|--------------------------------------------------------------------------
| API Routes - T-16 Implementation
|--------------------------------------------------------------------------
*/

// Health check endpoint
Route::get('/health', [HealthController::class, 'check']);

// Authentication routes (public)
Route::post('/auth/login', [AuthController::class, 'login']);

// Protected routes (basic implementation)
Route::group(['middleware' => []], function () {
    // User management (admin only)
    Route::post('/users/librarians', [AuthController::class, 'createLibrarian']);
    
    // Books
    Route::get('/books', [BookController::class, 'index']);
    Route::post('/books', [BookController::class, 'store']);
    Route::get('/books/{id}', [BookController::class, 'show']);
    Route::delete('/books/{id}', [BookController::class, 'destroy']);
    
    // Loans  
    Route::post('/loans', [LoanController::class, 'store']);
    Route::post('/loans/{loanId}/items/{itemId}/return', [LoanController::class, 'returnItem']);
    Route::get('/loans/overdue', [LoanController::class, 'overdue']);
});