<?php

namespace App\Http\Middleware;

use App\Support\TrafficSource;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Give each browser a random visitor id, so the steps of one person's checkout can be tied together before they
 * give an email, and remember where they came from to credit their visit and order: a link's tags, an ad's click
 * id, the app whose browser opened the link, or the site that sent them. It runs on every public page, so someone who
 * lands on the home page from Instagram is still credited to Instagram when they reach the tickets. Neither cookie
 * holds anything about who the person is.
 */
class IdentifyVisitor
{
    public const VisitorCookie = 'lri_visitor';

    public const AttributionCookie = 'lri_attribution';

    /**
     * The link tags that say where a visit came from.
     *
     * @var list<string>
     */
    private const CampaignTags = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $visitorId = $request->cookie(self::VisitorCookie);

        if (! is_string($visitorId) || ! Str::isUuid($visitorId)) {
            $visitorId = (string) Str::uuid();
            Cookie::queue(self::VisitorCookie, $visitorId, 60 * 24 * 400);
        }

        $attribution = $this->attribution($request);

        $request->attributes->set('visitor_id', $visitorId);
        $request->attributes->set('attribution', $attribution);

        return $next($request);
    }

    /**
     * The visitor id this request carries, once the middleware has run.
     */
    public static function visitorId(Request $request): ?string
    {
        return $request->attributes->get('visitor_id');
    }

    /**
     * Where this visitor came from, once the middleware has run.
     *
     * @return array<string, string>|null
     */
    public static function attributionFor(Request $request): ?array
    {
        return $request->attributes->get('attribution');
    }

    /**
     * Where the visitor came from. A visit with campaign tags or an ad's click id replaces what was remembered (the
     * latest campaign gets the credit); an untagged visit from another site or an app's browser is only remembered
     * when nothing is yet.
     *
     * @return array<string, string>|null
     */
    private function attribution(Request $request): ?array
    {
        $remembered = json_decode((string) $request->cookie(self::AttributionCookie), true);
        $remembered = is_array($remembered) ? $remembered : null;

        $tags = collect(self::CampaignTags)
            ->mapWithKeys(fn (string $tag): array => [Str::after($tag, 'utm_') => $request->query($tag)])
            ->filter(fn (mixed $value): bool => is_string($value) && $value !== '')
            ->map(fn (string $value): string => Str::limit(strtolower(trim($value)), 100, ''));

        $referrer = parse_url((string) $request->headers->get('referer'), PHP_URL_HOST);
        $isExternal = is_string($referrer) && $referrer !== '' && $referrer !== $request->getHost();
        $cameThrough = TrafficSource::fromUserAgent($request->userAgent()) ?? ($isExternal ? TrafficSource::fromHost($referrer) : null);

        if (! $tags->has('source') && ($clicked = TrafficSource::fromClickIds($request->query(), $cameThrough)) !== null) {
            $tags = collect($clicked)->merge($tags);
        }

        if ($tags->isEmpty()) {
            if ($remembered !== null || ($cameThrough === null && ! $isExternal)) {
                return $remembered;
            }

            if ($cameThrough !== null) {
                $tags->put('source', $cameThrough->value);
            }
        }

        $attribution = $tags->merge(array_filter([
            'referrer' => $isExternal ? Str::limit($referrer, 100, '') : null,
            'landing' => Str::limit('/'.ltrim($request->path(), '/'), 100, ''),
            'at' => now()->toIso8601String(),
        ]))->all();

        Cookie::queue(self::AttributionCookie, json_encode($attribution), 60 * 24 * 30);

        return $attribution;
    }
}
