<?php

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function paceSetter(array $overrides = []): array
{
    return [
        'name' => 'Adebola Okafor',
        'role' => 'Tribe lead',
        'group' => 'Bodija',
        'bio' => 'Keeps the back of the pack company.',
        'photo' => null,
        'instagram' => null,
        ...$overrides,
    ];
}

test('the pace setters page groups leaders into club-wide first and then each tribe in order', function () {
    config()->set('lri.tribes', ['Akobo', 'Bodija', 'UI / Eleyele', 'Oluyole / Akala']);
    config()->set('lri.pace_setters.people', [
        paceSetter(['name' => 'Tunde Bakare', 'group' => 'Bodija']),
        paceSetter(['name' => 'Kemi Adeyemi', 'role' => 'Club captain', 'group' => 'Club-wide']),
        paceSetter(['name' => 'Bisi Ola', 'role' => 'Pacer', 'group' => 'Akobo']),
        paceSetter(['name' => 'Seyi Lawal', 'role' => 'Sweeper', 'group' => 'Bodija']),
    ]);

    $this->get(route('pace-setters'))
        ->assertOk()
        ->assertSee('<title>Pace Setters · Let’s Run Ibadan</title>', false)
        ->assertSee('<link rel="canonical" href="'.route('pace-setters').'">', false)
        ->assertSeeInOrder([
            'id="lane-club-wide"', 'id="pace-setter-kemi-adeyemi"',
            'id="lane-akobo"', 'id="pace-setter-bisi-ola"',
            'id="lane-bodija"', 'id="pace-setter-tunde-bakare"', 'id="pace-setter-seyi-lawal"',
        ], false)
        ->assertSeeText('Club captain')
        ->assertSeeText('Keeps the back of the pack company.')
        ->assertSeeText('4 pace setters')
        ->assertSeeText('2 pace setters');
});

test('a group with no pace setters is left off the page', function () {
    config()->set('lri.pace_setters.people', [
        paceSetter(['group' => 'Akobo']),
    ]);

    $this->get(route('pace-setters'))
        ->assertOk()
        ->assertSee('id="lane-akobo"', false)
        ->assertSee('data-lane="akobo"', false)
        ->assertDontSee('id="lane-club-wide"', false)
        ->assertDontSee('id="lane-bodija"', false)
        ->assertDontSee('data-lane="oluyole-akala"', false);
});

test('each group shows its line when one is configured', function () {
    config()->set('lri.pace_setters.groups', ['Bodija' => 'Loops past Bodija Market.']);
    config()->set('lri.pace_setters.people', [
        paceSetter(),
        paceSetter(['name' => 'Bisi Ola', 'group' => 'Akobo']),
    ]);

    $response = $this->get(route('pace-setters'))
        ->assertOk()
        ->assertSeeText('Loops past Bodija Market.');

    expect(substr_count($response->getContent(), 'class="ps-group-line"'))->toBe(1);
});

test('a pace setter shows their photo when one is set and their initials otherwise', function () {
    config()->set('lri.pace_setters.people', [
        paceSetter(['photo' => 'images/pace-setters/adebola.jpg']),
        paceSetter(['name' => 'Tunde Bakare']),
    ]);

    $this->get(route('pace-setters'))
        ->assertOk()
        ->assertSee('src="'.asset('images/pace-setters/adebola.jpg').'" alt="Portrait of Adebola Okafor"', false)
        ->assertSee('<span class="fd-initials" aria-hidden="true">TB</span>', false)
        ->assertDontSee('<span class="fd-initials" aria-hidden="true">AO</span>', false);
});

test('a pace setter only gets an Instagram link when they have a handle', function () {
    config()->set('lri.pace_setters.people', [
        paceSetter(['instagram' => 'adebola.runs']),
        paceSetter(['name' => 'Tunde Bakare']),
    ]);

    $response = $this->get(route('pace-setters'))
        ->assertOk()
        ->assertSee('href="https://www.instagram.com/adebola.runs/"', false)
        ->assertSeeText('@adebola.runs');

    expect(substr_count($response->getContent(), 'ps-ig'))->toBe(1);
});

test('the pace setters page points visitors to the member form', function () {
    config()->set('lri.links.member_form', 'https://forms.example.com/join-lri');

    $this->get(route('pace-setters'))
        ->assertOk()
        ->assertSee('href="https://forms.example.com/join-lri"', false);
});
