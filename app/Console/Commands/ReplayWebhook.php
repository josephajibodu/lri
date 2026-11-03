<?php

namespace App\Console\Commands;

use App\Actions\Checkout\HandleWebhookCall;
use App\Models\WebhookCall;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('payments:replay-webhook {call : The id of the stored webhook call}')]
#[Description('Handle a stored payment webhook again, such as one that failed before a fix')]
class ReplayWebhook extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(HandleWebhookCall $handleWebhookCall): int
    {
        $call = WebhookCall::query()->findOrFail($this->argument('call'));

        try {
            $handleWebhookCall->handle($call);
        } catch (Throwable $exception) {
            $this->error("Webhook {$call->id} ({$call->type}) failed again: {$exception->getMessage()}");

            return self::FAILURE;
        }

        $this->info("Handled webhook {$call->id} ({$call->type}).".($call->error ? " Note: {$call->error}" : ''));

        return self::SUCCESS;
    }
}
