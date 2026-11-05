<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The name and password someone chooses when they accept an invitation. Their email address comes from the
 * invitation, so it isn't asked for here and can't be changed.
 */
class AcceptAdminInvitationRequest extends FormRequest
{
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
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
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
            'name.required' => 'Enter your full name.',
            'password.required' => 'Choose a password.',
            'password.min' => 'Use at least 12 characters.',
            'password.confirmed' => 'The two passwords don’t match.',
        ];
    }

    /**
     * Send people back to the form they were filling in.
     */
    protected function getRedirectUrl(): string
    {
        return route('invitations.show', $this->route('token'));
    }
}
