<?php

namespace App\Http\Requests;

use App\Actions\Checkout\RecordCheckoutStep;
use App\Enums\CheckoutStep;
use App\Http\Middleware\IdentifyVisitor;
use App\Models\TicketType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
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
        $ticketType = TicketType::query()->where('slug', $this->input('ticket_type'))->first();

        return [
            'ticket_type' => ['required', 'string', Rule::exists('ticket_types', 'slug')],
            'quantity' => ['required', 'integer', 'min:1', 'max:'.($ticketType?->max_per_order ?? 10)],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'phone' => ['nullable', 'string', 'regex:/^\+\d{10,15}$/'],
            'marketing_consent' => ['boolean'],
            'coupon' => ['nullable', 'string', 'max:40'],
            'channel' => ['required', 'integer', Rule::exists('payment_channels', 'id')->where('is_enabled', true)],
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
            'quantity.max' => 'You can buy up to :max tickets in one order.',
            'email.email' => 'Enter an email address like name@example.com. Your ticket goes there.',
            'phone.regex' => 'Enter a phone number like 0803 123 4567.',
            'coupon.max' => 'We can’t find that coupon. Check the spelling and try again.',
            'channel.required' => 'Choose how you’d like to pay.',
            'channel.exists' => 'That way to pay isn’t available right now. Choose another one.',
        ];
    }

    /**
     * Note which fields people stumble on (never what they typed), then send them back to fix it.
     */
    protected function failedValidation(Validator $validator): void
    {
        app(RecordCheckoutStep::class)->handle(CheckoutStep::FormInvalid, IdentifyVisitor::visitorId($this), properties: [
            'fields' => array_keys($validator->errors()->messages()),
        ]);

        parent::failedValidation($validator);
    }

    /**
     * Send people back to the ticket form, not the top of the page, when something needs fixing.
     */
    protected function getRedirectUrl(): string
    {
        return strtok(url()->previous(), '#').'#tickets';
    }

    /**
     * Store Nigerian numbers the international way (0803… becomes +234803…), so providers and messaging tools accept them.
     */
    protected function prepareForValidation(): void
    {
        $phone = preg_replace('/[\s().-]/', '', (string) $this->input('phone'));

        $this->merge([
            'email' => strtolower(trim((string) $this->input('email'))),
            'phone' => match (true) {
                $phone === '' => null,
                (bool) preg_match('/^0\d{10}$/', $phone) => '+234'.substr($phone, 1),
                (bool) preg_match('/^234\d{10}$/', $phone) => '+'.$phone,
                default => $phone,
            },
            'marketing_consent' => $this->boolean('marketing_consent'),
        ]);
    }
}
