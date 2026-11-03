<?php

namespace App\EmailTools\Exceptions;

use Illuminate\Http\Client\Response;
use RuntimeException;
use Throwable;

/**
 * An email tool refused a request or could not be reached. A retryable one is worth trying again later,
 * such as an outage; the rest will fail the same way until someone fixes something.
 */
class EmailToolException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $tool,
        public readonly bool $isRetryable = false,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * An error from a tool's failed response, using the detail it sent back. Rate limits and the tool's own errors can be retried.
     */
    public static function fromResponse(string $tool, Response $response): self
    {
        $detail = $response->json('detail') ?? 'No detail given';
        $problems = collect($response->json('errors', []))->pluck('message')->filter()->implode(' ');

        return new self(
            trim("{$tool} returned {$response->status()}: {$detail} {$problems}"),
            $tool,
            $response->status() === 429 || $response->serverError(),
        );
    }
}
