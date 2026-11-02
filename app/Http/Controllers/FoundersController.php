<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;
use Illuminate\View\View;

class FoundersController extends Controller
{
    /**
     * Accent colours handed to the founders in turn, matching the site's tile colours.
     *
     * @var list<string>
     */
    private const TONES = ['yellow', 'green', 'sky', 'coral', 'pink'];

    /**
     * Show the people who started the club and the story so far.
     */
    public function __invoke(): View
    {
        $founders = config('lri.founders');

        $people = collect($founders['people'])->values()->map(fn (array $person, int $index): array => [
            ...$person,
            'bib' => Str::padLeft((string) ($index + 1), 2, '0'),
            'tone' => self::TONES[$index % count(self::TONES)],
            'anchor' => 'founder-'.Str::slug($person['name']),
            'initials' => Str::upper(Str::of($person['name'])->explode(' ')->take(2)->map(fn (string $word): string => Str::substr($word, 0, 1))->implode('')),
        ]);

        return view('founders', [
            'founded' => $founders['founded'],
            'people' => $people,
            'milestones' => $founders['milestones'],
        ]);
    }
}
