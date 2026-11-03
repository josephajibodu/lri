<?php

namespace App\Payments;

use App\Models\PaymentChannel;
use App\Payments\Contracts\PaymentProvider;
use App\Payments\Providers\BachsProvider;
use Illuminate\Support\Manager;

/**
 * Hands out payment providers by name. Add a provider by adding a create{Name}Driver method and its config.
 *
 * @method PaymentProvider driver(?string $driver = null)
 */
class PaymentManager extends Manager
{
    /**
     * The provider that handles a payment channel.
     */
    public function forChannel(PaymentChannel $channel): PaymentProvider
    {
        return $this->driver($channel->provider);
    }

    /**
     * Get the default provider name.
     */
    public function getDefaultDriver(): string
    {
        return $this->config->get('payments.default');
    }

    protected function createBachsDriver(): BachsProvider
    {
        return new BachsProvider($this->config->get('payments.providers.bachs'));
    }
}
