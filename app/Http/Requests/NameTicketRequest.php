<?php

namespace App\Http\Requests;

use App\Models\Ticket;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The name and email for one ticket. Errors go in a bag of the ticket's own, so on a receipt with several tickets
 * they show under the right one.
 */
class NameTicketRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190'],
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
            'name.required' => 'Enter the name of the person this ticket is for.',
            'email.required' => 'Enter their email address. Their ticket goes there.',
            'email.email' => 'Enter an email address like name@example.com.',
        ];
    }

    /**
     * Keep each ticket's errors apart from the others.
     */
    protected function prepareForValidation(): void
    {
        $ticket = $this->route('ticket');

        $this->errorBag = $ticket instanceof Ticket ? 'ticket'.$ticket->id : 'default';
    }

    /**
     * Send people back to the ticket they were filling in.
     */
    protected function getRedirectUrl(): string
    {
        $ticket = $this->route('ticket');

        return strtok(url()->previous(), '#').($ticket instanceof Ticket ? '#ticket-'.$ticket->id : '');
    }
}
