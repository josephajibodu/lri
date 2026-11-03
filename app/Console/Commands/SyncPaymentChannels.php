<?php

namespace App\Console\Commands;

use App\Enums\PaymentMethod;
use App\Models\PaymentChannel;
use App\Payments\Exceptions\PaymentProviderException;
use App\Payments\PaymentManager;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('payments:sync-channels {currency=NGN : The currency to sync} {--provider= : The provider to ask, instead of the default}')]
#[Description('Match the payment channels to the methods switched on for our account at the provider')]
class SyncPaymentChannels extends Command
{
    /**
     * Ask the provider which methods are switched on for our account, add channels for new ones, and switch off
     * channels the provider no longer allows. Channels an admin switched off stay off, and channels handled by
     * another provider are left alone.
     */
    public function handle(PaymentManager $payments): int
    {
        $currency = strtoupper($this->argument('currency'));
        $provider = $payments->driver($this->option('provider') ?: null);

        try {
            $enabledMethods = $provider->enabledMethods($currency);
        } catch (PaymentProviderException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $rows = [];

        foreach ($provider->supportedMethods($currency) as $method) {
            $channel = PaymentChannel::query()->where('method', $method)->where('currency', $currency)->first();
            $isEnabledAtProvider = in_array($method, $enabledMethods, true);

            $action = match (true) {
                $channel && $channel->provider !== $provider->name() => "Left alone: handled by {$channel->provider}",
                $channel && $channel->is_enabled && ! $isEnabledAtProvider => $this->switchOff($channel),
                $channel && ! $channel->is_enabled && $isEnabledAtProvider => 'Left off: switched off on the site',
                $channel !== null => 'No change',
                $isEnabledAtProvider => $this->add($provider->name(), $method, $currency),
                default => 'Nothing to do: not switched on for our account',
            };

            $rows[] = [$method->label(), $isEnabledAtProvider ? 'On' : 'Off', $action];
        }

        $this->table(['Method', ucfirst($provider->name()).' account', 'Site'], $rows);

        return self::SUCCESS;
    }

    private function switchOff(PaymentChannel $channel): string
    {
        $channel->update(['is_enabled' => false, 'is_featured' => false]);

        return 'Switched off: no longer allowed for our account';
    }

    private function add(string $provider, PaymentMethod $method, string $currency): string
    {
        PaymentChannel::query()->create([
            'method' => $method,
            'currency' => $currency,
            'provider' => $provider,
            'is_enabled' => true,
            'is_featured' => false,
            'sort_order' => (int) PaymentChannel::query()->where('currency', $currency)->max('sort_order') + 1,
        ]);

        return 'Added';
    }
}
