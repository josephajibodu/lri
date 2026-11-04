{{-- The name and email form for one ticket on the receipt: "Type details" for a blank ticket, "Edit" for a named one. --}}
<details class="dr-order-ticket-name" @if ($ticketErrors->hasAny(['name', 'email'])) open @endif>
    <summary>{{ $summary }}</summary>
    <form method="POST" action="{{ route('receipts.tickets.update', [$order->receipt_token, $ticket]) }}">
        @csrf
        @method('PUT')
        <div class="dr-field">
            <label for="name-{{ $ticket->id }}">Their name</label>
            <input class="dr-input" id="name-{{ $ticket->id }}" name="name" type="text" maxlength="120" required autocomplete="off" value="{{ $ticketErrors->isNotEmpty() ? old('name') : $ticket->holder_name }}" @if ($ticketErrors->has('name')) aria-invalid="true" @endif>
            <p class="dr-field-error">{{ $ticketErrors->first('name') }}</p>
        </div>
        <div class="dr-field">
            <label for="email-{{ $ticket->id }}">Their email</label>
            <input class="dr-input" id="email-{{ $ticket->id }}" name="email" type="email" maxlength="190" required autocomplete="off" inputmode="email" value="{{ $ticketErrors->isNotEmpty() ? old('email') : $ticket->holder_email }}" @if ($ticketErrors->has('email')) aria-invalid="true" @endif>
            <p class="dr-field-error">{{ $ticketErrors->first('email') }}</p>
        </div>
        <button class="btn btn-sm dr-btn" type="submit">{{ $button }}</button>
    </form>
</details>
