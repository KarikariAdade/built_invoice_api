<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class AppServices
{
    public function generateResponse($message, $data, $error_code = null, $type = null): \Illuminate\Http\JsonResponse
    {
        if ($type === 'error') {
            return response()->json([
                'message' => $message,
                'data' => $data
            ], $error_code ?? 401);
        }

        return response()->json([
            'message' => $message,
            'data' => $data
        ]);
    }

    public function generateLog($channel, $header, $message): void
    {
        Log::channel($channel)->alert($header, $message);
    }

    public function generateLogData($data): array
    {
        return ['message' => $data->getMessage(), 'file' => $data->getFile(), 'line' => $data->getLine()];
    }
}
