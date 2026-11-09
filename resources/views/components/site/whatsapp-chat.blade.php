@php($chat = config('lri.whatsapp_chat'))

@if (filled($chat['number']))
    <aside class="wa-chat" aria-label="Chat with us" data-wa-chat data-note-after="{{ (int) $chat['note_after_seconds'] }}">
        <div class="wa-chat-note" id="wa-chat-note" data-wa-chat-note hidden>
            <button type="button" class="wa-chat-close" data-wa-chat-close aria-label="Close this note">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
            </button>
            <p class="wa-chat-note-title">Need a hand?</p>
            <p class="wa-chat-note-text">Questions about tickets, payments or runs? Chat with us on WhatsApp.</p>
        </div>

        <a class="wa-chat-btn" href="https://wa.me/{{ $chat['number'] }}?text={{ rawurlencode($chat['message']) }}" target="_blank" rel="noopener" aria-describedby="wa-chat-note" data-wa-chat-link>
            <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M20.5 11.6a8.5 8.5 0 0 1-12.5 7.5L3.5 20.5l1.4-4.3a8.5 8.5 0 1 1 15.6-4.6Z"/>
                <path d="M8.5 11.6h.01M12 11.6h.01M15.5 11.6h.01" stroke-width="2.6"/>
            </svg>
            <span class="sr-only">Chat with us on WhatsApp (opens WhatsApp)</span>
        </a>
    </aside>
@endif
