# Event-Driven Messaging & Notification Architecture Audit Report (Enterprise Level)

## FASE 1 — MAPA COMPLETO DE MENSAJERÍA
El sistema presenta una arquitectura distribuida pero fuertemente duplicada. Existen **tres (3) flujos paralelos y aislados** para el manejo de chats, cada uno con sus propios controladores, modelos, eventos y UI.

### Flujo 1: Marketplace Chat (Store ↔ Buyer)
*Usuario inicia chat general con una tienda desde su perfil público.*
**Flujo:** `User UI` → `ChatController@sendMessage` → `Message Model` → `MessageSent Event` → `NuevoMensajeChatNotification` → `Reverb (private-conversation.{id})` → `ChatShow.tsx`

### Flujo 2: Order Chat (Vendedor ↔ Comprador)
*Usuario chatea sobre un pedido específico.*
**Flujo:** `User UI` → `ConversacionPedidoController@enviarMensaje` → `MensajeConversacion Model` → `MensajeEnviado Event` → `NuevoMensajeChatPedidoNotification` → `Reverb (private-conversacion.{id})` → `ChatPedido.tsx`

### Flujo 3: Internal Chat (Tenant / Empleados)
*Comunicación interna entre empleados del mismo tenant.*
**Flujo:** `User UI` → `MensajeController@enviar` → `Mensaje Model` → `MensajeInternoEnviado Event` → `NuevoMensajeInternoNotification` → `Reverb (private-user.{receiver_id})`

---

## FASE 2 — MAPA DE NOTIFICACIONES

| Notificación | Trigger | Canal | Event Source | Archivo |
| :--- | :--- | :--- | :--- | :--- |
| `NuevoMensajeChatNotification` | Nuevo msj Marketplace | `database`, `mail` | `ChatController@sendMessage` | `/app/Notifications/NuevoMensajeChatNotification.php` |
| `NuevoMensajeChatPedidoNotification` | Nuevo msj Pedido | `database`, `mail` | `ConversacionPedidoController` | `/app/Notifications/...` |
| `NuevoMensajeInternoNotification` | Nuevo msj interno | `database`, `mail` | `MensajeController@enviar` | `/app/Notifications/...` |
| `NuevaReaccion` | Reacción a publicación | `database`, `mail` | `PublicacionController@react` | `/app/Notifications/NuevaReaccion.php` |
| `NuevoPedidoNotification` | Creación de pedido | `database`, `mail` | `PedidoController@crear` | `/app/Notifications/NuevoPedidoNotification.php` |
| `PaymentReceivedNotification` | Pago confirmado | `database`, `mail` | `Webhooks / Gateways` | `/app/Notifications/PaymentReceivedNotification.php` |

---

## FASE 3 — MAPA DE EVENTOS DE COMUNICACIÓN

| Event | Trigger | Listener / Broadcast | Job | Archivo |
| :--- | :--- | :--- | :--- | :--- |
| `MessageSent` | `ChatController` | Broadcast: `conversation.{id}` | N/A | `/app/Events/MessageSent.php` |
| `MensajeEnviado` | `ConversacionPedidoController` | Broadcast: `conversacion.{id}` | N/A | `/app/Events/MensajeEnviado.php` |
| `MensajeInternoEnviado`| `MensajeController` | Broadcast: `user.{id}` | N/A | `/app/Events/MensajeInternoEnviado.php` |
| `MensajesLeidosConversation`| `ChatController@show` | Broadcast: `conversation.{id}` | N/A | `/app/Events/MensajesLeidosConversation.php` |
| `PaymentSuccessful` | Confirmación pago | Listener: `SendPaymentSuccessful` | Queue | `/app/Events/PaymentSuccessful.php` |

---

## FASE 4 — FLUJO REAL END-TO-END

El sistema utiliza **Laravel Reverb** (`/resources/js/echo.ts`) para WebSockets.

**Sincronización Realtime vs Fallback:**
1. **Frontend (React)** se suscribe a canales privados vía `Echo.private()`.
2. Las notificaciones se despachan a colas (`implements ShouldQueue` en clases de Notificación).
3. **Fallback (Polling):** En `ChatShow.tsx` (L118-123), existe un polling de seguridad: `setInterval(() => router.reload(), 30000);`. Esto garantiza que los mensajes lleguen si el socket falla. *Sin embargo, no vi este polling en `ChatPedido.tsx`.*

---

## FASE 5 — POLÍTICAS Y SEGURIDAD

- **Acceso a chats privados:** La autorización está implementada de forma manual dentro de cada controlador (`if ($conversation->buyer_id !== $user->id) abort(403);`) en lugar de utilizar `Policies`.
- **Rutas de canales (`routes/channels.php`):** Correctamente protegidas con closures para validar pertenencia (comprador/vendedor o propietario).
- **Rate Limiting:** Los endpoints de envío (`/chat/.../messages` y `/conversaciones-pedidos/.../mensajes`) tienen el middleware `throttle:messages`, mitigando ataques de spam.
- **Riesgo:** La validación manual en controladores es propensa a errores humanos si se añaden nuevos endpoints.

---

## FASE 6 — FRONTEND CONSISTENCY

- **Optimistic Updates:** Ausentes. El frontend espera la confirmación del servidor (espera a la respuesta 201 o al broadcast) antes de renderizar el mensaje en la pantalla.
- **Desincronización Ui vs Backend:** En `ChatShow.tsx`, se gestiona la lista `localMessages`. Al usar Inerta `router.reload` (polling), puede ocurrir superposición de estado si el broadcast llega milisegundos antes del reload.
- **Whispering (Typing Indicator):** Correctamente implementado en todos los chats (`channel.whisper('typing')`).

---

## HALLAZGOS POR PRIORIDAD

### FASE 7 — HALLAZGOS CRÍTICOS (Seguridad & Pérdida de eventos)
1. **Falta de Fallback en ChatPedido:** A diferencia de `ChatShow`, `ChatPedido.tsx` no tiene fallback de polling. Si Reverb se desconecta, el usuario perderá mensajes hasta que recargue la página.

### FASE 8 — HALLAZGOS ALTOS (Arquitectura)
1. **Duplicación Masiva (Triple Chat):** Existen 3 modelos de conversación (`Conversation`, `Conversacion`, `Mensaje`) y 3 controladores con 90% de lógica idéntica. Esto dificulta escalar y mantener el sistema.
2. **Validación sin Policies:** La lógica de autorización está embebida en los controladores en vez de `ChatPolicy` o `ConversationPolicy`.

### FASE 9 — HALLAZGOS MEDIOS
1. **Naming Inconsistente:** Mezcla de "Spanglish" en todo el sistema (`Conversacion` vs `Conversation`, `Mensaje` vs `Message`, `comprador_id` vs `buyer_id`).

---

## FASE 11 — PLAN DE REFACTORIZACIÓN

1. **Unificar Modelos (Semana 1):** Crear un modelo único Polimórfico `Conversation` que soporte `type = 'marketplace' | 'order' | 'internal'`, apuntando siempre a un único modelo `Message`.
2. **Centralizar Autorización (Semana 2):** Migrar las verificaciones de `$id !== auth()->id()` hacia `ConversationPolicy@view` y `ConversationPolicy@sendMessage`.
3. **Consistencia Frontend (Semana 3):** Crear un Hook de React `useChat(conversationId)` que encapsule la lógica de Reverb, el fallback de polling y el whisper (escribiendo...), para usarlo en todos los componentes y eliminar la duplicación de código React.

---

## FASE 12 — ROADMAP (30 / 60 / 90 DÍAS)

- **30 días (Seguridad y Estabilización):** Añadir fallback polling a `ChatPedido.tsx`. Implementar `ConversationPolicy` para centralizar la seguridad de lectura/escritura.
- **60 días (Refactorización Core):** Unificar los 3 sistemas de chat en un solo servicio / controlador / modelo polimórfico. Migrar base de datos unificada (un solo `messages` y `conversations`).
- **90 días (Escalabilidad Realtime):** Implementar Optimistic Updates en React (mutar la UI antes del request HTTP) y habilitar caché con Redis para historiales de chat antiguos.
