<?php

namespace App\Support;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Turns any exception raised under /api into one JSON shape:
 *
 *   { "error": { "status": 404, "code": "not_found", "message": "Product 42 not found.", "details": {...} } }
 *
 * "details" is only present for validation errors (field => messages). Stack traces are never returned.
 */
class ApiError
{
    private const CODES = [
        400 => 'bad_request',
        401 => 'unauthenticated',
        403 => 'forbidden',
        404 => 'not_found',
        405 => 'method_not_allowed',
        409 => 'conflict',
        419 => 'session_expired',
        422 => 'validation_failed',
        429 => 'too_many_requests',
        500 => 'server_error',
        503 => 'service_unavailable',
    ];

    public static function response(int $status, string $message, ?array $details = null, ?string $code = null): JsonResponse
    {
        $error = [
            'status' => $status,
            'code' => $code ?? self::CODES[$status] ?? 'error',
            'message' => $message,
        ];

        if ($details !== null) {
            $error['details'] = $details;
        }

        return new JsonResponse(['error' => $error], $status);
    }

    public static function fromThrowable(Throwable $e): JsonResponse
    {
        if ($e instanceof HttpResponseException) {
            return $e->getResponse();
        }

        if ($e instanceof ValidationException) {
            return self::response(422, 'The given data was invalid.', $e->errors());
        }

        if ($e instanceof AuthenticationException) {
            return self::response(401, 'Unauthenticated.');
        }

        if ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();
            $previous = $e->getPrevious();

            if ($previous instanceof ModelNotFoundException) {
                $model = class_basename($previous->getModel());
                $ids = implode(', ', $previous->getIds());

                return self::response(404, trim("{$model} {$ids}").' not found.');
            }

            return self::response($status, $e->getMessage() ?: (Response::$statusTexts[$status] ?? 'Error'));
        }

        if ($e instanceof QueryException) {
            // 23503 = foreign key violation, 23505 = unique violation (Postgres SQLSTATE codes)
            return match ($e->getCode()) {
                '23503' => self::response(409, 'This record is still referenced by other records.'),
                '23505' => self::response(409, 'A record with the same unique value already exists.'),
                default => self::serverError($e),
            };
        }

        return self::serverError($e);
    }

    private static function serverError(Throwable $e): JsonResponse
    {
        $response = self::response(500, 'Something went wrong. Please try again later.');

        if (config('app.debug')) {
            $data = $response->getData(true);
            $data['error']['debug'] = ['exception' => $e::class, 'message' => $e->getMessage()];
            $response->setData($data);
        }

        return $response;
    }
}
