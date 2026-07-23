<?php

namespace App\Http\Exceptions;

use Exception;
use Symfony\Component\HttpFoundation\Response;

class SlotUnavailableException extends Exception
{
    protected $message;

    public function __construct(string $message = 'El horario solicitado no está disponible.')
    {
        parent::__construct($message);
    }

    public function render($request): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['error' => $this->message], 409);
        }

        return back()->withErrors(['error' => $this->message])->withInput();
    }
}
