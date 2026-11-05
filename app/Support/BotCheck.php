<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cloudflare Turnstile, which checks that an order comes from a real browser and not a script reserving tickets in bulk.
 * It's off until its keys are set. Someone who fails the check is refused, but if Cloudflare's server can't give an answer,
 * or our own key is wrong, the order goes through and the problem is reported: an outage elsewhere shouldn't stop ticket
 * sales, and nothing a visitor sends can cause either one. A token that never arrives is always refused, even if it's
 * because the check couldn't load in the buyer's browser, since a script skipping the check looks exactly the same.
 */
final class BotCheck
{
    private const VerifyUrl = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    /**
     * The longest token Turnstile issues.
     */
    private const LongestToken = 2048;

    /**
     * Cloudflare's answers that say nothing about the visitor.
     *
     * @var list<string>
     */
    private const NotTheVisitorsFault = ['missing-input-secret', 'invalid-input-secret', 'internal-error'];

    public static function isOn(): bool
    {
        return filled(config('services.turnstile.site_key')) && filled(config('services.turnstile.secret_key'));
    }

    public static function siteKey(): ?string
    {
        return config('services.turnstile.site_key');
    }

    /**
     * Whether this token from the page's widget shows a real browser. A token only works once, so this should only be
     * asked once the rest of the form is fine, or fixing a typo would need a new one.
     */
    public static function passes(mixed $token, ?string $ipAddress): bool
    {
        if (! self::isOn()) {
            return true;
        }

        if (! is_string($token) || $token === '' || strlen($token) > self::LongestToken) {
            return false;
        }

        try {
            $response = Http::asForm()->connectTimeout(3)->timeout(5)->post(self::VerifyUrl, [
                'secret' => config('services.turnstile.secret_key'),
                'response' => $token,
                'remoteip' => $ipAddress,
            ]);
        } catch (ConnectionException $exception) {
            report($exception);

            return true;
        }

        $problems = array_intersect((array) $response->json('error-codes'), self::NotTheVisitorsFault);

        if ($response->serverError() || $problems !== []) {
            report(new RuntimeException('The bot check let an order through without an answer: '.($problems === [] ? 'Cloudflare returned '.$response->status() : implode(', ', $problems))));

            return true;
        }

        return $response->json('success') === true;
    }
}
