<?php

namespace App\Payments;

use App\Models\PaymentChannel;
use App\Payments\Contracts\PaymentProvider;
use App\Payments\Providers\BachsProvider;
use App\Payments\Providers\FakeProvider;
use Illuminate\Support\Manager;
use LogicException;

/**
 * Hands out payment providers by name. Add a provider by adding a create{Name}Driver method and its config.
 *
 * @method PaymentProvider driver(?string $driver = null)
 */
class PaymentManager extends Manager
{
    /**
     * The provider that handles a payment channel, or the fake one when fake payments are switched on.
     */
    public function forChannel(PaymentChannel $channel): PaymentProvider
    {
        return $this->provider($channel->provider);
    }

    /**
     * The provider with the given name, or the fake one when fake payments are switched on.
     */
    public function provider(?string $name = null): PaymentProvider
    {
        return $this->driver($this->usesFakePayments() ? 'fake' : $name);
    }

    /**
     * Get the default provider name.
     */
    public function getDefaultDriver(): string
    {
        return $this->config->get('payments.default');
    }

    /**
     * Whether every channel goes through the fake provider. Never in production.
     */
    public function usesFakePayments(): bool
    {
        return $this->config->get('payments.fake') && ! $this->container->isProduction();
    }

    protected function createBachsDriver(): BachsProvider
    {
        return new BachsProvider($this->config->get('payments.providers.bachs'));
    }

    protected function createFakeDriver(): FakeProvider
    {
        if ($this->container->isProduction()) {
            throw new LogicException('The fake payment provider cannot be used in production.');
        }

        return new FakeProvider($this->config->get('payments.providers.fake'), $this->container->make('cache.store'));
    }
}
