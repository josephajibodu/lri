<?php

namespace App\Payments\Exceptions;

use Illuminate\Http\Client\Response;
use RuntimeException;
use Throwable;

/**
 * A provider refused a request or could not be reached. The error code is the provider's own, for the logs.
 */
class PaymentProviderException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $provider,
        public readonly ?string $errorCode = null,
        public readonly ?int $httpStatus = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * An error from a provider's failed response, using the detail and error code it sent back.
     */
    public static function fromResponse(string $provider, Response $response, ?Throwable $previous = null): self
    {
        $detail = $response->json('detail') ?? $response->json('message') ?? 'No detail given';

        return new self(
            "{$provider} returned {$response->status()}: {$detail}",
            $provider,
            $response->json('error_code'),
            $response->status(),
            $previous,
        );
    }

    /**
     * Context for the log entry when this is reported.
     *
     * @return array{provider: string, error_code: ?string, http_status: ?int}
     */
    public function context(): array
    {
        return [
            'provider' => $this->provider,
            'error_code' => $this->errorCode,
            'http_status' => $this->httpStatus,
        ];
    }
}
