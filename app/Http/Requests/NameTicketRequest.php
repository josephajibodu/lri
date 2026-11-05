<?php

namespace App\Http\Requests;

use App\Models\Ticket;
use App\Support\PersonName;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

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
            'name' => ['required', 'string', 'max:120', 'not_regex:'.PersonName::NotAllowed],
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
            'name.not_regex' => PersonName::NotAllowedMessage,
            'email.required' => 'Enter their email address. Their ticket goes there.',
            'email.email' => 'Enter an email address like name@example.com.',
        ];
    }

    /**
     * Keep each ticket's errors apart from the others, and put the name on one line.
     */
    protected function prepareForValidation(): void
    {
        $ticket = $this->route('ticket');
        $name = $this->input('name');

        $this->errorBag = $ticket instanceof Ticket ? 'ticket'.$ticket->id : 'default';
        $this->merge(['name' => is_string($name) ? Str::squish($name) : $name]);
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
