<?php

namespace App\Http\Controllers\Api\Bot;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

abstract class BotBaseController extends Controller
{
    protected function ok(mixed $data = null, string $message = 'OK'): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data,
        ]);
    }

    protected function created(mixed $data = null, string $message = 'Creado'): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data,
        ], 201);
    }

    protected function notFound(string $message = 'Recurso no encontrado.'): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
            'errors' => [],
        ], 404);
    }

    protected function error(string $message, array $errors = [], int $status = 422): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }
}
