<?php

namespace Database\Factories;

use App\Models\Producto;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductoFactory extends Factory
{
    protected $model = Producto::class;

    public function definition(): array
    {
        $tiposProducto = [
            'Desktop HP ProDesk 400 G7', 'Laptop Lenovo ThinkPad E14', 'Monitor Samsung 24"', 'Teclado Mecánico RGB',
            'Mouse Inalámbrico Logitech', 'Impresora Laser HP', 'Router WiFi TP-Link', 'Cable HDMI 2m',
            'Memoria USB 32GB', 'Disco SSD 500GB', 'RAM DDR4 8GB', 'Fuente de Poder 500W',
            'Gabinete ATX', 'Pasta Térmica Arctic', 'Ventilador 120mm', 'Cable Ethernet Cat6',
            'Switch 8 Puertos',             'Webcam Logitech C920', 'Micrófono Condenser', 'Auriculares Gaming',
            'Silla Ergonómica', 'Escritorio Regulable', 'Lámpara LED Escritorio', 'Organizador de Cables',
            'Paquete Hojas Carta x500', 'Tóner HP 105A', 'Bolígrafo Azul x12', 'Carpeta Archivador',
            'Calculadora Científica', 'Regla 30cm', 'Pegamento Stick', 'Tijeras Oficiales',
        ];

        return [
            'user_id' => User::factory(),
            'codigo' => fake()->unique()->bothify('PRO-####-???'),
            'unidad_medida' => fake()->randomElement(['unidad', 'kg', 'lt']),
            'nombre' => fake()->randomElement($tiposProducto).' '.fake()->numberBetween(100, 999),
            'descripcion' => fake()->paragraph(),
            'categoria_id' => fake()->numberBetween(1, 10),
            'precio_compra' => fake()->randomFloat(2, 1000, 50000),
            'precio_venta' => fake()->randomFloat(2, 5000, 100000),
            'stock_minimo' => fake()->numberBetween(5, 50),
            'envase_retornable' => fake()->boolean(30),
            'medida_pesable' => fake()->boolean(20),
            'tipo_medida' => fake()->randomElement(['unidad', 'kilo', 'litro']),
            'cantidad_medida' => fake()->randomFloat(2, 0.5, 10),
            'tipo_envase' => fake()->randomElement(['caja', 'bolsa', 'bidón', 'envase']),
            'activo' => true,
            'mostrar_en_perfil' => fake()->boolean(70),
            'is_service' => false,
            'duracion' => 0,
            'peso_base' => 0,
        ];
    }

    public function service(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_service' => true,
            'duracion' => fake()->randomElement([30, 45, 60, 90, 120]),
            'mostrar_en_perfil' => true,
            'precio_compra' => 0,
            'stock_minimo' => 0,
            'codigo' => fake()->unique()->bothify('SRV-####-???'),
            'nombre' => fake()->randomElement([
                'Corte de Cabello', 'Manicura Completa', 'Masaje Descontracturante',
                'Limpieza Facial', 'Consulta Médica', 'Clase de Yoga Particular',
                'Asesoría Legal', 'Reparación de PC', 'Instalación de Software',
            ]),
        ]);
    }
}
