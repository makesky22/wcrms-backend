<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Always return JSON 401 for unauthenticated API requests.
     * Never redirect to a web "login" route.
     */
    protected function unauthenticated(
        $request,
        AuthenticationException $exception
    ) {
        return response()->json([
            'message' => 'Unauthenticated. Please log in.',
        ], 401);
    }
}