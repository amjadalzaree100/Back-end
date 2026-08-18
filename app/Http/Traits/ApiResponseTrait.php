<?php

namespace App\Http\Traits;

trait ApiResponseTrait
{
    protected function successResponse($data = null, string $message = null, int $code = 200)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    protected function errorResponse(string $message = null, int $code = 400, $errors = null)
    {
        $response = [
            'success' => false,
            'message' => $message ?? 'An error occurred',
        ];

        if ($errors) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }

    protected function unauthorizedResponse(string $message = 'Unauthorized')
    {
        return $this->errorResponse($message, 401);
    }

    protected function forbiddenResponse(string $message = 'Forbidden')
    {
        return $this->errorResponse($message, 403);
    }

    protected function notFoundResponse(string $message = 'Resource not found')
    {
        return $this->errorResponse($message, 404);
    }

    protected function validationErrorResponse($errors)
    {
        return $this->errorResponse('Validation error', 422, $errors);
    }
}
