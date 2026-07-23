# Sitemap — aldiaproyect

> **Total: ~390 rutas** en 87 módulos/prefix.
> Generado: June 2026
> Framework: Laravel 12 + Inertia + React

---

## 1. Rutas Públicas

| Método | URI | Nombre | Controlador |
|--------|-----|--------|-------------|
| GET | `/` | `home` | WelcomeController@index |
| GET | `/quienes-somos` | `quienes-somos` | WelcomeController@quienesSomos |
| GET | `/feedback` | `feedback` | WelcomeController@feedback |
| GET | `/fundacion` | `fundacion` | WelcomeController@fundacion |
| GET | `/status` | `status` | StatusPageController@index |
| GET | `/status/embed` | `status.embed` | StatusPageController@embed |
| GET | `/tienda` | `marketplace.index` | MarketplaceController@index |
| GET | `/tienda/{slug}` | `marketplace.show` | MarketplaceController@show |
| GET | `/tienda/{slug}/categoria/{categoria}` | `marketplace.category` | MarketplaceController@category |
| GET | `/booking/{slug}` | `booking.show` | BookingController@show |
| POST | `/booking/{slug}` | `booking.store` | BookingController@store |
| GET | `/rifa/{slug}` | `raffles.public.show` | RafflePublicController@show |
| POST | `/rifa/{slug}/participate` | `raffles.public.participate` | RafflePublicController@participate |
| POST | `/rifa/{slug}/buy-numbers` | `raffles.public.buy-numbers` | RafflePublicController@buyNumbers |
| GET | `/rifa/{slug}/ganadores` | `raffles.public.winners` | RafflePublicController@winners |
| GET | `/auth/{provider}/redirect` | `socialite.redirect` | SocialiteController@redirect |
| GET | `/auth/{provider}/callback` | `socialite.callback` | SocialiteController@callback |

---

## 2. Dashboard

Middleware: `auth`, `permission:ver dashboard`

| Método | URI | Nombre | Controlador |
|--------|-----|--------|-------------|
| GET | `/dashboard` | `dashboard` | DashboardController@index |
| POST | `/dashboard/config` | `dashboard.config` | DashboardController@saveConfig |

---

## 3. CRM — Clientes, Prospectos, Oportunidades

Middleware: `auth`, permisos `comercial.*`

| Método | URI | Nombre | Controlador |
|--------|-----|--------|-------------|
| GET/POST | `/clientes` | `clientes.index/.store` | ClienteController |
| GET | `/clientes/create` | `clientes.create` | ClienteController |
| GET | `/clientes/export` | `clientes.export` | ClienteController@exportCsv |
| GET | `/clientes/export-excel` | `clientes.exportExcel` | ClienteController@exportExcel |
| POST | `/clientes/import` | `clientes.import` | ClienteController@importCsv |
| POST | `/clientes/import-excel` | `clientes.importExcel` | ClienteController@importExcel |
| GET/PUT/DELETE | `/clientes/{cliente}` | `clientes.show/.update/.destroy` | ClienteController |
| GET | `/clientes/{cliente}/edit` | `clientes.edit` | ClienteController |
| GET/POST | `/prospectos` | `prospectos.index/.store` | ProspectoController |
| GET | `/prospectos/create` | `prospectos.create` | ProspectoController |
| GET/PUT/DELETE | `/prospectos/{prospecto}` | `prospectos.show/.update/.destroy` | ProspectoController |
| PATCH | `/prospectos/{prospecto}/estado` | `prospectos.updateEstado` | ProspectoController |
| GET/POST | `/oportunidades` | `oportunidades.index/.store` | OportunidadController |
| GET/PUT/DELETE | `/oportunidades/{oportunidade}` | `oportunidades.show/.update/.destroy` | OportunidadController |
| PATCH | `/oportunidades/{oportunidade}/etapa` | `oportunidades.etapa` | OportunidadController@cambiarEtapa |
| GET/POST | `/cotizaciones` | `cotizaciones.index/.store` | CotizacionController |
| GET | `/cotizaciones/{cotizacion}/pdf` | `cotizaciones.pdf` | CotizacionController@downloadPdf |
| GET | `/cotizaciones/{cotizacion}/preview` | `cotizaciones.preview` | CotizacionController@previewPdf |
| GET/POST | `/campanas` | `campanas.index/.store` | CampanaController |
| GET/POST | `/call-center` | `call-center.index` | CallCenterController@index |
| POST | `/call-center/llamadas` | `call-center.llamadas.store` | CallCenterController |
| POST | `/call-center/gestiones` | `call-center.gestiones.store` | CallCenterController |

---

## 4. Ventas — Facturas, Puntos de Venta

Middleware: `auth`, permisos `ventas.*`

| Método | URI | Nombre | Controlador |
|--------|-----|--------|-------------|
| GET/POST | `/ventas` | `ventas.index/.store` | VentaController |
| GET | `/ventas/{venta}/download` | `ventas.download` | VentaController@downloadPdf |
| GET | `/ventas/{venta}/download-informal` | `ventas.download-informal` | VentaController@downloadPdfInformal |
| PATCH | `/ventas/{venta}/status` | `ventas.status` | VentaController@updateStatus |
| GET/POST | `/facturacion` | `facturacion.index/.store` | FacturacionController |
| GET | `/facturacion/{factura}/pdf` | `facturacion.pdf` | FacturacionController@downloadPdf |
| GET/POST | `/compras` | `compras.index/.store` | CompraController |
| GET | `/compras/{compra}/pdf` | `compras.pdf` | CompraController@downloadPdf |
| GET/POST | `/pos` | `pos.index/.store` | PosController |
| POST | `/pos/{venta}/emitir-dte` | `pos.emitir-dte` | PosController@emitirDte |
| GET/POST | `/pagos` | `pagos.index/.store` | PagoController |
| GET/POST | `/cobranzas` | `cobranzas.index/.store` | CobranzaController |
| GET | `/pedidos-recibidos` | `pedidos-recibidos.index` | PedidoRecibidoController |
| PUT | `/pedidos-recibidos/{pedido}/estado` | `pedidos-recibidos.estado` | PedidoRecibidoController |
| POST | `/pedidos-recibidos/{pedido}/venta` | `pedidos-recibidos.generar-venta` | PedidoRecibidoController |
| GET | `/mis-pedidos` | `pedidos.mios` | PedidoController |
| GET | `/pedidos/{pedido}` | `pedidos.ver` | PedidoController |
| GET | `/pedidos/{pedido}/estado` | `pedidos.estado` | PedidoController |

---

## 5. Marketplace / Tienda

Middleware: `auth` en rutas protegidas

| Método | URI | Nombre | Controlador |
|--------|-----|--------|-------------|
| GET | `/tienda/{slug}` | `marketplace.show` | MarketplaceController@show |
| POST | `/tienda/{slug}/react` | `marketplace.react` | MarketplaceController@react |
| POST | `/tienda/{slug}/checkout` | `tienda.checkout` | PedidoController@crear |
| GET | `/tienda/{slug}/confirmacion/{pedidoId}` | `tienda.confirmacion` | PedidoController@confirmacion |
| GET | `/tienda/{slug}/chat` | `chat.start` | ChatController@start |
| GET | `/chat` | `chat.index` | ChatController@index |
| GET | `/chat/{conversation}` | `chat.show` | ChatController@show |
| POST | `/chat/{conversation}/messages` | `chat.send` | ChatController@sendMessage |

---

## 6. Pagos — Webpay, PayPal, MercadoPago

Middleware: `auth`

| Método | URI | Nombre | Controlador |
|--------|-----|--------|-------------|
| GET | `/webpay/config` | `webpay.config` | WebpayConfigController@index |
| POST | `/webpay/config` | `webpay.config.update` | WebpayConfigController@update |
| POST | `/webpay/pay` | `webpay.pay` | WebpayController@pay |
| GET/POST | `/webpay/return` | `webpay.callback` | WebpayController@callback |
| GET | `/webpay/movimientos` | `webpay.movimientos` | WebpayTransactionController@index |
| GET | `/paypal/config` | `paypal.config` | PayPalConfigController@index |
| POST | `/paypal/config` | `paypal.config.update` | PayPalConfigController@update |
| POST | `/paypal/test` | `paypal.test` | PayPalConfigController@testConnection |
| GET | `/paypal/pay/{pedidoId}` | `paypal.pay` | PayPalController@pay |
| GET | `/paypal/success/{pedidoId}` | `paypal.success` | PayPalController@success |
| GET | `/paypal/cancel/{pedidoId}` | `paypal.cancel` | PayPalController@cancel |
| GET | `/mercadopago/config` | `mercadopago.config` | MercadoPagoConfigController@index |
| POST | `/mercadopago/config` | `mercadopago.config.update` | MercadoPagoConfigController@update |
| POST | `/mercadopago/test` | `mercadopago.test` | MercadoPagoConfigController@testConnection |
| GET | `/mercadopago/pay/{pedidoId}` | `mercadopago.pay` | MercadoPagoController@pay |
| GET | `/mercadopago/success/{pedidoId}` | `mercadopago.success` | MercadoPagoController@success |
| GET | `/mercadopago/failure/{pedidoId}` | `mercadopago.failure` | MercadoPagoController@failure |
| GET | `/mercadopago/pending/{pedidoId}` | `mercadopago.pending` | MercadoPagoController@pending |

---

## 7. Productos / Inventario

Middleware: `auth`, permisos `comercial.productos.*` / `inventario.*`

| Método | URI | Nombre | Controlador |
|--------|-----|--------|-------------|
| GET/POST | `/productos` | `productos.index/.store` | ProductoController |
| GET/PUT/DELETE | `/productos/{producto}` | `productos.show/.update/.destroy` | ProductoController |
| GET/POST | `/categorias` | `categorias.index/.store` | CategoriaController |
| GET/POST | `/inventarios` | `inventarios.index/.store` | InventarioController |
| GET/POST | `/almacenes` | `almacenes.index/.store` | AlmacenController |
| GET/POST | `/lotes` | `lotes.index/.store` | LoteController |
| GET/POST | `/movimientos` | `movimientos.index/.store` | MovimientoController |
| GET/POST | `/boms` | `boms.index/.store` | BomController |
| GET | `/inventario-cierre` | `inventario-cierre.index` | InventarioCierreController |
| PATCH | `/inventario-cierre/{inventarioCierre}/confirmar` | `inventario-cierre.confirmar` | InventarioCierreController |
| GET/POST | `/vacios` | `vacios.index/.store` | VacioController |
| PATCH | `/vacios/{vacio}/retornar` | `vacios.retornar` | VacioController@retornar |
| GET/POST | `/lista-pendientes` | `lista-pendientes.index/.store` | ListaPendienteController |

---

## 8. RRHH — Empleados, Asistencia, Evaluaciones

Middleware: `auth`, permisos `rrhh.*`

| Método | URI | Nombre | Controlador |
|--------|-----|--------|-------------|
| GET/POST | `/empleados` | `empleados.index/.store` | EmpleadoController |
| GET/PUT/DELETE | `/empleados/{empleado}` | `empleados.show/.update/.destroy` | EmpleadoController |
| GET/POST | `/asistencia` | `asistencia.index/.store` | AsistenciaController |
| GET/POST | `/evaluaciones` | `evaluaciones.index/.store` | EvaluacionController |
| GET/POST | `/nominas` | `nominas.index/.store` | NominaController |
| GET/POST | `/reclutamiento` | `reclutamiento.index/.store` | ReclutamientoController |
| GET/POST | `/timesheets` | `timesheets.index/.store` | TimesheetController |

---

## 9. Finanzas — Contabilidad, Tesorería, SII

Middleware: `auth`, permisos `finanzas.*`

| Método | URI | Nombre | Controlador |
|--------|-----|--------|-------------|
| GET/POST | `/contabilidad` | `contabilidad.index/.store` | ContabilidadController |
| GET/PUT/DELETE | `/contabilidad/{asiento}` | `contabilidad.show/.update/.destroy` | ContabilidadController |
| GET/POST | `/tesoreria` | `tesoreria.index/.store` | TesoreriaController |
| GET/POST | `/impuestos` | `impuestos.index/.store` | ImpuestoController |
| GET | `/sii` | `sii.index` | SiiController@index |
| POST | `/sii/caf` | `sii.caf.store` | SiiController@storeCaf |
| GET | `/sii/configuracion` | `sii.config` | SiiController@configuracion |
| GET/POST | `/sii/configuracion/ambiente` | `sii.config.ambiente` | SiiController |
| GET/POST | `/sii/configuracion/certificado` | `sii.config.certificado` | SiiController |
| GET/POST | `/sii/configuracion/emisor` | `sii.config.emisor` | SiiController |
| GET | `/sii/configuracion/folios` | `sii.config.folios` | SiiController |
| GET | `/sii/documentos` | `sii.documentos` | SiiController@documentos |
| POST | `/sii/token/refrescar` | `sii.token.refrescar` | SiiController@refrescarToken |
| GET/POST | `/cargas-diarias` | `cargas-diarias.index/.store` | CargaDiariaController |
| POST | `/cargas-diarias/{cargaDiaria}/renovar` | `cargas-diarias.renovar` | CargaDiariaController |

---

## 10. Producción / MRP

Middleware: `auth`, permisos `produccion.*`

| Método | URI | Nombre | Controlador |
|--------|-----|--------|-------------|
| GET/POST | `/ordenes-produccion` | `ordenes-produccion.index/.store` | OrdenProduccionController |
| GET/POST | `/planificacion` | `planificacion.index/.store` | PlanificacionController |
| GET/POST | `/calidad` | `calidad.index/.store` | ControlCalidadController |
| GET/POST | `/boms` | `boms.index/.store` | BomController |

---

## 11. Proyectos

Middleware: `auth`, permisos `proyectos.*`

| Método | URI | Nombre | Controlador |
|--------|-----|--------|-------------|
| GET/POST | `/proyectos` | `proyectos.index/.store` | ProyectoController |
| GET/PUT/DELETE | `/proyectos/{proyecto}` | `proyectos.show/.update/.destroy` | ProyectoController |
| GET/POST | `/gastos-proyecto` | `gastos-proyecto.index/.store` | GastoProyectoController |
| GET/POST | `/grupos-trabajo` | `grupos-trabajo.index/.store` | GrupoTrabajoController |
| GET/POST | `/tareas` | `tareas.index/.store` | TareaController |
| PUT | `/tareas/{tarea}` | `tareas.update` | TareaController@update |
| DELETE | `/tareas/{tarea}` | `tareas.destroy` | TareaController@destroy |
| GET/POST | `/hitos` | `hitos.index/.store` | HitoController |
| POST | `/entregas` | `entregas.index/.store` | EntregaController |

---

## 12. Flota — Vehículos y Conductores

Middleware: `auth`, permisos `flota.*`

| Método | URI | Nombre | Controlador |
|--------|-----|--------|-------------|
| GET/POST | `/vehiculos` | `vehiculos.index/.store` | VehiculoController |
| PATCH | `/vehiculos/{vehiculo}/tracking` | `vehiculos.tracking` | VehiculoController@actualizarTracking |
| POST | `/vehiculos/{vehiculo}/simular` | `vehiculos.simular` | VehiculoController@simularTracking |
| POST | `/vehiculos/{vehiculo}/limpiar` | `vehiculos.limpiar` | VehiculoController@limpiarTracking |
| GET/POST | `/conductores` | `conductores.index/.store` | ConductorController |
| POST | `/conductores/{conductor}/simular` | `conductores.simular` | ConductorController@simularTracking |
| POST | `/conductores/{conductor}/limpiar` | `conductores.limpiar` | ConductorController@limpiarTracking |
| POST | `/api/v1/tracking/update` | — | TrackingController@updateLocation |

---

## 13. Uptime Monitoring

Middleware: `auth`, permisos `uptime.*`

| Método | URI | Nombre | Controlador |
|--------|-----|--------|-------------|
| GET/POST | `/uptime` | `uptime.index/.store` | UptimeController |
| GET/PUT/DELETE | `/uptime/{site}` | `uptime.show/.update/.destroy` | UptimeController |
| POST | `/uptime/{site}/check` | `uptime.check` | UptimeController@checkNow |
| GET | `/uptime/{site}/incidents` | `uptime.incidents` | UptimeController@incidents |
| GET/POST | `/uptime/alerts` | `alerts.index/.store` | UptimeAlertController |
| PUT/DELETE | `/uptime/alerts/{alert}` | `alerts.update/.destroy` | UptimeAlertController |

---

## 14. Comunicación — Mensajes, Comunidad, Notificaciones

Middleware: `auth`, permisos `general.comunidad.*`

| Método | URI | Nombre | Controlador |
|--------|-----|--------|-------------|
| GET/POST | `/mensajes` | `mensajes.index/.enviar` | MensajeController |
| GET | `/mensajes/usuarios` | `mensajes.usuarios` | MensajeController |
| GET | `/mensajes/{usuarioId}` | `mensajes.conversacion` | MensajeController |
| GET | `/comunidad` | `comunidad.index` | ComunidadController@index |
| POST | `/publicaciones` | `publicaciones.store` | PublicacionController@store |
| PUT/DELETE | `/publicaciones/{publicacion}` | `publicaciones.update/.destroy` | PublicacionController |
| POST | `/publicaciones/{publicacion}/react` | `publicaciones.react` | PublicacionController@react |
| POST | `/publicaciones/{publicacion}/comment` | `publicaciones.comment` | PublicacionController@comment |
| POST | `/publicaciones/{publicacion}/share` | `publicaciones.share` | PublicacionController@share |
| POST | `/comentarios/{comentario}/react` | `comentarios.react` | PublicacionController@reactComment |
| POST | `/notifications/mark-as-read` | `notifications.mark-as-read` | Closure |
| DELETE | `/notifications/{id}` | `notifications.destroy` | Closure |

---

## 15. Cliente Portal

Middleware: `auth`, `role:Cliente`

| Método | URI | Nombre | Controlador |
|--------|-----|--------|-------------|
| GET | `/cliente` | `cliente.dashboard` | ClientDashboardController@index |
| POST | `/cliente/pedidos` | `cliente.pedidos.store` | ClientDashboardController@storeOrder |
| POST | `/cliente/pedidos/{pedido}/cancelar` | `cliente.pedidos.cancelar` | ClientDashboardController@cancelOrder |
| POST | `/cliente/citas` | `cliente.citas.store` | ClientDashboardController@storeAppointment |
| POST | `/cliente/tickets` | `cliente.tickets.store` | ClientDashboardController@storeTicket |

---

## 16. Proveedor Portal

Middleware: `auth`, `role:Proveedor`

| Método | URI | Nombre | Controlador |
|--------|-----|--------|-------------|
| GET | `/proveedor` | `proveedor.dashboard` | ProveedorDashboardController@index |
| POST | `/proveedor/perfil` | `proveedor.perfil.update` | ProveedorDashboardController@updateProfile |
| GET | `/proveedor/compras/{compra}/pdf` | `proveedor.compras.pdf` | ProveedorDashboardController@downloadCompraPdf |
| POST | `/proveedor/documentos` | `proveedor.documentos.store` | ProveedorDashboardController@uploadDocument |
| DELETE | `/proveedor/documentos/{documento}` | `proveedor.documentos.destroy` | ProveedorDashboardController@deleteDocument |
| GET/POST | `/proveedors` | `proveedors.index/.store` | ProveedorController |

---

## 17. Configuración General

Middleware: `auth`

| Método | URI | Nombre | Controlador |
|--------|-----|--------|-------------|
| GET/POST | `/configuracion` | `configuracion.index/.store` | ConfiguracionController |
| GET | `/configuracion-web` | `configuracion-web.index` | WebSettingController@index |
| PUT | `/configuracion-web/{configuracion_web}` | `configuracion-web.update` | WebSettingController@update |
| GET/POST | `/mail-templates` | `mail-templates.index/.store` | MailTemplateController |
| PATCH | `/mail-templates/{mailTemplate}/toggle` | `mail-templates.toggle` | MailTemplateController@toggle |
| GET/POST | `/marketing/email-config` | `email-config.index/.store` | EmailConfigController |

---

## 18. Settings / Perfil de Usuario

Middleware: `auth` (algunas requieren `verified`)

| Método | URI | Nombre | Controlador |
|--------|-----|--------|-------------|
| GET | `/settings/profile` | `profile.edit` | ProfileController@edit |
| PATCH | `/settings/profile` | `profile.update` | ProfileController@update |
| DELETE | `/settings/profile` | `profile.destroy` | ProfileController@destroy |
| PATCH | `/settings/profile/onboarding` | `profile.onboarding.disable` | ProfileController@disableOnboarding |
| GET | `/settings/password` | `user-password.edit` | PasswordController@edit |
| PUT | `/settings/password` | `user-password.update` | PasswordController@update |
| GET | `/settings/two-factor` | `two-factor.show` | TwoFactorAuthenticationController@show |
| GET | `/settings/appearance` | `appearance.edit` | Inertia page |
| GET/PATCH | `/settings/public-profile` | `public-profile.edit/.update` | PublicProfileController |
| POST | `/settings/public-profile/toggle-active` | `public-profile.toggle-active` | PublicProfileController@toggleActive |
| GET/PATCH | `/mi-informacion` | `mi-informacion.show/.update` | UserProfileController |
| GET | `/settings/notifications` | `notifications.preferences` | Closure |
| POST | `/settings/notifications` | `notifications.preferences.update` | Closure |
| GET | `/perfil/{user}` | `profile.show` | ProfileController@show |

---

## 19. Usuarios, Roles y Permisos

Middleware: `auth`, `permission:admin.usuarios.viewAny`

| Método | URI | Nombre | Controlador |
|--------|-----|--------|-------------|
| GET/POST | `/usuarios-roles` | `usuarios-roles.index/.store` | UsuarioRolController |
| POST | `/usuarios-roles/role` | `usuarios-roles.role.store` | UsuarioRolController@storeRole |
| PUT | `/usuarios-roles/role/{role}` | `usuarios-roles.role.update` | UsuarioRolController@updateRole |
| DELETE | `/usuarios-roles/role/{role}` | `usuarios-roles.role.destroy` | UsuarioRolController@destroyRole |
| POST | `/usuarios-roles/permission` | `usuarios-roles.permission.store` | UsuarioRolController@storePermission |
| PUT | `/usuarios-roles/permission/{permission}` | `usuarios-roles.permission.update` | UsuarioRolController@updatePermission |
| DELETE | `/usuarios-roles/permission/{permission}` | `usuarios-roles.permission.destroy` | UsuarioRolController@destroyPermission |
| PATCH | `/usuarios-roles/user/{user}` | `usuarios-roles.user.update` | UsuarioRolController@updateUser |
| POST | `/usuarios-roles/user/{user}/reset-password` | `usuarios-roles.user.reset-password` | UsuarioRolController@resetUserPassword |
| POST | `/usuarios-roles/user/{user}/toggle-ban` | `usuarios-roles.user.toggle-ban` | UsuarioRolController@toggleBan |
| POST | `/usuarios/{user}/follow` | `usuarios.follow` | FollowerController@follow |
| DELETE | `/usuarios/{user}/unfollow` | `usuarios.unfollow` | FollowerController@unfollow |

---

## 20. Citas / Servicios

| Método | URI | Nombre | Controlador |
|--------|-----|--------|-------------|
| GET/POST | `/appointments` | `appointments.index/.store` | AppointmentController |
| GET | `/appointments/calendar` | `appointments.calendar` | AppointmentController@calendar |
| GET | `/appointments/calendar/google-auth` | `appointments.calendar.google-auth` | AppointmentController@redirectToGoogle |
| GET | `/appointments/calendar/google-callback` | `appointments.calendar.google-callback` | AppointmentController@handleGoogleCallback |
| POST | `/appointments/calendar/sync` | `appointments.calendar.sync` | AppointmentController@syncGoogleEvents |
| GET/POST | `/services` | `services.index/.store` | ServiceController |

---

## 21. LMS — Cursos

Middleware: `auth`

| Método | URI | Nombre | Controlador |
|--------|-----|--------|-------------|
| GET | `/cursos` | `lms.courses.index` | CourseController@index |
| GET | `/cursos/{course}` | `lms.courses.show` | CourseController@show |
| POST | `/cursos/{course}/enroll` | `lms.courses.enroll` | CourseController@enroll |
| GET | `/alumno/cursos` | `lms.student.courses` | StudentController@myCourses |
| GET | `/alumno/progreso` | `lms.student.progress` | StudentController@progress |
| GET | `/instructor/dashboard` | `lms.instructor.dashboard` | StudentController@instructorDashboard |
| GET/POST | `/instructor/cursos` | `lms.instructor.cursos.index/.store` | InstructorCourseController |
| PUT/DELETE | `/instructor/cursos/{course}` | `lms.instructor.cursos.update/.destroy` | InstructorCourseController |
| POST | `/instructor/cursos/{course}/publish` | `lms.instructor.cursos.publish` | InstructorCourseController@publish |
| POST | `/instructor/cursos/{course}/modules` | `lms.instructor.modules.store` | ModuleController@store |
| GET/POST | `/lecciones` | `lms.lessons.index/.store` | LessonController |

---

## 22. Rifas (Raffles)

Middleware: `auth`

| Método | URI | Nombre | Controlador |
|--------|-----|--------|-------------|
| GET/POST | `/raffles` | `raffles.index/.store` | RaffleController |
| GET/PUT/DELETE | `/raffles/{raffle}` | `raffles.show/.update/.destroy` | RaffleController |
| GET | `/raffles/{raffle}/draw-room` | `raffles.draw-room` | RaffleController@drawRoom |
| POST | `/raffles/{raffle}/draw` | `raffles.draw` | RaffleController@draw |
| POST | `/raffles/{raffle}/prizes` | `raffles.prizes.store` | RaffleController@storePrize |
| PUT/DELETE | `/prizes/{prize}` | `raffles.prizes.update/.destroy` | RaffleController |
| GET | `/raffles/draws` | `raffles.draws.index` | RaffleController@drawsIndex |
| GET | `/raffles/{raffle}/export` | `raffles.export` | RaffleController@exportParticipants |

---

## 23. Variantes POS

Middleware: `auth`, permisos `pos.*`

| Método | URI | Nombre | Controlador |
|--------|-----|--------|-------------|
| GET/POST | `/pos/variantes` | `pos.variantes` | VarianteController |

---

## 24. Afiliados

| Método | URI | Nombre | Controlador |
|--------|-----|--------|-------------|
| GET | `/afiliados/recomendar` | `afiliados.recomendar` | AffiliateController@recommend |
| GET | `/afiliados/red` | `afiliados.red` | AffiliateController@network |

---

## 25. API

| Método | URI | Controlador |
|--------|-----|-------------|
| GET | `/api/user` | Closure |
| POST | `/api/v1/tracking/update` | TrackingController@updateLocation |
| GET | `/api/global-search` | GlobalSearchController@search |
| GET | `/api/sii/consultar/{rut}` | SiiController@validarRut |
| POST | `/api/sii/validar-rut` | SiiController@validarRut |

---

## Mapa de Middleware por Grupo

| Grupo de Rutas | Middleware |
|----------------|-----------|
| Públicas | — |
| Dashboard | `auth`, `permission:ver dashboard` |
| CRM | `auth`, `permission:comercial.*` |
| Ventas | `auth`, `permission:ventas.*` |
| Productos | `auth`, `permission:comercial.productos.*` |
| Inventario | `auth`, `permission:inventario.*` |
| RRHH | `auth`, `permission:rrhh.*` |
| Finanzas | `auth`, `permission:finanzas.*` |
| Producción | `auth`, `permission:produccion.*` |
| Proyectos | `auth`, `permission:proyectos.*` |
| Flota | `auth`, `permission:flota.*` |
| Uptime | `auth` |
| Cliente Portal | `auth`, `role:Cliente` |
| Proveedor Portal | `auth`, `role:Proveedor` |
| Configuración | `auth`, `role:Master\|Super Admin\|Administrador`, `permission:admin.*` |
| Usuarios/Roles | `auth`, `permission:admin.usuarios.viewAny` |
| Settings | `auth`, `verified` (algunas) |
| Marketplace | — (público), `auth` (checkout/chat) |
| Pagos | `auth` |

---

## Modelos Multi-Tenant (OwnerScope)

Todos los modelos con `BelongsToOwner` trait filtran automáticamente por `owner_id`:

- Almacen, Categoria, Producto, Cliente, Proveedor, Empleado
- Venta, Compra, Factura, DetalleFactura, Cotizacion
- Pedido, PedidoItem, Pago, Cobranza
- Proyecto, Tarea, Hito, GastoProyecto, GrupoTrabajo
- Conductor, Vehiculo, Entrega
- UptimeAlert, UptimeCheck, MonitoredSite
- Raffle, RaffleParticipant, RafflePrize
- DashboardConfig, Configuracion
- Y ~40+ modelos adicionales

**Bypass**: Master y Super Admin via `Gate::before` y `OwnerScope`.
