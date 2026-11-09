<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('with no number set there is no chat button', function () {
    config()->set('lri.whatsapp_chat.number', '');

    $this->get(route('home'))->assertOk()->assertDontSee('data-wa-chat', false)->assertDontSee('wa.me/', false);
});

test('with a number set, public pages carry a button that opens a WhatsApp chat with the message filled in', function (string $page) {
    config()->set('lri.whatsapp_chat.number', '2348031234567');
    config()->set('lri.whatsapp_chat.message', 'Hi, I need help & more');
    config()->set('lri.whatsapp_chat.note_after_seconds', 5);

    $this->get(route($page))
        ->assertOk()
        ->assertSee('href="https://wa.me/2348031234567?text=Hi%2C%20I%20need%20help%20%26%20more"', false)
        ->assertSee('data-note-after="5"', false)
        ->assertSeeText('Chat with us on WhatsApp');
})->with(['home', 'disco-run']);

test('the number keeps only its digits, so it can be written with a plus and spaces', function () {
    $_ENV['WHATSAPP_CHAT_NUMBER'] = $_SERVER['WHATSAPP_CHAT_NUMBER'] = '+234 803 123 4567';

    try {
        expect((require config_path('lri.php'))['whatsapp_chat']['number'])->toBe('2348031234567');
    } finally {
        $_ENV['WHATSAPP_CHAT_NUMBER'] = $_SERVER['WHATSAPP_CHAT_NUMBER'] = '';
    }
});

test('the email previews have no chat button', function () {
    config()->set('lri.whatsapp_chat.number', '2348031234567');

    $this->get(route('mail-previews.index'))->assertDontSee('data-wa-chat', false);
});
