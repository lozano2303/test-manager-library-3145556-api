<?php

namespace Library\Adapters\Inbound\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Health Check Controller
 * 
 * Simple health check endpoint following hexagonal architecture.
 * This is an inbound adapter that exposes the health check functionality.
 */
class HealthController
{
    /**
     * Health check endpoint
     * 
     * Returns a simple status response to verify the API is running.
     * This endpoint is public and doesn't require authentication.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function check(Request $request): JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }
}