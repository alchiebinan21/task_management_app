<?php

use App\Http\Controllers\API\TaskController;
use Illuminate\Support\Facades\Route;

Route::middleware('mcp.api')
    ->prefix('mcp')
    ->name('mcp.')
    ->group(function (): void {
        Route::get('health', function () {
            return response()->json([
                'success' => true,
                'message' => 'MCP API key is valid.',
            ]);
        })->name('health');

        Route::apiResource('tasks', TaskController::class);
    });
