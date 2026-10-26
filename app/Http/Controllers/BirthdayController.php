<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBirthdayRequest;
use App\Models\Birthday;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class BirthdayController extends Controller
{
    /**
     * List the birthdays in a month so they can be celebrated. Defaults to next month.
     */
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'month' => ['nullable', 'integer', 'between:1,12'],
        ]);

        $nextMonth = now()->startOfMonth()->addMonth();
        $month = (int) ($validated['month'] ?? $nextMonth->month);
        $year = $month < now()->month ? now()->year + 1 : now()->year;

        return view('birthdays.index', [
            'birthdays' => Birthday::query()->inMonth($month)->get(),
            'month' => $month,
            'year' => $year,
            'monthName' => Carbon::create($year, $month)->format('F'),
            'months' => $this->monthNames(),
        ]);
    }

    /**
     * Show the form for sharing a birthday.
     */
    public function create(): View
    {
        return view('birthdays.create', [
            'maxPhotos' => StoreBirthdayRequest::MaxPhotos,
            'months' => $this->monthNames(),
            'tribes' => config('lri.tribes'),
        ]);
    }

    /**
     * Save a submitted birthday along with its photos.
     */
    public function store(StoreBirthdayRequest $request): RedirectResponse
    {
        $photoPaths = collect($request->file('photos'))
            ->map(fn (UploadedFile $photo): string => $photo->store('birthday-photos'))
            ->all();

        Birthday::create([
            ...$request->safe()->except(['photos', 'photo_consent']),
            'photo_paths' => $photoPaths,
            'photo_consent' => $request->boolean('photo_consent'),
        ]);

        return redirect()
            ->route('birthdays.create')
            ->with('status', 'Thank you! Your birthday has been saved.');
    }

    /**
     * Month names keyed by month number, January to December.
     *
     * @return array<int, string>
     */
    private function monthNames(): array
    {
        return collect(range(1, 12))
            ->mapWithKeys(fn (int $month): array => [$month => Carbon::create(2000, $month)->format('F')])
            ->all();
    }
}
