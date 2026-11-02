<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Validator;

class StoreBirthdayRequest extends FormRequest
{
    /**
     * The most photos one person can upload.
     */
    public const MaxPhotos = 3;

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
            'preferred_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('birthdays', 'email')],
            'phone' => ['nullable', 'string', 'max:50'],
            'instagram_handle' => ['nullable', 'string', 'max:31', 'regex:/^@?[A-Za-z0-9._]{1,30}$/'],
            'tribe' => ['nullable', Rule::in(config('lri.tribes'))],
            'birth_month' => ['required', 'integer', 'between:1,12'],
            'birth_day' => ['required', 'integer', 'between:1,31'],
            'birth_year' => ['nullable', 'integer', 'between:1900,'.now()->year],
            'notes' => ['nullable', 'string', 'max:2000'],
            'photos' => ['required', 'array', 'min:1', 'max:'.self::MaxPhotos],
            'photos.*' => [File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max('5mb')],
            'photo_consent' => ['accepted'],
        ];
    }

    /**
     * Store Instagram handles without the leading @.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('instagram_handle')) {
            $this->merge(['instagram_handle' => ltrim(trim($this->string('instagram_handle')->toString()), '@')]);
        }
    }

    /**
     * Check that the day actually exists in the chosen month (e.g. no 31 April).
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['birth_month', 'birth_day', 'birth_year'])) {
                    return;
                }

                $yearToCheck = $this->integer('birth_year') ?: 2000;

                if (! checkdate($this->integer('birth_month'), $this->integer('birth_day'), $yearToCheck)) {
                    $validator->errors()->add('birth_day', 'That day does not exist in the chosen month.');
                }
            },
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
            'instagram_handle.regex' => 'That doesn’t look like an Instagram handle. Use letters, numbers, dots and underscores.',
            'email.unique' => 'We already have a birthday saved for this email. Message an LRI organiser if it needs changing.',
            'photos.required' => 'Please add at least one photo.',
            'photos.max' => 'You can upload up to '.self::MaxPhotos.' photos.',
            'photos.*.image' => 'Each photo must be an image.',
            'photos.*.mimes' => 'Photos must be JPG, PNG or WebP.',
            'photos.*.max' => 'Each photo must be 5 MB or smaller.',
            'photo_consent.accepted' => 'Please confirm we can use your photos for the celebration.',
        ];
    }
}
