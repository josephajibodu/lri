<?php

namespace App\Jobs;

use App\EmailTools\EmailToolManager;
use App\EmailTools\Exceptions\EmailToolException;
use App\Enums\EmailToolSendStatus;
use App\Models\EmailToolSend;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Tell the email tool one piece of news. It runs on the queue, so a slow tool never holds up checkout. An outage is
 * retried; a refusal fails straight away, because it would only fail the same way again.
 */
class SendToEmailTool implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 5;

    /**
     * Seconds to wait before each retry.
     *
     * @var list<int>
     */
    public array $backoff = [60, 300, 900, 3600];

    /**
     * Create a new job instance.
     */
    public function __construct(public EmailToolSend $send) {}

    /**
     * Execute the job.
     *
     * @throws EmailToolException When the tool is worth trying again later
     */
    public function handle(EmailToolManager $tools): void
    {
        if ($this->send->status === EmailToolSendStatus::Sent) {
            return;
        }

        $order = $this->send->order;

        if (! $this->send->event->stillAppliesTo($order)) {
            $this->send->update(['status' => EmailToolSendStatus::Skipped, 'error' => "The order is now {$order->status->value}, so this no longer applies."]);

            return;
        }

        $this->send->increment('attempts');

        try {
            $tools->driver($this->send->tool)->send($this->send->event, $order);
        } catch (EmailToolException $exception) {
            if (! $exception->isRetryable) {
                $this->fail($exception);

                return;
            }

            $this->send->update(['error' => $exception->getMessage()]);

            throw $exception;
        }

        $this->send->update(['status' => EmailToolSendStatus::Sent, 'sent_at' => now(), 'failed_at' => null, 'error' => null]);
    }

    /**
     * Note the failure for the admin once the job has given up.
     */
    public function failed(?Throwable $exception): void
    {
        $this->send->update([
            'status' => EmailToolSendStatus::Failed,
            'failed_at' => now(),
            'error' => $exception?->getMessage(),
        ]);
    }
}
