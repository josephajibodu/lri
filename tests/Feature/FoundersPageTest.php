<?php

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function founder(array $overrides = []): array
{
    return [
        'name' => 'Adebola Okafor',
        'role' => 'Co-founder',
        'tribe' => 'Bodija',
        'photo' => null,
        'quote' => 'Nobody runs alone in Ibadan.',
        'bio' => 'Started running after work and never stopped.',
        'facts' => [],
        'links' => [],
        ...$overrides,
    ];
}

test('the founders page introduces each founder from config in order', function () {
    config()->set('lri.founders.people', [
        founder([
            'facts' => ['Saturday pace' => '6:15 /km'],
        ]),
        founder([
            'name' => 'Tunde Bakare',
            'role' => 'Run lead',
            'tribe' => 'Akobo',
            'quote' => 'Six thirty means six thirty.',
            'bio' => 'Keeps the back of the pack company.',
        ]),
    ]);

    $this->get(route('founders'))
        ->assertOk()
        ->assertSee('<title>Founders · Let’s Run Ibadan</title>', false)
        ->assertSee('<link rel="canonical" href="'.route('founders').'">', false)
        ->assertSeeInOrder(['href="#founder-adebola-okafor"', 'href="#founder-tunde-bakare"'], false)
        ->assertSeeInOrder(['id="founder-adebola-okafor"', 'id="founder-tunde-bakare"'], false)
        ->assertSeeText('Nobody runs alone in Ibadan.')
        ->assertSeeText('Keeps the back of the pack company.')
        ->assertSeeText('Run lead · Akobo')
        ->assertSeeText('Saturday pace')
        ->assertSeeText('6:15 /km');
});

test('a founder shows their photo when one is set and their initials otherwise', function () {
    config()->set('lri.founders.people', [
        founder(['photo' => 'images/founders/adebola.jpg']),
        founder(['name' => 'Tunde Bakare']),
    ]);

    $this->get(route('founders'))
        ->assertOk()
        ->assertSee('src="'.asset('images/founders/adebola.jpg').'" alt="Portrait of Adebola Okafor"', false)
        ->assertSee('<span class="fd-initials" aria-hidden="true">TB</span>', false)
        ->assertDontSee('<span class="fd-initials" aria-hidden="true">AO</span>', false);
});

test('a founder only gets link buttons for the links they have', function () {
    config()->set('lri.founders.people', [
        founder(['links' => ['Instagram' => 'https://instagram.example.com/adebola']]),
        founder(['name' => 'Tunde Bakare']),
    ]);

    $response = $this->get(route('founders'))
        ->assertOk()
        ->assertSee('href="https://instagram.example.com/adebola"', false);

    expect(substr_count($response->getContent(), 'class="fd-links"'))->toBe(1);
});

test('the founders page tells the club story from the configured milestones in order', function () {
    config()->set('lri.founders.founded', '2023');
    config()->set('lri.founders.milestones', [
        ['date' => 'Mar 2023', 'title' => 'The first run', 'text' => 'Five friends at Agodi Gardens.'],
        ['date' => 'Jan 2024', 'title' => 'Four tribes', 'text' => 'Runs close to home.'],
    ]);

    $this->get(route('founders'))
        ->assertOk()
        ->assertSeeText('Est. 2023')
        ->assertSeeTextInOrder(['Split 01', 'Mar 2023', 'The first run', 'Five friends at Agodi Gardens.', 'Split 02', 'Jan 2024', 'Four tribes']);
});

test('the founders page points visitors to the member form', function () {
    config()->set('lri.links.member_form', 'https://forms.example.com/join-lri');

    $this->get(route('founders'))
        ->assertOk()
        ->assertSee('href="https://forms.example.com/join-lri"', false);
});
