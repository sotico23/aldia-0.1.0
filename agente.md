# Instrucciones del Agente - Arquitectura y Flujo de Trabajo ERP SaaS

Este documento contiene las de las reglas y el contexto maestro para el Agente que replique, diseñe o escale el sistema actual hacia **Laravel 12, Livewire 3 y Tailwind CSS v4**.

## 1. Stack Tecnológico Objetivo
*   **Backend:** Laravel 12 (PHP 8.4+). Uso estricto de la estructura moderna `bootstrap/app.php` (sin Kernels, registramos middleware/routing desde ahí).
*   **Frontend:** Livewire 3 (usando preferentemente Full-page components) y Alpine.js para la interactividad reactiva.
*   **Estilos:** Tailwind CSS v4 con un enfoque en diseño **Premium** (Glassmorphism, sombras muy suaves, Dark Mode nativo integrado).
*   **Tests:** Pest PHP v4 (TDD es obligatorio antes de cerrar cualquier característica crítica).
*   **Base de Datos:** Eloquent ORM puro (evitar DB::raw en lo posible) con tipado estricto. Migraciones con total integridad referencial (Foreign keys on cascada/restrict) y uso de índices.

## 2. Mapa Estructural de Módulos (Dominios de Negocio)
El ERP es monolítico y modular, albergando 11 dominios clave que marcan las fronteras de los features:
- Ventas / CRM
- POS (Punto de Venta y Cierres)
- Inventario / WMS (Warehouse)
- Producción / MRP
- Finanzas / Contabilidad (y reglas del SII)
- Recursos Humanos (RRHH)
- Control de Proyectos
- Logística y Entregas
- Marketplace de Tiendas Reales
- Monitoreo (Uptime Checks)
- Core/Admin

*Nota: Segmenta siempre la lógica respetando estos límites.*

## 3. Directrices de Arquitectura y Reglas Globales

### A. Localización Latinoamericana y Multi-divisa (Value Objects)
El sistema se utiliza en múltiples países de Latam. Por ende, la estandarización y evitar la pérdida de precisión son vitales.
*   **Acción Requerida:** NO debes usar simples `strings` para identificadores fiscales como el RUT chileno, RFC mexicano o NIT. Tienes que implementar y aprovechar un componente **Value Object** llamado `App\ValueObjects\TaxId` que internamente gestione la limpieza, validación y formateo dependiendo del enum del País.
*   **Manejo de Monedas/Dinero:** Todo monto financiero (precios, impuestos, totales) en BD y Lógica se guarda de forma obligatoria como **Integer o BigInteger representando CENTAVOS**, para erradicar cualquier fallo matemático por coma flotante (Crucial para el ecosistema del SII y pesos tipo CLP).

### B. Migración Arquitectónica UI State Management
*   Erradicar y sustituir de pleno React/Inertia por componentes **Livewire tipo Page**, usando el decorador de layout principal `#[Layout('layouts.app')]`.
*   Mantener las clases Livewire exclusivas al manejo del DOM Virtual. Mueve absolutamente toda **lógica pesada a Actions** (ubicadas en `app/Actions/`) o a clases de Servicio puras.
*   Emplea profusamente `wire:navigate` (o sus alias de `href`) para mantener la magia e instantaneidad de la navegación estilo SPA.

### C. Multi-Tenancy y Seguridad Estricta
*   En cada query hacia la base de datos se debe agregar ineludiblemente el filtro de fragmentación de tenant por `owner_id` o `user_id` dependiendo la tabla, cuidando evitar exposición transversal ("data leakage").
*   Validación imperativa: Debes comprobar permisos de operaciones que muten datos aplicando Laravel Policies de manera restrictiva en la lógica o en el middleware.

### D. Diseño de Interfaz Premium (Tailwind 4)
*   Está **prohibido** el diseño básico de "solo cajas grises" que luzca incompleto. Debemos proveer una UI atractiva.
*   Aplica **colores HSL** precisos, e implementa múltiples micro-interacciones. Reutiliza siempre Componentes de Blade anonimizados para evitar la duplicación de directivas CSS (especialmente en Cards y Modales).
*   **Paleta de Colores Sugerida:** Tonos pastel relajantes (Mint pálido, rosa muy suave, colores tierra claros) que inspiren "paz intelectual y organización operativa".

### E. Autogestión de Sub-Agentes y Skills (Orquestación)
*   **Acción Requerida:** Eres libre de comportarte como un orquestador. Si notas una de tus tareas muy monolítica o ambiciosa (por ejemplo el Módulo de Facturación Electrónica), asume la autogeneración de "Sub-Agentes especializados" para particionar y despachar el trabajo.
*   **Skills:** Si identificas que debes repetir una porción lógica de infraestructura en varios lugares, deten la repetición. Refactoriza al vuelo tu abstracción mediante Traits de PHP o creando sub-clases y Actions reutilizables.
*   Debes ir relatando al programador subyacente cada decisión de infraestructura u orquestación que vayas tomando mediante el log del chat.

## 4. Reglas de Código y Estilo Fuerte
*   **Tipado Riguroso:** Cada propiedad y retorno de función (por minúsculo que sea) debe declarar su tipo formal en la firma (`public string $foo;`, `: bool`, `: RedirectResponse`). Además, saca ventaja constructiva de "constructor property promotion" de PHP 8.4.
*   Intenta utilizar la sintaxis nueva de Livewire 3 basada en atributos `#[Validate(...)]` inyectados en las propiedades, pero si tienes lógica cruzada o muy enredada, sepárala al tradicional *Form Request* de HTTP.
*   **Calidad de Sintaxis:** Ejecución automática u obligatoria de `vendor/bin/pint --format agent` de cada archivo que logres finalizar.

## 5. Instrucciones de Inicialización (Flujo de Trabajo del Agente)
Cualquier desafío grande que afrontes para arrancar o mejorar desde cero un módulo debe observar obligatoriamente los siguientes 6 pasos:

1.  **Lectura de Entorno:** Cerciorarte de la existencia del núcleo Laravel 12 y la instalación y versión de Livewire 3 mediante la revisión de dependencias básicas (como composer.json o config).
2.  **Identificación de País:** *Paso de verificación previa.* Antes de atreverte a modelar las entidades de Facturas o Cuentas Clientes, confirma con el Usuario (o a nivel configuración App) a qué país de LATAM te estás refiriendo, para establecer el cast exacto del TaxId y las divisiones de enteros.
3.  **Definición de Entidades en BD:** Crear la Migración -> Modelo (con Factory) -> Seeders.
4.  **Despliegue de Lógica Backend:** Confeccionar las Acciones (app/Actions), Servicios de API si existieran, e irremediablemente sus Controllers auxiliares o Policies.
5.  **Construcción Vistas Premium:** Crear el componente Livewire y volcar diseño estético utilizando directivas Tailwind v4 Premium y componentes compartidos de Blade.
6.  **Aseguramiento Testing (QA):** Finalizar comprobando legibilidad mediante `/bin/pint` y correr/desarrollar su equivalente *Feature Tests* implementado en base a `Pest v4` para garantizar su viabilidad.
