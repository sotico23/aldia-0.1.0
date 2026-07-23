<?php

namespace Database\Seeders;

use App\Models\Almacen;
use App\Models\Asistencia;
use App\Models\Bom;
use App\Models\Campana;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Cobranza;
use App\Models\Compra;
use App\Models\Conductor;
use App\Models\Configuracion;
use App\Models\ControlCalidad;
use App\Models\Cotizacion;
use App\Models\Empleado;
use App\Models\Entrega;
use App\Models\Evaluacion;
use App\Models\Factura;
use App\Models\GrupoTrabajo;
use App\Models\Hito;
use App\Models\Impuesto;
use App\Models\Inventario;
use App\Models\Lote;
use App\Models\MailTemplate;
use App\Models\MonitoredSite;
use App\Models\Movimiento;
use App\Models\Nomina;
use App\Models\Oportunidad;
use App\Models\OrdenProduccion;
use App\Models\Pago;
use App\Models\Planificacion;
use App\Models\Producto;
use App\Models\Promocion;
use App\Models\Prospecto;
use App\Models\Proveedor;
use App\Models\Proyecto;
use App\Models\Raffle;
use App\Models\Tarea;
use App\Models\Tesoreria;
use App\Models\Ticket;
use App\Models\Timesheet;
use App\Models\User;
use App\Models\Vehiculo;
use App\Models\Venta;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    private function factory(string $modelClass): Factory
    {
        return Factory::factoryForModel($modelClass);
    }

    public function run(): void
    {
        $users = User::all();

        foreach ($users as $user) {
            $this->seedData($user);
        }
    }

    private function seedData(User $user): void
    {
        $ownerId = $user->getOwnerId();

        $this->command->info("Seeding for {$user->name} ({$user->email}) — ownerId: {$ownerId}");

        $catIds = $this->seedCategorias($ownerId, $user->id);
        $prodIds = $this->seedProductos($ownerId, $user->id, $catIds['producto']);
        $almIds = $this->seedAlmacenes($ownerId, $user->id);
        $cliIds = $this->seedClientes($ownerId, $user->id, $catIds['cliente']);
        $provIds = $this->seedProveedores($ownerId, $user->id, $catIds['proveedor']);
        $empIds = $this->seedEmpleados($ownerId, $user->id, $almIds);

        $this->seedInventario($ownerId, $prodIds, $almIds);
        $this->seedProspectos($ownerId);
        $this->seedOportunidades($ownerId, $cliIds);
        $this->seedCotizaciones($ownerId, $user->id, $cliIds);
        $this->seedVentas($ownerId, $user->id, $cliIds, $prodIds);
        $this->seedCompras($ownerId, $provIds, $prodIds);
        $this->seedMovimientos($ownerId, $almIds);
        $this->seedLotes($ownerId);
        $this->seedBoms($ownerId);
        $this->seedOrdenesProduccion($ownerId);
        $this->seedControlCalidad($ownerId);
        $this->seedPlanificacion($ownerId);
        $this->seedFacturas($ownerId, $user->id, $cliIds, $prodIds);
        $this->seedCobranzas($ownerId, $cliIds);
        $this->seedPagos($ownerId, $provIds);
        $this->seedNominas($ownerId);
        $this->seedAsistencia($ownerId, $empIds);
        $this->seedEvaluaciones($ownerId, $empIds, $user->id);
        $this->seedProyectos($ownerId, $empIds, $user->id);
        $this->seedTareas($ownerId, $user->id, $empIds);
        $this->seedTimesheets($ownerId);
        $this->seedVehiculos($ownerId);
        $this->seedConductores($ownerId);
        $this->seedEntregas($ownerId);
        $this->seedCampanas($ownerId);
        $this->seedTickets($ownerId, $user->id, $cliIds);
        $this->seedImpuestos($ownerId);
        $this->seedTesoreria($ownerId);
        $this->seedMonitoreo($ownerId, $user->id);
        $this->seedRifas($ownerId, $user->id);
        $this->seedPromociones($ownerId, $user->id);
        $this->seedConfiguracion($ownerId);
        $this->seedGruposTrabajo($ownerId, $user->id);
        $this->seedMailTemplates($ownerId);
    }

    private function seedCategorias(int $ownerId, int $userId): array
    {
        $ids = [];
        foreach (['producto', 'cliente', 'proveedor', 'servicio'] as $tipo) {
            $ids[$tipo] = $this->factory(Categoria::class)->count(5)->create([
                'user_id' => $userId, 'owner_id' => $ownerId, 'tipo' => $tipo,
            ])->pluck('id')->toArray();
        }

        return $ids;
    }

    private function seedProductos(int $ownerId, int $userId, array $catIds): array
    {
        return $this->factory(Producto::class)->count(20)->create([
            'user_id' => $userId,
            'owner_id' => $ownerId,
            'categoria_id' => fn () => $catIds[array_rand($catIds)],
            'peso_base' => fake()->randomFloat(2, 0.1, 100),
        ])->pluck('id')->toArray();
    }

    private function seedAlmacenes(int $ownerId, int $userId): array
    {
        return $this->factory(Almacen::class)->count(3)->create([
            'user_id' => $userId, 'owner_id' => $ownerId,
        ])->pluck('id')->toArray();
    }

    private function seedClientes(int $ownerId, int $userId, array $catIds): array
    {
        return $this->factory(Cliente::class)->count(12)->create([
            'user_id' => $userId,
            'owner_id' => $ownerId,
            'categoria_id' => fn () => $catIds[array_rand($catIds)],
        ])->pluck('id')->toArray();
    }

    private function seedProveedores(int $ownerId, int $userId, array $catIds): array
    {
        return $this->factory(Proveedor::class)->count(8)->create([
            'user_id' => $userId,
            'owner_id' => $ownerId,
            'categoria_id' => fn () => $catIds[array_rand($catIds)],
        ])->pluck('id')->toArray();
    }

    private function seedInventario(int $ownerId, array $prodIds, array $almIds): void
    {
        foreach ($almIds as $almId) {
            $selected = $prodIds;
            shuffle($selected);
            foreach (array_slice($selected, 0, 5) as $pId) {
                $this->factory(Inventario::class)->create([
                    'owner_id' => $ownerId, 'almacen_id' => $almId, 'producto_id' => $pId,
                ]);
            }
        }
    }

    private function seedProspectos(int $ownerId): void
    {
        $this->factory(Prospecto::class)->count(10)->create(['owner_id' => $ownerId]);
    }

    private function seedOportunidades(int $ownerId, array $cliIds): void
    {
        $this->factory(Oportunidad::class)->count(8)->create([
            'owner_id' => $ownerId,
            'cliente_id' => fn () => $cliIds[array_rand($cliIds)],
        ]);
    }

    private function seedCotizaciones(int $ownerId, int $userId, array $cliIds): void
    {
        $this->factory(Cotizacion::class)->count(8)->create([
            'owner_id' => $ownerId,
            'user_id' => $userId,
            'cliente_id' => fn () => $cliIds[array_rand($cliIds)],
        ]);
    }

    private function seedVentas(int $ownerId, int $userId, array $cliIds, array $prodIds): void
    {
        $this->factory(Venta::class)->count(10)->create([
            'owner_id' => $ownerId,
            'user_id' => $userId,
            'cliente_id' => fn () => $cliIds[array_rand($cliIds)],
        ])->each(function (Venta $venta) use ($prodIds) {
            $subtotal = 0;
            foreach (range(1, rand(2, 5)) as $i) {
                $cantidad = rand(1, 10);
                $precio = rand(5000, 100000);
                $venta->detalleVentas()->create([
                    'owner_id' => $venta->owner_id,
                    'producto_id' => $prodIds[array_rand($prodIds)],
                    'cantidad' => $cantidad,
                    'precio_unitario' => $precio,
                    'subtotal' => $cantidad * $precio,
                    'subtotal_metrica' => $cantidad * $precio,
                ]);
                $subtotal += $cantidad * $precio;
            }
            $venta->updateQuietly(['subtotal' => $subtotal, 'iva' => (int) round($subtotal * 0.19), 'total' => (int) round($subtotal * 1.19)]);
        });
    }

    private function seedCompras(int $ownerId, array $provIds, array $prodIds): void
    {
        $this->factory(Compra::class)->count(5)->create([
            'owner_id' => $ownerId,
            'proveedor_id' => fn () => $provIds[array_rand($provIds)],
        ])->each(function (Compra $compra) use ($prodIds) {
            $subtotal = 0;
            foreach (range(1, rand(2, 4)) as $i) {
                $cantidad = rand(1, 50);
                $precio = rand(1000, 50000);
                $compra->detalleCompras()->create([
                    'owner_id' => $compra->owner_id,
                    'producto_id' => $prodIds[array_rand($prodIds)],
                    'cantidad' => $cantidad,
                    'precio_unitario' => $precio,
                    'subtotal' => $cantidad * $precio,
                ]);
                $subtotal += $cantidad * $precio;
            }
            $iva = round($subtotal * 0.19);
            $compra->updateQuietly(['subtotal' => $subtotal, 'iva' => $iva, 'total' => $subtotal + $iva]);
        });
    }

    private function seedMovimientos(int $ownerId, array $almIds): void
    {
        $this->factory(Movimiento::class)->count(10)->create([
            'owner_id' => $ownerId,
            'almacen_origen' => fn () => (string) $almIds[array_rand($almIds)],
        ]);
    }

    private function seedLotes(int $ownerId): void
    {
        $this->factory(Lote::class)->count(8)->create(['owner_id' => $ownerId]);
    }

    private function seedBoms(int $ownerId): void
    {
        $this->factory(Bom::class)->count(3)->create(['owner_id' => $ownerId]);
    }

    private function seedOrdenesProduccion(int $ownerId): void
    {
        $this->factory(OrdenProduccion::class)->count(3)->create(['owner_id' => $ownerId]);
    }

    private function seedControlCalidad(int $ownerId): void
    {
        $this->factory(ControlCalidad::class)->count(3)->create(['owner_id' => $ownerId]);
    }

    private function seedPlanificacion(int $ownerId): void
    {
        $this->factory(Planificacion::class)->count(3)->create(['owner_id' => $ownerId]);
    }

    private function seedFacturas(int $ownerId, int $userId, array $cliIds, array $prodIds): void
    {
        foreach (range(1, 8) as $i) {
            $factura = Factura::create([
                'owner_id' => $ownerId, 'user_id' => $userId,
                'cliente_id' => $cliIds[array_rand($cliIds)],
                'numero' => 'FAC-'.fake()->unique()->numerify('########'),
                'fecha' => fake()->dateTimeBetween('-1 year', 'now'),
                'fecha_vencimiento' => fake()->dateTimeBetween('now', '+60 days'),
                'tipo' => fake()->randomElement(['venta', 'compra', 'cotizacion', 'proforma']),
                'estado' => fake()->randomElement(['pendiente', 'pagada', 'anulada']),
                'iva_porcentaje' => 19, 'iva_incluido' => true,
                'descuento_tipo' => 'none', 'descuento_valor' => 0, 'total_descuento' => 0,
            ]);
            $subtotal = 0;
            foreach (range(1, rand(2, 5)) as $j) {
                $cantidad = rand(1, 10);
                $precio = rand(5000, 50000);
                $neto = $cantidad * $precio;
                $factura->detalles()->create([
                    'producto_id' => $prodIds[array_rand($prodIds)],
                    'cantidad' => $cantidad, 'precio_unitario' => $precio,
                    'subtotal' => $neto, 'impuesto' => round($neto * 0.19), 'total' => (int) round($neto * 1.19),
                ]);
                $subtotal += $neto;
            }
            $factura->updateQuietly(['subtotal' => $subtotal, 'impuesto' => round($subtotal * 0.19), 'total' => (int) round($subtotal * 1.19)]);
        }
    }

    private function seedCobranzas(int $ownerId, array $cliIds): void
    {
        foreach (range(1, 5) as $i) {
            Cobranza::create([
                'owner_id' => $ownerId,
                'cliente_id' => $cliIds[array_rand($cliIds)],
                'monto' => fake()->randomFloat(2, 10000, 500000),
                'fecha_pago' => fake()->dateTimeBetween('-6 months', 'now'),
                'metodo_pago' => fake()->randomElement(['efectivo', 'transferencia', 'cheque', 'tarjeta']),
                'estado' => fake()->randomElement(['completado', 'pendiente', 'fallido']),
                'notas' => fake()->optional()->sentence(),
            ]);
        }
    }

    private function seedPagos(int $ownerId, array $provIds): void
    {
        foreach (range(1, 5) as $i) {
            Pago::create([
                'owner_id' => $ownerId,
                'proveedor_id' => $provIds[array_rand($provIds)],
                'monto' => fake()->randomFloat(2, 10000, 1000000),
                'fecha_pago' => fake()->dateTimeBetween('-6 months', 'now'),
                'metodo_pago' => fake()->randomElement(['transferencia', 'cheque', 'efectivo', 'tarjeta']),
                'estado' => fake()->randomElement(['completado', 'pendiente', 'fallido']),
                'notas' => fake()->optional()->sentence(),
            ]);
        }
    }

    private function seedEmpleados(int $ownerId, int $userId, array $almIds): array
    {
        return $this->factory(Empleado::class)->count(8)->create([
            'owner_id' => $ownerId, 'user_id' => $userId, 'creator_id' => $userId,
            'almacen_id' => fn () => $almIds[array_rand($almIds)],
        ])->pluck('id')->toArray();
    }

    private function seedNominas(int $ownerId): void
    {
        $this->factory(Nomina::class)->count(3)->create(['owner_id' => $ownerId]);
    }

    private function seedAsistencia(int $ownerId, array $empIds): void
    {
        $this->factory(Asistencia::class)->count(15)->create([
            'owner_id' => $ownerId,
            'empleado_id' => fn () => $empIds[array_rand($empIds)],
        ]);
    }

    private function seedEvaluaciones(int $ownerId, array $empIds, int $userId): void
    {
        foreach (range(1, 3) as $i) {
            Evaluacion::create([
                'owner_id' => $ownerId,
                'empleado_id' => $empIds[array_rand($empIds)],
                'evaluador_id' => $empIds[array_rand($empIds)],
                'fecha' => fake()->dateTimeBetween('-3 months', 'now'),
                'puntuacion' => rand(1, 5),
                'comentarios' => fake()->paragraph(),
                'tipo' => fake()->randomElement(['desempeno', 'periodica', 'probatoria', '360']),
            ]);
        }
    }

    private function seedProyectos(int $ownerId, array $empIds, int $userId): void
    {
        $this->factory(Proyecto::class)->count(3)->create(['owner_id' => $ownerId])
            ->each(function (Proyecto $proyecto) use ($ownerId, $userId) {
                foreach (range(1, 3) as $i) {
                    Hito::create([
                        'owner_id' => $ownerId,
                        'proyecto_id' => $proyecto->id,
                        'nombre' => 'Hito '.$i,
                        'descripcion' => fake()->sentence(),
                        'fecha_vencimiento' => fake()->dateTimeBetween('-1 month', '+3 months'),
                        'estado' => fake()->randomElement(['pendiente', 'en_progreso', 'completado', 'atrasado']),
                        'progreso' => rand(0, 100),
                        'responsable_id' => $userId,
                    ]);
                }
            });
    }

    private function seedTareas(int $ownerId, int $userId, array $empIds): void
    {
        $this->factory(Tarea::class)->count(8)->create([
            'owner_id' => $ownerId, 'user_id' => $userId,
            'empleado_id' => $userId,
        ]);
    }

    private function seedTimesheets(int $ownerId): void
    {
        $this->factory(Timesheet::class)->count(8)->create(['owner_id' => $ownerId]);
    }

    private function seedVehiculos(int $ownerId): void
    {
        $this->factory(Vehiculo::class)->count(3)->create(['owner_id' => $ownerId]);
    }

    private function seedConductores(int $ownerId): void
    {
        $this->factory(Conductor::class)->count(3)->create(['owner_id' => $ownerId]);
    }

    private function seedEntregas(int $ownerId): void
    {
        $this->factory(Entrega::class)->count(5)->create(['owner_id' => $ownerId]);
    }

    private function seedCampanas(int $ownerId): void
    {
        $this->factory(Campana::class)->count(3)->create(['owner_id' => $ownerId]);
    }

    private function seedTickets(int $ownerId, int $userId, array $cliIds): void
    {
        $this->factory(Ticket::class)->count(5)->create([
            'owner_id' => $ownerId,
            'assigned_user_id' => $userId,
            'cliente_id' => fn () => $cliIds[array_rand($cliIds)],
        ]);
    }

    private function seedImpuestos(int $ownerId): void
    {
        foreach (range(1, 3) as $i) {
            Impuesto::create([
                'owner_id' => $ownerId,
                'nombre' => fake()->randomElement(['IVA 19%', 'IVA 19% Reducido', 'Impuesto Específico', 'Retención']).' '.fake()->numberBetween(1, 99),
                'tasa' => fake()->randomFloat(2, 0, 40),
                'tipo' => fake()->randomElement(['porcentaje', 'fijo']),
                'codigo' => fake()->numerify('IMP-####'),
                'notas' => fake()->optional()->sentence(),
            ]);
        }
    }

    private function seedTesoreria(int $ownerId): void
    {
        $this->factory(Tesoreria::class)->count(5)->create(['owner_id' => $ownerId]);
    }

    private function seedMonitoreo(int $ownerId, int $userId): void
    {
        $this->factory(MonitoredSite::class)->count(3)->create([
            'owner_id' => $ownerId, 'user_id' => $userId,
        ]);
    }

    private function seedRifas(int $ownerId, int $userId): void
    {
        foreach (range(1, 3) as $i) {
            Raffle::create([
                'owner_id' => $ownerId, 'user_id' => $userId,
                'title' => fake()->sentence(3), 'description' => fake()->paragraph(),
                'slug' => fake()->unique()->slug(3),
                'type' => fake()->randomElement(['raffle', 'draw', 'competition']),
                'status' => fake()->randomElement(['draft', 'active', 'completed', 'cancelled']),
                'start_date' => fake()->dateTimeBetween('-1 week', 'now'),
                'end_date' => fake()->dateTimeBetween('now', '+1 month'),
            ]);
        }
    }

    private function seedPromociones(int $ownerId, int $userId): void
    {
        $this->factory(Promocion::class)->count(3)->create([
            'owner_id' => $ownerId, 'user_id' => $userId,
        ]);
    }

    private function seedConfiguracion(int $ownerId): void
    {
        $this->factory(Configuracion::class)->count(5)->create(['owner_id' => $ownerId]);
    }

    private function seedGruposTrabajo(int $ownerId, int $userId): void
    {
        $this->factory(GrupoTrabajo::class)->count(2)->create([
            'owner_id' => $ownerId, 'user_id' => $userId,
        ]);
    }

    private function seedMailTemplates(int $ownerId): void
    {
        foreach (['Bienvenida', 'Promoción', 'Recordatorio'] as $name) {
            MailTemplate::create([
                'owner_id' => $ownerId,
                'name' => $name,
                'slug' => str($name)->slug()->append('-', fake()->uuid()),
                'subject' => "{$name} - {{business_name}}",
                'content' => "<h1>{$name}</h1><p>Hola {{user_name}}, gracias por confiar en nosotros.</p>",
                'is_active' => true,
                'type' => 'marketing',
            ]);
        }
    }
}
