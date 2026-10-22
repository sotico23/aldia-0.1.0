<?php

namespace App\Support;

/**
 * Contexto inmutable del agente/cliente API para la request actual.
 *
 * Se resuelve en App\Http\Middleware\Bot\ResolveAgentContext y se expone
 * en $request->attributes->get('agent_context').
 *
 * - ownerId       : tenant (raiz de la cadena creator_id) sobre el que se
 *                   permite operar. NUNCA se toma del cuerpo de la request.
 * - actingUserId  : usuario que ejecuta la accion (auditoria / user_id).
 * - abilities     : permisos del token (Sanctum). El wildcard `*` concede todo.
 * - legacyToken   : true cuando la credencial es el `users.api_token` legacy
 *                   (compatibilidad con los workflows de n8n ya desplegados).
 * - tokenName     : nombre del token ("nombre del dispositivo" en Sanctum).
 */
final readonly class AgentContext
{
    /**
     * @param  array<int, string>  $abilities
     */
    public function __construct(
        public int $ownerId,
        public int $actingUserId,
        public array $abilities = ['*'],
        public bool $legacyToken = false,
        public ?string $tokenName = null,
    ) {}

    /**
     * Comprueba si el token tiene una ability concreta (soporta el wildcard `*`).
     */
    public function can(string $ability): bool
    {
        return in_array('*', $this->abilities, true)
            || in_array($ability, $this->abilities, true);
    }
}
