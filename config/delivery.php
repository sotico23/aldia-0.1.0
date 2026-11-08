<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tarifas de reparto
    |--------------------------------------------------------------------------
    |
    | Se usan como respaldo cuando un pedido no trae el costo de envío
    | explícito (por ejemplo, pedidos creados antes de habilitar el pool).
    | El repartidor recibe su comisión (`comision_pct`) sobre este monto.
    |
    */

    'costo_envio_default' => (float) env('DELIVERY_COSTO_ENVIO_DEFAULT', 2500),

    'costo_por_km' => (float) env('DELIVERY_COSTO_POR_KM', 350),

    /*
    |--------------------------------------------------------------------------
    | Ofertas de pedido (modelo Uber/Rappi)
    |--------------------------------------------------------------------------
    */

    'offer_ttl_seconds' => (int) env('DELIVERY_OFFER_TTL_SECONDS', 45),

    'offers_por_solicitud' => (int) env('DELIVERY_OFFERS_POR_SOLICITUD', 3),

    /*
    |--------------------------------------------------------------------------
    | Rol por defecto de repartidores
    |--------------------------------------------------------------------------
    |
    | Rol Spatie que se asigna automáticamente a todo repartidor creado desde
    | el panel de una empresa/tienda. No debe pedirse en el formulario.
    |
    */

    'default_role' => env('DELIVERY_DEFAULT_ROLE', 'Repartidor'),

];
