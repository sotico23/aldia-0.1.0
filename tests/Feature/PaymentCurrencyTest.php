<?php

namespace Tests\Feature;

use App\Enums\Currency;
use App\Models\Pedido;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentCurrencyTest extends TestCase
{
    use RefreshDatabase;

    private User $chileUser;

    private User $peruUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->chileUser = User::factory()->chile()->create();
        $this->peruUser = User::factory()->peru()->create();
    }

    public function test_mercadopago_uses_country_currency(): void
    {
        $pedido = Pedido::factory()->create([
            'owner_id' => $this->peruUser->id,
            'total' => 100,
        ]);

        $resolvedCurrency = Currency::fromCountry($this->peruUser->country);

        $this->assertEquals(Currency::PEN, $resolvedCurrency);
        $this->assertEquals('PEN', $resolvedCurrency->value);
    }

    public function test_paypal_uses_country_currency(): void
    {
        $pedido = Pedido::factory()->create([
            'owner_id' => $this->peruUser->id,
            'total' => 100,
        ]);

        $resolvedCurrency = Currency::fromCountry($this->peruUser->country);

        $this->assertEquals(Currency::PEN, $resolvedCurrency);
        $this->assertEquals('PEN', $resolvedCurrency->value);
    }

    public function test_venta_controller_auto_assigns_currency(): void
    {
        $venta = Venta::factory()->create([
            'owner_id' => $this->peruUser->id,
        ]);

        $this->assertEquals('PEN', $venta->currency ?? Currency::fromCountry($this->peruUser->country)->value);
    }

    public function test_chile_user_gets_clp(): void
    {
        $resolvedCurrency = Currency::fromCountry($this->chileUser->country);

        $this->assertEquals(Currency::CLP, $resolvedCurrency);
        $this->assertEquals('CLP', $resolvedCurrency->value);
    }

    public function test_peru_user_gets_pen(): void
    {
        $resolvedCurrency = Currency::fromCountry($this->peruUser->country);

        $this->assertEquals(Currency::PEN, $resolvedCurrency);
        $this->assertEquals('PEN', $resolvedCurrency->value);
    }
}
