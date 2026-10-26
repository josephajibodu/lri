<?php

namespace App\Http\Controllers;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PaceSettersController extends Controller
{
    /**
     * The group for leaders of the whole club rather than one tribe.
     */
    public const CLUB_GROUP = 'Club-wide';

    /**
     * Accent colours handed to the pace setters in turn, matching the site's tile colours.
     *
     * @var list<string>
     */
    private const TONES = ['yellow', 'green', 'sky', 'coral', 'pink'];

    /**
     * Show the club's leaders, grouped into club-wide leaders and each tribe.
     */
    public function __invoke(): View
    {
        $paceSetters = config('lri.pace_setters');

        $people = collect($paceSetters['people'])->values()->map(fn (array $person, int $index): array => [
            ...$person,
            'bib' => Str::padLeft((string) ($index + 1), 2, '0'),
            'tone' => self::TONES[$index % count(self::TONES)],
            'anchor' => 'pace-setter-'.Str::slug($person['name']),
            'initials' => Str::upper(Str::of($person['name'])->explode(' ')->take(2)->map(fn (string $word): string => Str::substr($word, 0, 1))->implode('')),
            'instagram_url' => $person['instagram'] ? 'https://www.instagram.com/'.$person['instagram'].'/' : null,
        ]);

        return view('pace-setters', [
            'people' => $people,
            'groups' => $this->groups($people, $paceSetters['groups'] ?? []),
        ]);
    }

    /**
     * Put the people into lanes: club-wide leaders first, then each tribe in order, skipping empty groups.
     *
     * @param  Collection<int, array<string, mixed>>  $people
     * @param  array<string, string>  $lines
     * @return Collection<int, array{name: string, slug: string, lane: string, line: ?string, people: Collection<int, array<string, mixed>>}>
     */
    private function groups(Collection $people, array $lines): Collection
    {
        return collect([self::CLUB_GROUP, ...config('lri.tribes')])
            ->map(fn (string $name): array => [
                'name' => $name,
                'slug' => Str::slug($name),
                'line' => $lines[$name] ?? null,
                'people' => $people->where('group', $name)->values(),
            ])
            ->filter(fn (array $group): bool => $group['people']->isNotEmpty())
            ->values()
            ->map(fn (array $group, int $index): array => [
                ...$group,
                'lane' => Str::padLeft((string) ($index + 1), 2, '0'),
            ]);
    }
}
