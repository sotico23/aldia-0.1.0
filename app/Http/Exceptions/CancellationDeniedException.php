<?php

namespace App\Http\Exceptions;

use Exception;
use Symfony\Component\HttpFoundation\Response;

class CancellationDeniedException extends Exception
{
    protected $message;

    public function __construct(string $message = 'No se puede cancelar la cita fuera del plazo permitido.')
    {
        parent::__construct($message);
    }

    public function render($request): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['error' => $this->message], 400);
        }

        return back()->withErrors(['error' => $this->message])->withInput();
    }
}
