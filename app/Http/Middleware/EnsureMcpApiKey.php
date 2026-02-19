<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMcpApiKey
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $providedApiKey = $request->header('X-AI-Agent-Api-Key') ?? $request->bearerToken();

        $expectedApiKey = (string) config('services.mcp.api_key', '');

        if ($expectedApiKey === '' || $providedApiKey !== $expectedApiKey) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or missing MCP API key.',
            ], 401);
        }

        return $next($request);
    }
}
