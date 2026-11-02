<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBarracksRegistrationRequest;
use App\Models\BarracksRegistration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BarracksRegistrationController extends Controller
{
    /**
     * List everyone who has registered, newest last, for the organisers.
     */
    public function index(): View
    {
        return view('barracks.index', [
            'registrations' => BarracksRegistration::query()->oldest()->get(),
        ]);
    }

    /**
     * Show the Akobo Barracks permit form.
     */
    public function create(Request $request): View
    {
        $pendingPassport = $request->old('passport_upload');
        $pendingPassportPath = BarracksRegistration::PendingPassportDirectory.'/'.$pendingPassport;
        $hasPendingPassport = is_string($pendingPassport)
            && preg_match(BarracksRegistration::PendingPassportPattern, $pendingPassport)
            && Storage::exists($pendingPassportPath);

        return view('barracks.create', [
            'details' => config('lri.barracks_registration'),
            'pendingPassport' => $hasPendingPassport ? $pendingPassport : null,
            'pendingPassportUrl' => $hasPendingPassport ? Storage::temporaryUrl($pendingPassportPath, now()->addHour()) : null,
        ]);
    }

    /**
     * Upload a passport photograph on its own, before the form is submitted, so the page can show progress.
     */
    public function uploadPassport(Request $request): JsonResponse
    {
        $request->validate(
            ['passport' => ['required', ...StoreBarracksRegistrationRequest::passportRules()]],
            (new StoreBarracksRegistrationRequest)->messages(),
        );

        $passport = $request->file('passport');
        $fileName = Str::uuid().'.'.$passport->extension();
        $passport->storeAs(BarracksRegistration::PendingPassportDirectory, $fileName);

        return response()->json([
            'passport_upload' => $fileName,
            'preview_url' => Storage::temporaryUrl(BarracksRegistration::PendingPassportDirectory.'/'.$fileName, now()->addHour()),
        ]);
    }

    /**
     * Save a filled permit form along with its passport photograph.
     */
    public function store(StoreBarracksRegistrationRequest $request): RedirectResponse
    {
        if ($request->filled('passport_upload')) {
            $passportPath = BarracksRegistration::PassportDirectory.'/'.$request->validated('passport_upload');
            Storage::move(BarracksRegistration::PendingPassportDirectory.'/'.$request->validated('passport_upload'), $passportPath);
        } else {
            $passportPath = $request->file('passport')->store(BarracksRegistration::PassportDirectory);
        }

        BarracksRegistration::create([
            ...$request->safe()->except(['passport', 'passport_upload', 'declaration']),
            'passport_path' => $passportPath,
            'declared_at' => now(),
        ]);

        return redirect()
            ->route('barracks.create')
            ->with('status', 'Thank you! You’re registered. The organisers will print your form and take it to the barracks.');
    }

    /**
     * Show the permit forms laid out to print, one per page. Prints everyone, or one person when given.
     */
    public function print(?BarracksRegistration $registration = null): View
    {
        return view('barracks.print', [
            'registrations' => $registration ? collect([$registration]) : BarracksRegistration::query()->oldest()->get(),
            'details' => config('lri.barracks_registration'),
        ]);
    }
}
