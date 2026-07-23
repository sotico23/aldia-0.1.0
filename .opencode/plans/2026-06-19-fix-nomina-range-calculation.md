# Plan: Corregir cálculo de nómina por rango de fechas

## Problema
`calcularProporcional()` siempre calcula sobre el **mes completo** (whereYear/whereMonth del periodo), ignorando `fecha_inicio`/`fecha_fin`. Aunque el usuario marque solo 15 días en el rango, cuenta los 19 registros del mes.

## Cambios

### 1. Backend — `app/Http/Controllers/Backend/NominaController.php`

Reemplazar el método `calcularProporcional` (líneas 88-106):

**Antes:**
```php
public function calcularProporcional(Request $request)
{
    $periodo = $request->input('periodo');
    if (! $periodo) {
        return response()->json([]);
    }
    // ...
    $year = substr($periodo, 0, 4);
    $month = substr($periodo, 5, 2);

    $asistenciasPorEmpleado = Asistencia::whereIn('empleado_id', $empleados->pluck('id'))
        ->whereYear('fecha', $year)
        ->whereMonth('fecha', $month)
        ->get()
        ->groupBy('empleado_id');
```

**Después:**
```php
public function calcularProporcional(Request $request)
{
    $periodo = $request->input('periodo');
    $fechaInicio = $request->input('fecha_inicio');
    $fechaFin = $request->input('fecha_fin');

    if (! $periodo) {
        return response()->json([]);
    }
    // ...
    $asistenciasQuery = Asistencia::whereIn('empleado_id', $empleados->pluck('id'));

    if ($fechaInicio && $fechaFin) {
        $asistenciasQuery->whereBetween('fecha', [$fechaInicio, $fechaFin]);
    } else {
        $year = substr($periodo, 0, 4);
        $month = substr($periodo, 5, 2);
        $asistenciasQuery->whereYear('fecha', $year)->whereMonth('fecha', $month);
    }

    $asistenciasPorEmpleado = $asistenciasQuery->get()->groupBy('empleado_id');
```

### 2. Frontend — `resources/js/pages/Backend/Nominas/Index.tsx`

Reemplazar el método `calcularNominas` (líneas 157-175):

**Antes:**
```ts
const calcularNominas = async () => {
    if (!data.periodo) {
        alert('Ingrese el período primero (ej: 2026-05)');
        return;
    }
    try {
        const res = await fetch(`/nominas/calcular?periodo=${data.periodo}`);
        const calculos = await res.json();
        const sumBruto = calculos.reduce((acc: number, curr: any) => acc + curr.sueldo_proporcional, 0);
        setData(prev => ({
            ...prev,
            detalles: calculos,
            total_bruto: sumBruto,
            total_neto: sumBruto - prev.total_deducciones
        }));
    } catch (error) {
        console.error('Error calculando:', error);
    }
};
```

**Después:**
```ts
const calcularNominas = async () => {
    if (!data.periodo) {
        alert('Ingrese el período primero (ej: 2026-05)');
        return;
    }
    if ((data.fecha_inicio && !data.fecha_fin) || (!data.fecha_inicio && data.fecha_fin)) {
        alert('Debe especificar ambas fechas (inicio y fin) o ninguna para calcular el mes completo.');
        return;
    }
    try {
        let url = `/nominas/calcular?periodo=${data.periodo}`;
        if (data.fecha_inicio && data.fecha_fin) {
            url += `&fecha_inicio=${data.fecha_inicio}&fecha_fin=${data.fecha_fin}`;
        }
        const res = await fetch(url);
        const calculos = await res.json();
        const sumBruto = calculos.reduce((acc: number, curr: any) => acc + curr.sueldo_proporcional, 0);
        setData(prev => ({
            ...prev,
            detalles: calculos,
            total_bruto: sumBruto,
            total_neto: sumBruto - prev.total_deducciones
        }));
    } catch (error) {
        console.error('Error calculando:', error);
    }
};
```

## Verificación
- `vendor/bin/pint --format agent` (PHP)
- `npm run build` (JS)
- Probar: abrir modal, poner periodo `2026-05`, fechas `2026-05-01` a `2026-05-15`, calcular → debe contar solo 15 días
- Probar: calcular sin fechas → debe seguir calculando el mes completo
