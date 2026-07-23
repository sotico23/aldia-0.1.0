# System Architecture + Security Audit Report (Enterprise Level)

**Project:** AldiaProyect — ERP SaaS Platform  
**Date:** 2026-06-27  
**Auditor:** Principal Software Architect / Senior Security Auditor  
**Stack:** Laravel 12 / PHP 8.4 / React 19 / Inertia v2 / MySQL 8.0  
**Scope:** Full backend (controllers, services, models, events, jobs, notifications, middleware, routes) + Frontend (React/Inertia pages, components, hooks, layouts)

---

## FASE 1 — MAPA COMPLETO DE ARQUITECTURA

### Diagrama Textual del Sistema

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           HTTP / HTTPS                                        │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  ┌──────────┐  ┌───────────┐  ┌──────────────┐  ┌──────────────────────┐   │
│  │  Public   │  │    Auth   │  │  Client /    │  │   Backend (Admin)    │   │
│  │  Routes   │  │  (Fortify)│  │  Proveedor   │  │   80 controllers     │   │
│  │  /tienda  │  │  /auth/*  │  │  /cliente/*  │  │  /admin/*            │   │
│  │  /booking │  │           │  │  /proveedor/*│  │                      │   │
│  └─────┬─────┘  └─────┬─────┘  └──────┬───────┘  └──────────┬───────────┘   │
│        │              │               │                     │              │
│        └──────────────┴───────────────┴─────────────────────┘              │
│                                  │                                          │
│                    ┌─────────────┴─────────────┐                            │
│                    │     Middleware Stack      │                            │
│                    │  web: SecurityHeaders,    │                            │
│                    │  HandleAppearance,        │                            │
│                    │  HandleInertiaRequests,   │                            │
│                    │  TrackPageViews,          │                            │
│                    │  CheckActive,             │                            │
│                    │  RedirectIfClient/        │                            │
│                    │  RedirectIfProveedor      │                            │
│                    │  Permission/CheckRole/    │                            │
│                    │  CheckOwnership           │                            │
│                    └─────────────┬─────────────┘                            │
│                                  │                                          │
│              ┌───────────────────┴────────────────────┐                     │
│              │                                        │                     │
│     ┌────────┴────────┐                  ┌────────────┴────────────┐       │
│     │ Inertia Response│                  │    JSON Response        │       │
│     │  (SSR + SPA)    │                  │   (API / Webhook /      │       │
│     │  React Pages    │                  │    Internal)            │       │
│     └────────┬────────┘                  └────────────┬────────────┘       │
│              │                                        │                     │
└──────────────┼────────────────────────────────────────┼─────────────────────┘
               │                                        │
     ┌─────────┴────────────────────────────────────────┴─────────────┐
     │                     LARAVEL APPLICATION                        │
     │                                                                │
     │  ┌────────────────────────────────────────────────────────┐   │
     │  │              SERVICE LAYER (23 services)               │   │
     │  │  N8nService │ WebpayService │ GoogleCalendarService    │   │
     │  │  TelegramService │ WhatsAppService │ VentaService      │   │
     │  │  InventarioService │ SplitPaymentManager               │   │
     │  │  MailConfigurationService │ SiiService │ ...           │   │
     │  └────────────────────────────────────────────────────────┘   │
     │                                                                │
     │  ┌────────────────────────────────────────────────────────┐   │
     │  │              ELOQUENT MODELS (115+)                    │   │
     │  │  97 with BelongsToOwner | 4 with BelongsToBusiness     │   │
     │  │  21 without tenant scoping                             │   │
     │  └────────────────────────────────────────────────────────┘   │
     │                                                                │
     │  ┌─────────────────┐  ┌──────────────┐  ┌────────────────┐   │
     │  │  Events (6)     │  │  Listeners   │  │  Jobs (4)      │   │
     │  │  PedidoCreado   │  │  (2)         │  │  SendToN8nJob  │   │
     │  │  PaymentSuccess │  │  SendPayment │  │  RunAutomation │   │
     │  │  MessageSent    │  │  Successful  │  │  RenewSubscrip │   │
     │  │  MensajeEnviado │  │  Notification│  │  CheckUptime   │   │
     │  │  MensajesLeidos │  │  SendPedido  │  └────────────────┘   │
     │  │  MailConfigErr  │  │  CreadoBuyer │                       │
     │  └─────────────────┘  └──────────────┘                       │
     │                                                                │
     │  ┌────────────────────────────────────────────────────────┐   │
     │  │         NOTIFICATIONS (13, all ShouldQueue)            │   │
     │  │  PaymentReceived │ NuevoMensaje* (3) │ NuevoPedido    │   │
     │  │  PedidoCreadoComprador │ ActualizacionEstado           │   │
     │  │  NuevoTicket │ TempPassword │ WelcomeProveedor         │   │
     │  │  NuevaReaccion │ NuevoComentario │ AutomationFailure   │   │
     │  └────────────────────────────────────────────────────────┘   │
     │                                                                │
     │  ┌────────────────────────────────────────────────────────┐   │
     │  │         MIDDLEWARE (13) + POLICIES + SCOPES            │   │
     │  └────────────────────────────────────────────────────────┘   │
     │                                                                │
     └────────────────────────────────────────────────────────────────┘
                              │
            ┌─────────────────┴─────────────────┐
            │                                   │
     ┌──────┴──────┐                   ┌────────┴────────┐
     │  MySQL 8.0  │                   │   Queue / Jobs  │
     │  130 tables │                   │   (database)    │
     └─────────────┘                   └─────────────────┘
            │                                   │
            └───────────────────────────────────┘
                              │
                    ┌─────────┴─────────┐
                    │  External Systems │
                    │                   │
                    │  • MercadoPago    │
                    │  • PayPal         │
                    │  • Transbank      │
                    │    (Webpay)       │
                    │  • n8n            │
                    │  • Google OAuth   │
                    │  • Facebook OAuth │
                    │  • Google Calendar│
                    │  • Telegram API   │
                    │  • WhatsApp API   │
                    │  • SII (Chile)    │
                    │  • Mailtrap       │
                    └───────────────────┘
```

### Módulos Principales (18 módulos)

| # | Módulo | Controllers | Models | Routes Prefix | Pages |
|---|--------|------------|--------|---------------|-------|
| 1 | **CRM** | 5 | 5 | `crm.php` | Prospectos, Oportunidades, Clientes, Campañas, Tickets, CallCenter |
| 2 | **Ventas/POS** | 7 | 8 | `ventas.php` | Ventas, POS, Cupones, Variantes, SKUs, Cotizaciones |
| 3 | **Inventario** | 5 | 8 | `inventario.php` | Productos, Almacenes, Lotes, Movimientos, Cierres, Vacíos |
| 4 | **MRP** | 3 | 4 | `mrp.php` | BOMs, Órdenes Producción, Calidad, Planificación |
| 5 | **Finanzas** | 6 | 8 | `finanzas.php` | Cobranzas, Pagos, Tesorería, Contabilidad, Impuestos, SII |
| 6 | **RRHH** | 4 | 4 | `rrhh.php` | Empleados, Nóminas, Asistencia, Evaluaciones, Reclutamiento |
| 7 | **Proyectos** | 3 | 3 | `proyectos.php` | Proyectos, Hitos, Timesheets, Gastos |
| 8 | **Flota** | 4 | 6 | `flota.php` | Vehículos, Conductores, Entregas, Cargas Diarias |
| 9 | **Marketplace** | 4 | 8 | `web.php` | Tienda, Órdenes, Chat, Reviews |
| 10 | **LMS** | 5 | 9 | `lms.php` | Cursos, Módulos, Lecciones, Quizzes, Certificados |
| 11 | **Raffles** | 2 | 3 | `raffles.php` | Sorteos, Premios, Participantes, Salas |
| 12 | **Pagos (3 gateways)** | 10 | 5 | `web.php` | MercadoPago, PayPal, Webpay + Split Payment |
| 13 | **Comunidad/Social** | 4 | 5 | `web.php` | Publicaciones, Comentarios, Reacciones, Followers |
| 14 | **Notificaciones** | — | 1 | — | Preferencias, Logs |
| 15 | **Automation/n8n** | 5 | 4 | `api.php` | Automations, Ejecuciones, Canales |
| 16 | **Admin/Settings** | 15+ | 10 | `admin.php` | Web Settings, Financial, Gateways, Mail, Users/Roles |
| 17 | **Uptime Monitoring** | 2 | 4 | `uptime.php` | Sites, Checks, Alertas, Incidentes |
| 18 | **Email Marketing** | 2 | 4 | `admin.php` | MailTemplates, MailConfig, Logs |

### Dependencias entre Capas

```
Request → Middleware (auth, permission, role, ownership, scope)
   → Controller (validates, authorizes, orchestrates)
       → Service (business logic, external API calls)
           → Model/Eloquent (DB queries, scoped by OwnerScope/BusinessScope)
               → MySQL
       → Event dispatch (synchronous)
           → Listener (sync dispatch → queued Notification)
               → Notification (ShouldQueue) → MailTemplate (DynamicNotification)
       → Job dispatch (queued)
           → N8nService | TelegramService | WhatsAppService
   → Response (Inertia page / JsonResponse / Redirect)
```

### Flujo Request → Response

```
1. HTTP Request → bootstrap/app.php → Middleware pipeline
2. Route matched → Controller::method()
3. Controller::method():
   a. $request->validate() → FormRequest or inline validation
   b. Permission check via middleware or usePermissions() hook
   c. OwnerScope/BusinessScope applied automatically (global scopes)
   d. Business logic in Service layer or inline
   e. Event dispatch (if applicable)
   f. Response: Inertia::render() / response()->json() / redirect()
4. Inertia renders React page (SSR or CSR)
5. React page mounts → fetches additional data via axios/router
6. Client-side interactions → Inertia visits or API calls
```

---

## FASE 2 — MAPA DE EVENTOS

### Lista Completa de Events (6)

| Event | Trigger | Listener | Side Effects | Archivo |
|-------|---------|----------|--------------|---------|
| `PedidoCreado` | `BookingController@store` (line 100), `PedidoController@crear` (line 96) | `SendPedidoCreadoBuyerNotification` | `PedidoCreadoCompradorNotification` (queued, mail+database) | `app/Events/PedidoCreado.php` |
| `PaymentSuccessful` | `WebpayController@callback` (line 196) | `SendPaymentSuccessfulNotification` | `PaymentReceivedNotification` (queued, mail+database) | `app/Events/PaymentSuccessful.php` |
| `MessageSent` | `ChatController@sendMessage` (line 181) | None | Broadcast to `conversation.{id}` private channel | `app/Events/MessageSent.php` |
| `MensajeEnviado` | `ConversacionPedidoController@enviarMensaje` (line 185) | None | Broadcast to `conversacion.{id}` private channel | `app/Events/MensajeEnviado.php` |
| `MensajesLeidos` | `ConversacionPedidoController@getMensajes` (line 128) | None | Broadcast to `conversacion.{id}` private channel | `app/Events/MensajesLeidos.php` |
| `MailConfigErrorOccurred` | `MailConfigurationService@logTestResult` (line 66) | None | None (pure data event) | `app/Events/MailConfigErrorOccurred.php` |

### Listeners Asociados (2)

| Listener | Listens To | Method | File |
|----------|-----------|--------|------|
| `SendPaymentSuccessfulNotification` | `PaymentSuccessful` | `handle()`: notifies business owner via `PaymentReceivedNotification` | `app/Listeners/SendPaymentSuccessfulNotification.php` |
| `SendPedidoCreadoBuyerNotification` | `PedidoCreado` | `handle()`: notifies buyer via `PedidoCreadoCompradorNotification` | `app/Listeners/SendPedidoCreadoBuyerNotification.php` |

### Jobs Dispatched (4)

| Job | Trigger | Queue | Retries | File |
|-----|---------|-------|---------|------|
| `SendToN8nJob` | `RunAutomationJob`, Automation dispatch | Default | 3 (5s, 30s, 120s) | `app/Jobs/SendToN8nJob.php` |
| `RunAutomationJob` | `automations:dispatch` command (everyMinute) | Default | 3 (5s, 30s, 120s) | `app/Jobs/RunAutomationJob.php` |
| `RenewSubscription` | `subscriptions:renew` command (daily 04:00) | Default | — | `app/Jobs/RenewSubscription.php` |
| `CheckUptimeJob` | `uptime:check` command (everyMinute) | `uptime` queue | — | `app/Jobs/CheckUptimeJob.php` |

### Flujo Event-Driven Real

```
Payment Flow (Webpay only):
  WebpayController@callback success
    → fires PaymentSuccessful event (sync)
      → SendPaymentSuccessfulNotification listener
        → PaymentReceivedNotification (queued, mail+database)

Order Flow:
  PedidoController@crear / BookingController@store
    → fires PedidoCreado event (sync)
      → SendPedidoCreadoBuyerNotification listener
        → PedidoCreadoCompradorNotification (queued, mail+database)

Chat Flow:
  ChatController@sendMessage
    → broadcast(MessageSent) → toOthers() (sync, private channel)
    → NuevoMensajeChatNotification (queued, mail+database)

  ConversacionPedidoController@getMensajes
    → broadcast(MensajesLeidos) → toOthers() (sync, private channel)

  ConversacionPedidoController@enviarMensaje
    → broadcast(MensajeEnviado) → toOthers() (sync, private channel)
    → NuevoMensajeChatPedidoNotification (queued, mail+database)

Internal Messaging:
  MensajeController@enviar
    → NuevoMensajeInternoNotification (queued, mail+database)
    → No broadcast events
```

### Eventos Duplicados o Sin Uso

| Evento | Problema | Evidencia |
|--------|----------|-----------|
| `MailConfigErrorOccurred` | Creado pero sin listeners | `app/Events/MailConfigErrorOccurred.php` — no hay listeners registrados en `AppServiceProvider` |
| `MessageSent` | Chat duplicado conceptual: dos eventos (`MessageSent` y `MensajeEnviado`) para casi el mismo propósito, en dominios distintos (marketplace vs order-chat) | `app/Events/MessageSent.php` y `app/Events/MensajeEnviado.php` |
| `MensajesLeidos` | Sólo se dispara en Order Chat, no en Marketplace Chat ni Mensajes Internos | `ConversacionPedidoController:128`, ausente en `ChatController` y `MensajeController` |

### Eventos Faltantes Críticos

| Evento Faltante | Justificación | Impacto |
|----------------|---------------|---------|
| `PaymentSuccessful` desde MPController y PayPalController | Solo se dispara desde WebpayController:196. MP y PayPal silenciosamente completan pagos sin evento ni notificación | Compradores y vendedores no reciben confirmación de pago vía MP o PayPal |
| `OrderStatusChanged` | No existe un evento centralizado de cambio de estado de pedido | `ActualizacionEstadoPedidoNotification` se dispara manualmente desde `PedidoRecibidoController@actualizarEstado` sin evento intermedio |
| `SubscriptionRenewed` / `SubscriptionExpired` | La lógica de suscripción en `RenewSubscription` job no dispara eventos | Sin eventos, no hay forma de reaccionar a cambios de suscripción vía listeners |
| `LowStockEvent` | `InventarioService::getProductosBajoStock()` existe pero el chequeo no está programado ni event-driven | Sin alertas automáticas de stock bajo |
| `NewUserRegistered` | No hay evento post-registro (Fortify ya crea el usuario pero no hay evento propio) | Dificultad para extender el flujo de registro con listeners |
| `WebhookReceived` (por gateway) | Los webhooks de MP y PayPal no disparan eventos internos | Dificultad para auditar o reaccionar a webhooks de forma desacoplada |

---

## FASE 3 — MAPA DE PAGOS

### Flujos Completos de Pago

#### 3.1 MercadoPago

```
1. Cliente hace checkout → PedidoController@crear → Pedido creado (pendiente)
2. Cliente selecciona MP → GET /mercadopago/pay/{pedidoId}
3. MercadoPagoController@pay:
   a. getCredentials(ownerId) → PaymentConfig::resolveForOwner()
   b. Verifica auth (cliente_id === auth()->id())
   c. Verifica double-pay (payment_status !== 'completed')
   d. POST /checkout/preferences → MP API
   e. Actualiza Pedido: payment_id, payment_status='created'
   f. Inertia::location(init_point)
4. Cliente paga en MP → MP redirige a success/failure/pending
5. Success callback:
   a. Verifica ?status=approved
   b. Transaction::firstOrCreate(['gateway'=>'mercadopago', 'gateway_transaction_id'=>$paymentId])
   c. syncPedidoToErp() → Cliente + Venta + Tesoreria + Asiento
   d. confirmAppointmentFromPedido()
6. Webhook async (POST /webhooks/mercadopago):
   a. verifyOrigin() → HMAC-SHA256 con mercadopago_webhook_secret
   b. Idempotency: cache key por eventId (24h TTL)
   c. fetchPaymentDetails() from MP API
   d. Transaction::firstOrCreate()
```

#### 3.2 PayPal

```
1. GET /paypal/pay/{pedidoId}
2. PayPalController@pay:
   a. getCredentials() → PaymentConfig::resolveForOwner()
   b. getAccessToken() → OAuth token exchange
   c. POST /v2/checkout/orders (intent: CAPTURE)
   d. Siempre usa USD (currency_code: 'USD') — mismatch si pedido en CLP
   e. Inertia::location(approval_url)
3. Success callback:
   a. POST /v2/checkout/orders/{id}/capture
   b. Solo acepta status=COMPLETED
   c. Transaction::firstOrCreate()
   d. syncPedidoToErp()
4. Webhook (POST /webhooks/paypal):
   a. verifySignature() → PayPal POST /v1/notifications/verify-webhook-signature
   b. Idempotency: cache por eventId (24h TTL)
   c. Transaction::firstOrCreate()
```

#### 3.3 Webpay (Transbank)

```
1. POST /webpay/pay → WebpayController@pay
   a. Genera buyOrder 'ORD-'.time().'-'.rand(100,999)
   b. WebpayService::createTransaction() → API Transbank
   c. Crea WebpayTransaction (status=pending)
   d. Crea PaymentSession (status=pending, expires 2h)
2. Cliente paga en Transbank → redirige a /webpay/return (GET+POST)
3. WebpayController@callback:
   a. DB::transaction()
   b. Amount verification: compara monto original PaymentSession vs Transbank response
   c. Solo acepta status=AUTHORIZED + VCI in ['TSY','TSN']
   d. Transaction::create() — NOT firstOrCreate (DUPLICATE RISK)
   e. Fires PaymentSuccessful event
   f. confirmBookingAppointment()
```

### Gateways Usados

| Gateway | Production Endpoint | Test Mode | SDK Used | Archivo Controlador |
|---------|-------------------|-----------|----------|-------------------|
| MercadoPago | `api.mercadopago.com` | `sandbox_init_point` vs `init_point` | HTTP direct | `MercadoPagoController.php` |
| PayPal | `api-m.paypal.com` | `api-m.sandbox.paypal.com` | HTTP direct | `PayPalController.php` |
| Webpay (Transbank) | Producción (SDK) | Integración (SDK) | `transbank/transbank-sdk` | `Backend/WebpayController.php` |

### Validaciones de Seguridad

| Gateway | HMAC/Firma | Doble Pago | Monto | Idempotencia Webhook |
|---------|-----------|------------|-------|---------------------|
| MercadoPago | HMAC-SHA256 con `mercadopago_webhook_secret` + `hash_equals()` | `Pedido.payment_status === 'completed'` check | ❌ No verifica | Cache por eventId (24h) + firstOrCreate |
| PayPal | Verificación via PayPal API POST /v1/notifications/verify-webhook-signature | Mismo check | ❌ No verifica | Cache por eventId (24h) + firstOrCreate |
| Webpay | Token `token_ws` (sin HMAC adicional) | Mismo check | ✅ Compara PaymentSession.amount vs Transbank response | ❌ Sin cache + `Transaction::create()` (no firstOrCreate) |

### Problemas Identificados en Pagos

| # | Severidad | Problema | Archivo:Linea | Detalle |
|---|-----------|----------|--------------|---------|
| P1 | **CRITICAL** | `Transaction::create()` en Webpay callback en vez de `firstOrCreate` | `WebpayController.php:172` | Callbacks concurrentes pueden duplicar registros. DB unique constraint no está en modelo. |
| P2 | **CRITICAL** | Webhook credentials usa `->first()` en vez de `resolveForOwner()` | `MercadoPagoWebhookController.php:68,143`, `PaypalWebhookController.php:99` | Multi-tenant: el primer PaymentConfig encontrado puede ser de cualquier tenant. Webhook de tenant A puede verificarse con credenciales de tenant B. |
| P3 | **HIGH** | Booking Webpay usa `session()` para estado pendiente, no `PaymentSession` table | `BookingController.php:150-155` | El callback de WebpayController solo lee PaymentSession table. Booking flow pierde estado si el usuario completa el pago. |
| P4 | **HIGH** | Split payment `pedidoId` hardcodeado a 0 en back_urls | `MercadoPagoSplitPayment.php:55`, `PayPalSplitPayment.php:69` | Redirect siempre va a error page. Split payment no funcional. |
| P5 | **HIGH** | `PaymentSuccessful` event no se dispara desde MP ni PayPal | `MercadoPagoController.php`, `PayPalController.php` | Notificaciones de pago sólo funcionan para Webpay. |
| P6 | **MEDIUM** | PayPal usa USD fijo, no CLP | `PayPalController.php:115` | `currency_code: 'USD'` — pedidos en CLP se registran en USD sin conversión. |
| P7 | **MEDIUM** | Split payment `processSplit()` y `releaseFunds()` son stubs | `MercadoPagoSplitPayment.php:82-99`, `PayPalSplitPayment.php:94-111` | Solo log + return array informativo. Sin llamadas API reales. |
| P8 | **MEDIUM** | Sin rate limiting en webhooks | `routes/web.php:321-322` | Endpoints POST /webhooks/* sin throttle. |
| P9 | **LOW** | Commission hybrid type: fixed component hardcoded to 0 | `MercadoPagoSplitPayment.php:129`, `PayPalSplitPayment.php:146` | `+ 0` en fórmula híbrida. |

---

## FASE 4 — MAPA DE MARKETPLACE

### Creación de Productos/Listings

```
Producto model:
  - public_profile_id (FK) → vincula producto al perfil de tienda
  - mostrar_en_perfil (bool) → controla visibilidad en marketplace
  - is_service (bool) → productos tipo servicio con duración
  - tiene_variantes (bool) → SKU variants

Categoria:
  - public_profile_id (FK) → categorías por tienda
  - mostrar_en_perfil (bool) → visibilidad
```

**Flujo de creación:** `Backend/ProductoController` (resource) — utiliza `HasBulkOperations` trait para import/export.

### Checkout

```
1. Cliente navega /tienda/{slug} → MarketplaceController@show
2. Agrega productos al carrito (localStorage en Cliente.tsx)
3. POST checkout → PedidoController@crear:
   a. Crea Pedido con estado='pendiente' y metodo_pago del request
   b. payment_status = metodo_pago === 'local' ? 'local' : null
   c. Crea PedidoItem records
   d. Dispara PedidoCreado event → notification al comprador
4. Cliente elige gateway de pago:
   - MercadoPago: /mercadopago/pay/{pedidoId}
   - PayPal: /paypal/pay/{pedidoId}
   - Webpay (booking): /booking/{slug}/webpay/{pedido}
   - Pago local (efectivo/transferencia): payment_status='local'
```

### Estados de Orden

```
Pedido.estado enum:
  pendiente → confirmado → preparando → enviado → entregado
     ↓
  cancelado

Pedido.payment_status:
  null → created → completed / failed / pending / cancelled / local
```

Transiciones gestionadas por `PedidoRecibidoController@actualizarEstado`.

### Comisiones

```
Config (WebSetting):
  - marketplace_commission_type: percentage | fixed | hybrid
  - marketplace_commission_rate: decimal
  - marketplace_fixed_amount: decimal
  - min_commission / max_commission

Commission model:
  - transaction_id (FK)
  - amount (calculated)
  - status: pending (default)
  - BelongsToBusiness scope
```

**Split Payment:** `SplitPaymentManager` — driver pattern (MP/PayPal). `calculateAndRecordCommission()` crea Commission record. **processSplit() y releaseFunds() son stubs no funcionales.**

### Validaciones

| Aspecto | Implementación | Archivo |
|---------|---------------|---------|
| Auth checkout | `auth()->check()` + `$pedido->cliente_id === auth()->id()` | `PedidoController`, `MPController`, `PPController` |
| Double-pay prevention | `payment_status === 'completed'` guard | `MPController:52`, `PPController:92` |
| Stock validation | No encontrado durante checkout. `InventarioService::reducirStock()` existe pero no se llama desde checkout flow | `app/Services/InventarioService.php` |
| Price consistency | No verifica que precio en carrito coincida con precio actual del producto | — |

---

## FASE 5 — MAPA DE MENSAJERÍA

### 3 Sistemas de Chat Independientes

| Sistema | Modelo Principal | Controlador | Broadcast | Notificaciones |
|---------|-----------------|-------------|-----------|----------------|
| **A: Marketplace Chat** | `Conversation` / `Message` | `ChatController` | ✅ `MessageSent` event → PrivateChannel `conversation.{id}` | `NuevoMensajeChatNotification` (mail+database) |
| **B: Order Chat** | `Conversacion` / `MensajeConversacion` | `ConversacionPedidoController` | ✅ `MensajeEnviado` + `MensajesLeidos` events → PrivateChannel `conversacion.{id}` | `NuevoMensajeChatPedidoNotification` (mail+database) |
| **C: Internal Messages** | `Mensaje` | `MensajeController` | ❌ Sin broadcast | `NuevoMensajeInternoNotification` (mail+database) |

### Almacenamiento de Mensajes

| Modelo | Campos Clave | Fillable Issue | Archivo |
|--------|-------------|----------------|---------|
| `Message` | `id`, `conversation_id`, `sender_id`, `body`, `image_url`, `read_at` | ✅ Correcto | `app/Models/Message.php` |
| `MensajeConversacion` | `id`, `conversacion_id`, `sender_id`, `receiver_id`, `contenido`, `leido`, `file_url` | ✅ Correcto | `app/Models/MensajeConversacion.php` |
| `Mensaje` | `id`, `conversacion_id`, `user_id`, `owner_id`, `contenido`, `leido` | **❌ BUG:** Fillable no incluye sender_id/receiver_id, pero controller pasa estos campos | `app/Models/Mensaje.php` |

### Bug Crítico en Mensajes Internos

`app/Models/Mensaje.php` `$fillable`:
```php
protected $fillable = ['conversacion_id', 'user_id', 'owner_id', 'contenido', 'leido'];
```

`app/Http/Controllers/Backend/MensajeController.php:156`:
```php
$mensaje = Mensaje::create([
    'sender_id' => $userId,       // ❌ NO en $fillable
    'receiver_id' => $usuarioId,  // ❌ NO en $fillable
    'contenido' => $validated['contenido'],
    'conversacion_id' => $request->conversacion_id ?? 0,
]);
```

Mass-assignment exception en runtime al enviar mensaje interno. **Esto rompe el sistema de mensajería interna.**

Además, `Mensaje` model solo define relaciones `user()` y `conversacion()`, pero el controller hace `$mensaje->load(['sender:id,name,profile_photo_path', 'receiver:id,name,profile_photo_path'])` (línea 162) — estas relaciones no existen.

### Delivery/Read Status

| Sistema | Read Receipt | Delivery Status | Implementación |
|---------|-------------|-----------------|----------------|
| Marketplace Chat | ✅ `read_at` timestamp | ❌ No existe | `ChatController:126-129` — update sincrónico on page load |
| Order Chat | ✅ `leido` boolean + broadcast `MensajesLeidos` | ❌ No existe | `ConversacionPedidoController:118-128` — update sincrónico + broadcast |
| Internal Messages | ✅ `leido` boolean | ❌ No existe | `MensajeController:91-94` — update sincrónico |

### Realtime (Broadcasting)

- **Driver:** Laravel Reverb (compatible Pusher)
- **Cliente:** Laravel Echo (Pusher JS)
- **Channels:** 3 private channels definidos en `routes/channels.php`
- **Eventos broadcast:** `MessageSent`, `MensajeEnviado`, `MensajesLeidos` (todos sincrónicos, no ShouldQueue)
- **To Others:** Todos usan `->toOthers()` para evitar eco al emisor

---

## FASE 6 — MAPA DE NOTIFICACIONES

### Sistema de Notificaciones

- **13 Notification classes**, todas implementan `ShouldQueue`
- **Traits comunes:** `HasNotificationPreferences` + `SendsViaMailTemplate`
- **Canales:** `database` y `mail` (ambos condicionales vía preference system); `AutomationFailureAlert` añade `slack` y `log`
- **Mail Templates:** Sistema de plantillas via `MailTemplate` model + `DynamicNotification` mailable con fallback a `toMail()` hardcodeado

### Canales por Notificación

| Notification | database | mail | slack | log | Preference Key | Template Slug |
|-------------|----------|------|-------|-----|----------------|---------------|
| `PaymentReceivedNotification` | ✅ | ✅ | ❌ | ❌ | `pago_recibido` | `pago_recibido` |
| `NuevoMensajeChatNotification` | ✅ | ✅ | ❌ | ❌ | `mensaje_chat` | `mensaje_chat` |
| `NuevoMensajeChatPedidoNotification` | ✅ | ✅ | ❌ | ❌ | `mensaje_chat` | `mensaje_chat` |
| `NuevoMensajeInternoNotification` | ✅ | ✅ | ❌ | ❌ | `mensaje_chat` | `mensaje_chat` |
| `NuevoPedidoNotification` | ✅ | ✅ | ❌ | ❌ | `nuevo_pedido` | `nuevo_pedido` |
| `PedidoCreadoCompradorNotification` | ✅ | ✅ | ❌ | ❌ | `pedido_creado` | `pedido_creado` |
| `ActualizacionEstadoPedidoNotification` | ✅ | ✅ | ❌ | ❌ | `actualizacion_pedido` | `actualizacion_pedido` |
| `NuevoTicketNotification` | ✅ | ✅ | ❌ | ❌ | `nuevo_ticket` | `nuevo_ticket` |
| `NuevaReaccion` | ✅ | ❌ | ❌ | ❌ | `reaccion` | — |
| `NuevoComentario` | ✅ | ❌ | ❌ | ❌ | `comentario` | — |
| `TempPasswordNotification` | ❌ | ✅ | ❌ | ❌ | `temp_password` | `temp_password` |
| `WelcomeProveedorNotification` | ❌ | ✅ | ❌ | ❌ | `welcome_proveedor` | `welcome_proveedor` |
| `AutomationFailureAlert` | ✅ | ✅ condicional | ✅ condicional | ✅ | — | — |

### Triggers de Notificaciones

| Trigger | Notification | Channel | File:Line |
|---------|-------------|---------|-----------|
| Pago Webpay exitoso | `PaymentReceivedNotification` | database, mail | `WebpayController:196` → Listener |
| Nuevo pedido marketplace | `NuevoPedidoNotification` (vendedor) + `PedidoCreadoCompradorNotification` (comprador) | database, mail | `PedidoController:96` → Evento |
| Nuevo mensaje marketplace chat | `NuevoMensajeChatNotification` | database, mail | `ChatController:178` |
| Nuevo mensaje order chat | `NuevoMensajeChatPedidoNotification` | database, mail | `ConversacionPedidoController:173` |
| Nuevo mensaje interno | `NuevoMensajeInternoNotification` | database, mail | `MensajeController:164` |
| Cambio estado pedido | `ActualizacionEstadoPedidoNotification` | database, mail | `PedidoRecibidoController` |
| Nuevo ticket | `NuevoTicketNotification` | database, mail | `TicketController` (assumed) |
| Reacción en publicación | `NuevaReaccion` | database | `PublicacionController:react()` |
| Comentario en publicación | `NuevoComentario` | database | `PublicacionController:comment()` |
| Fallo automación consecutivo | `AutomationFailureAlert` | log, slack, mail | `HandleAutomationFailure` action |

### Consistencia

| Aspecto | Estado | Evidencia |
|---------|--------|-----------|
| Todas las notificaciones son ShouldQueue | ✅ Consistente | Todas usan `Queueable` trait |
| Preference System aplicado uniformemente | ✅ Consistente | Todas usan `filterChannelsByPreference()` |
| MailTemplate integrado | ✅ Consistente | Todas (excepto reacción/comentario/automation) usan `sendViaTemplate()` |
| PaymentSuccessful no disparado desde MP/PayPal | ❌ **Inconsistente** | Solo Webpay tiene listener |
| 3 tipos de mensaje comparten mismo preference key `mensaje_chat` | ⚠️ **Posible confusión** | Usuario no puede silenciar solo chat interno vs marketplace |

---

## FASE 7 — HALLAZGOS CRÍTICOS

| # | Hallazgo | Archivo | Línea | Impacto | Evidencia |
|---|----------|---------|-------|---------|-----------|
| C1 | **Multi-tenant webhook credentials: `->first()` sin scope de tenant** | `MercadoPagoWebhookController.php` | 68, 143 | Si múltiples tenants tienen MP, el webhook puede verificar firma y obtener payment details con credenciales del tenant incorrecto | `PaymentConfig::whereNotNull('mercadopago_access_token')->first()` |
| C2 | **Multi-tenant PayPal webhook credentials: mismo patrón** | `PaypalWebhookController.php` | 99 | Idem C1 para PayPal | `PaymentConfig::whereNotNull('paypal_client_id')->first()` |
| C3 | **`Transaction::create()` en Webpay sin `firstOrCreate`** | `WebpayController.php` | 172 | Duplicación de registros Transaction en callbacks concurrentes | `Transaction::create([...])` — líneas 172-192 |
| C4 | **N8n internal API: token compartido expone todos los tenants** | `routes/api.php`, `VerifyN8nToken.php` | — | Cualquier workflow n8n con el token global puede leer datos de cualquier negocio | Rutas `/internal/business/{business}/*` protegidas por `verify-n8n-token` que usa `config('services.n8n.token')` único |
| C5 | **`Mensaje::create()` pasa campos no fillable → mass-assignment exception** | `MensajeController.php:156`, `Mensaje.php` (fillable) | 156 | El envío de mensajes internos lanza excepción en runtime. Sistema de mensajería interna roto. | `sender_id` y `receiver_id` pasados a `Mensaje::create()` pero no en `$fillable` |
| C6 | **`MensajeController` carga relaciones inexistentes** | `MensajeController.php:162` | 162 | `$mensaje->load(['sender', 'receiver'])` — el modelo Mensaje solo define `user()` y `conversacion()`, no `sender()` ni `receiver()` | Error 500 al cargar página de conversación |

---

## FASE 8 — HALLAZGOS ALTOS

| # | Hallazgo | Archivo | Línea | Impacto | Evidencia |
|---|----------|---------|-------|---------|-----------|
| H1 | **`PaymentSuccessful` event no disparado desde MP ni PayPal** | `MercadoPagoController.php`, `PayPalController.php` | — | Notificaciones de pago y side effects dependientes del evento no funcionan para MP/PayPal | Solo `WebpayController:196` dispara el evento |
| H2 | **Split payment `pedidoId` hardcodeado = 0 en back_urls** | `MercadoPagoSplitPayment.php:55`, `PayPalSplitPayment.php:69` | — | Redirect siempre a error page. Split payment no funcional | `'pedidoId' => 0` hardcodeado |
| H3 | **Split payment `processSplit()` y `releaseFunds()` son stubs vacíos** | `MercadoPagoSplitPayment.php:82-99`, `PayPalSplitPayment.php:94-111` | — | Liberación de fondos y procesamiento de splits no implementados. Solo log + return array | Métodos solo contienen `Log::info()` y return |
| H4 | **Booking Webpay usa `session()` para estado, no `PaymentSession` table** | `BookingController.php:150-155` | — | Callback de WebpayController solo lee PaymentSession. Booking flow pierde estado. | `session(['webpay_pending' => ...])` vs `PaymentSession::create()` |
| H5 | **Webpay sin idempotencia en webhook** | `WebpayController.php`, `routes/web.php` | — | No hay webhook para Webpay, pero el callback sincrónico tampoco tiene cache de idempotencia | Sin cache key por eventId, sin firstOrCreate |
| H6 | **PayPal usa `currency_code: 'USD'` fijo** | `PayPalController.php` | 115 | Pedidos en CLP se registran como USD sin conversión. Diferencia contable. | `'currency_code' => 'USD'` sin lógica de conversión |
| H7 | **`VerifyTenantToken` usa `->where()` en vez de `hash_equals()`** | `VerifyTenantToken.php` | — | Timing attack vulnerable en comparación de API tokens | `User::where('api_token', $token)->first()` |
| H8 | **Sin rate limiting en webhooks** | `routes/web.php:321-322` | — | Webhooks POST sin throttle. Potencial DoS. | Rutas no tienen middleware `throttle` |
| H9 | **Sin rate limiting en `/api/tenant/*`** | `routes/api.php` | — | Endpoint `api/tenant/resumen-completo` sin throttle | Ausencia de `throttle` middleware |

---

## FASE 9 — HALLAZGOS MEDIOS

| # | Hallazgo | Archivo | Impacto |
|---|----------|---------|---------|
| M1 | **21 modelos sin tenant scoping** — `User`, `Conversacion`, `Conversation`, `Message`, `MensajeConversacion`, `Role`, `CuponUso`, `StoreReview`, `StoreReaction`, `Follower`, `Reaction`, `PageView`, `UserDashboardWidget`, `UserNotificationPreference`, `WebhookLog`, `CourseDiscussion` | Varios | Consultas directas pueden filtrar datos entre tenants. Parcialmente mitigado por autorización manual. |
| M2 | **`Role` model sin global scope — visible entre tenants** | `app/Models/Role.php:20-22` | Roles personalizados visibles a través de todos los tenants si no se usa local scope |
| M3 | **`CheckOwnership` middleware silencioso si route param no coincide** | `CheckOwnership.php` | Si el nombre del recurso no coincide exactamente con el route parameter, el middleware pasa sin abortar |
| M4 | **Usuario desactivado (`is_active=false`) puede acceder API routes** | `CheckActive.php`, `routes/api.php` | `CheckActive` solo está en middleware group `web`, no en `api` |
| M5 | **API token en query string puede leakear en logs** | `VerifyTenantToken.php` | Soporta `?api_token=` en URL — visible en server logs, referrers, browser history |
| M6 | **3 sistemas de chat con implementaciones paralelas** — cada uno con su modelo, controlador, evento, notificación separados | Múltiples | Duplicación de lógica. Falta abstracción/unificación. |
| M7 | **Solo Order Chat tiene broadcast de read receipts** | `ConversacionPedidoController:128` | Marketplace chat e Internal messages no emiten broadcast cuando se leen mensajes |
| M8 | **Commission hybrid type: fixed component hardcodeado a 0** | `MercadoPagoSplitPayment.php:129`, `PayPalSplitPayment.php:146` | Cálculo incompleto de comisión híbrida |
| M9 | **`MailConfigErrorOccurred` event sin listeners** | `app/Events/MailConfigErrorOccurred.php` | Evento creado pero nunca escuchado |
| M10 | **Sin validación de stock en checkout marketplace** | `PedidoController.php` | No se verifica stock disponible al confirmar pedido |

---

## FASE 10 — HALLAZGOS BAJOS

| # | Hallazgo | Archivo |
|---|----------|---------|
| L1 | **3 notificaciones comparten mismo preference key `mensaje_chat`** | `NuevoMensajeChatNotification.php`, `NuevoMensajeChatPedidoNotification.php`, `NuevoMensajeInternoNotification.php` |
| L2 | **Broadcast events no están encolados (no ShouldQueue ni ShouldBroadcastNow)** | `MessageSent.php`, `MensajeEnviado.php`, `MensajesLeidos.php` |
| L3 | **CORS expone `internal/*` path** | `config/cors.php` |
| L4 | **`User::getOwnerId()` fallback a raw `creator_id` si creator user deleted** | `User.php` (getOwnerId method) |
| L5 | **Sin `hash_equals()` en webhook token comparison (PayPal usa API call, MP usa `hash_equals()` — OK)** | — |
| L6 | **400+ líneas en `routes/web.php`** — mantenibilidad | `routes/web.php` (467 lines) |
| L7 | **`RenewSubscription` job no dispara eventos** | `app/Jobs/RenewSubscription.php` |
| L8 | **Sin chequeo programado de stock bajo** | `app/Services/InventarioService.php` — método existe, nadie lo llama periódicamente |
| L9 | **`WebpayController` tiene callback @deprecated sin reemplazo documentado** | `Backend/WebpayConfigController.php:62` |

---

## FASE 11 — PLAN DE REFACTORIZACIÓN

### Crítico Inmediato (Semana 1)

| Orden | Qué cambiar | Dónde | Por qué | Impacto esperado |
|-------|------------|-------|---------|-----------------|
| 1 | Fix `Mensaje::$fillable` agregar `sender_id`, `receiver_id` + crear relaciones `sender()`, `receiver()` | `app/Models/Mensaje.php` | Mass-assignment exception rompe mensajería interna | Mensajería interna vuelve a funcionar |
| 2 | Fix webhook credentials: reemplazar `->first()` por `resolveForOwner()` usando `$pedido->owner_id` | `MercadoPagoWebhookController.php:68,143`, `PaypalWebhookController.php:99` | Multi-tenant: webhook de tenant A puede usar credenciales de tenant B | Aislamiento correcto de tenants en webhooks |
| 3 | Fix `Transaction::create()` → `Transaction::firstOrCreate()` con composite unique key | `WebpayController.php:172` | Duplicación de transacciones en callbacks concurrentes | Idempotencia en todas las transacciones |
| 4 | Disparar `PaymentSuccessful` event desde MP y PayPal success handlers | `MercadoPagoController.php:success()`, `PayPalController.php:success()` | Notificaciones de pago no funcionan para MP/PayPal | Notificaciones consistentes entre todos los gateways |
| 5 | Fix split payment `pedidoId=0` hardcodeado | `MercadoPagoSplitPayment.php:55`, `PayPalSplitPayment.php:69` | Split payment redirect siempre a error | Split payment funcional |

### Alto Impacto (Semanas 2-3)

| Orden | Qué cambiar | Dónde | Por qué | Impacto esperado |
|-------|------------|-------|---------|-----------------|
| 6 | Implementar `processSplit()` y `releaseFunds()` reales para MP y PayPal | `MercadoPagoSplitPayment.php`, `PayPalSplitPayment.php` | Split payment releases no funcionales | Liberación de fondos a vendedores |
| 7 | Agregar rate limiting a webhooks + `/api/tenant/*` | `routes/web.php`, `routes/api.php` | Protección contra DoS en endpoints críticos | Seguridad mejorada en webhooks y API |
| 8 | `VerifyTenantToken`: usar `hash_equals()` + restringir a Bearer header | `VerifyTenantToken.php` | Timing attack + token leak en query string | Auth más segura para tenant API |
| 9 | Agregar `hash_equals()` + rate limiting + per-business API keys para n8n | `VerifyN8nApiKey.php`, `VerifyN8nToken.php` | Token compartido expone todos los tenants | Aislamiento de datos entre tenants para n8n |
| 10 | Unificar Booking Webpay: migrar de `session()` a `PaymentSession` table | `BookingController.php` | Estado inconsistente en booking flow | Booking payments funcionales correctamente |

### Medio Plazo (Semanas 4-6)

| Orden | Qué cambiar | Dónde | Por qué |
|-------|------------|-------|---------|
| 11 | Agregar `PaymentSuccessful` event dispatching y listener para notificar cambios de estado | MP + PayPal success handlers | Desacoplar lógica de post-pago |
| 12 | Agregar `OrderStatusChanged`, `SubscriptionRenewed`, `LowStock` events | Varios | Event-driven architecture completa |
| 13 | Unificar los 3 sistemas de mensajería en un ChatService con driver pattern | `app/Services/ChatService.php` (nuevo) | Eliminar duplicación de lógica |
| 14 | `CheckActive` middleware en API routes o verificación de `is_active` en auth middleware | `routes/api.php`, Sanctum config | Usuarios desactivados no deben acceder API |
| 15 | Agregar `owner_id` + `BelongsToOwner` a `Conversacion`, `Conversation`, `Message` | Modelos de chat | Tenant scoping para chats |
| 16 | `CheckOwnership`: validar que route param exista antes de proceder | `CheckOwnership.php` | Robustez del middleware |

### Bajo Impacto (Semanas 7-8)

| Orden | Qué cambiar | Dónde | Por qué |
|-------|------------|-------|---------|
| 17 | Implementar chequeo programado de stock bajo (nuevo comando + evento `LowStock`) | `app/Console/Commands/` | Alertas automáticas de inventario |
| 18 | Disparar eventos desde `RenewSubscription` job | `app/Jobs/RenewSubscription.php` | Listeners de suscripción |
| 19 | Refactor `routes/web.php` en módulos más pequeños | `routes/web.php` | Mantenibilidad |
| 20 | Agregar broadcast de read receipts a Marketplace chat e Internal messages | `ChatController.php`, `MensajeController.php` | Consistencia de UX entre chats |
| 21 | Separar preference keys para cada tipo de mensaje | 3 Notification classes | Granularidad de preferencias |

---

## FASE 12 — ROADMAP (30 / 60 / 90 DÍAS)

### DÍAS 1-30: Fixes Críticos y Seguridad

```
Semana 1 (Crítico):
  ┌─────────────────────────────────────────────────────────────┐
  │  D1-D2: Fix Mensaje $fillable + relaciones (C5, C6)        │
  │  D3-D4: Fix webhook credentials multi-tenant (C1, C2)      │
  │  D5-D6: Fix Transaction::firstOrCreate (C3)                │
  │  D7:   Disparar PaymentSuccessful desde MP/PayPal (H1)     │
  └─────────────────────────────────────────────────────────────┘

Semana 2 (Alto):
  ┌─────────────────────────────────────────────────────────────┐
  │  D8-D9:  Fix split payment pedidoId (H2)                   │
  │  D10:    Rate limiting webhooks + tenant API (H8, H9)      │
  │  D11:    VerifyTenantToken hash_equals + Bearer only (H7)  │
  │  D12:    CheckActive en API routes (M4)                    │
  └─────────────────────────────────────────────────────────────┘

Semana 3-4 (Alto):
  ┌─────────────────────────────────────────────────────────────┐
  │  D13-D15: Implement processSplit/releaseFunds reales (H3)  │
  │  D16-D18: Per-business n8n API keys (C4)                   │
  │  D19-D20: Fix Booking Webpay session vs PaymentSession (H4)│
  └─────────────────────────────────────────────────────────────┘
```

### DÍAS 31-60: Arquitectura y Eventos

```
Semana 5-6 (Medio):
  ┌─────────────────────────────────────────────────────────────┐
  │  D21-D22: PaymentSuccessful dispatching completo           │
  │  D23-D25: OrderStatusChanged + SubscriptionRenewed events  │
  │  D26-D27: Unificar chat (ChatService) — diseño             │
  └─────────────────────────────────────────────────────────────┘

Semana 7-8 (Medio):
  ┌─────────────────────────────────────────────────────────────┐
  │  D28-D30: Tenant scoping para modelos de chat (M1, M2)    │
  │  D31-D33: CheckOwnership route param validation (M3)       │
  │  D34-D35: LowStock event + comando programado              │
  └─────────────────────────────────────────────────────────────┘
```

### DÍAS 61-90: Optimización y Escalabilidad

```
Semana 9-10 (Bajo):
  ┌─────────────────────────────────────────────────────────────┐
  │  D36-D38: Broadcast read receipts para todos los chats      │
  │  D39-D41: Refactor routes/web.php → módulos                │
  │  D42-D43: Preference keys separadas por tipo de mensaje    │
  └─────────────────────────────────────────────────────────────┘

Semana 11-12 (Bajo):
  ┌─────────────────────────────────────────────────────────────┐
  │  D44-D46: MailConfigErrorOccurred → listener real           │
  │  D47-D48: Commission hybrid type fix                       │
  │  D49-D50: Stock validation en checkout                     │
  │  D51-D52: Encolar broadcast events                         │
  └─────────────────────────────────────────────────────────────┘
```

---

## RESUMEN EJECUTIVO

| Categoría | Found | Fixed in previous sessions | Open |
|-----------|-------|---------------------------|------|
| **CRITICAL** | 6 | 0 | **6** |
| **HIGH** | 9 | 3 (tailLog, Google OAuth false positive, external API errors) | **9** |
| **MEDIUM** | 10 | 2 (validation hero/planes/cta, FinancialEvent enum) | **10** |
| **LOW** | 9 | 2 (error-boundary, webhook null safety) | **9** |

**Total open findings: 34** (6 critical, 9 high, 10 medium, 9 low)

### Métricas Clave

| Métrica | Valor |
|---------|-------|
| Total PHP files | ~320 |
| Total Controllers | 80+ backend + 10+ frontend/public |
| Total Models | 115+ (130 DB tables) |
| Total Services | 23 |
| Total Middleware | 13 |
| Total Events | 6 |
| Total Jobs | 4 |
| Total Notifications | 13 (all ShouldQueue) |
| Total React Pages | 175+ |
| Total React Components | 88+ |
| Tests (Financial + Config) | 73 passing, 387 assertions |
| Payment Gateways | 3 (MercadoPago, PayPal, Webpay) |
| Chat Systems | 3 (parallel, un-unified) |
| Tenant Scope Coverage | 97/115 models (84%) |
