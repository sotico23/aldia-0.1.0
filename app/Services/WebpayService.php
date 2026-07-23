<?php

namespace App\Services;

use App\Models\PaymentConfig;
use Transbank\Webpay\WebpayPlus;
use Transbank\Webpay\WebpayPlus\Transaction;

class WebpayService
{
    public function getCredentials($ownerId)
    {
        $config = PaymentConfig::resolveForOwner($ownerId);
        if (! $config || ! $config->is_active) {
            throw new \Exception('No hay configuración de pago activa para este usuario o negocio.');
        }

        if ($config->environment === 'production') {
            WebpayPlus::configureForProduction($config->commerce_code, $config->api_key);
        } else {
            // Integration Environment parameters
            WebpayPlus::configureForIntegration($config->commerce_code, $config->api_key);
        }

        return $config;
    }

    public function createTransaction($buyOrder, $sessionId, $amount, $returnUrl, $ownerId)
    {
        $this->getCredentials($ownerId);

        $transaction = new Transaction;
        $response = $transaction->create($buyOrder, $sessionId, $amount, $returnUrl);

        return $response; // Contains $response->getUrl() and $response->getToken()
    }

    public function confirmTransaction($token, $ownerId)
    {
        $this->getCredentials($ownerId);

        $transaction = new Transaction;
        $response = $transaction->commit($token);

        return $response;
    }
}
