<?php

namespace App\Http\Requests;

use App\Models\BarracksRegistration;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class StoreBarracksRegistrationRequest extends FormRequest
{
    /**
     * The rules a passport photograph must pass, whether uploaded on its own or with the form.
     *
     * @return array<int, mixed>
     */
    public static function passportRules(): array
    {
        return [File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max('5mb')];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'state_of_origin' => ['required', 'string', 'max:100'],
            'lga' => ['required', 'string', 'max:100'],
            'residential_address' => ['required', 'string', 'max:255'],
            'occupation' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50', 'regex:/^\+?[0-9 ()-]{7,}$/', Rule::unique('barracks_registrations', 'phone')],
            'passport' => ['required_without:passport_upload', 'nullable', ...self::passportRules()],
            'passport_upload' => [
                'nullable',
                'string',
                'regex:'.BarracksRegistration::PendingPassportPattern,
                function (string $attribute, string $value, Closure $fail): void {
                    if (! Storage::exists(BarracksRegistration::PendingPassportDirectory.'/'.$value)) {
                        $fail('Your passport photograph has expired. Please upload it again.');
                    }
                },
            ],
            'signature_name' => ['required', 'string', 'max:255'],
            'declaration' => ['accepted'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'That doesn’t look like a phone number.',
            'phone.unique' => 'Someone has already registered with this phone number. Message an LRI organiser if it needs changing.',
            'passport.required_without' => 'Please add a passport photograph.',
            'passport_upload.regex' => 'Your passport photograph has expired. Please upload it again.',
            'passport.image' => 'The passport photograph must be an image.',
            'passport.mimes' => 'The passport photograph must be JPG, PNG or WebP.',
            'passport.max' => 'The passport photograph must be 5 MB or smaller.',
            'signature_name.required' => 'Type your full name to sign the form.',
            'declaration.accepted' => 'Please accept the declaration.',
        ];
    }
}
