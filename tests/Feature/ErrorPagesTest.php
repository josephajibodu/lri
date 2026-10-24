<?php

use Illuminate\Support\Facades\Route;

beforeEach(function () {
    config()->set('app.debug', false);
});

test('a missing page shows the wrong turn page with a way back home', function () {
    $this->get('/this-page-does-not-exist')
        ->assertNotFound()
        ->assertSeeText('404')
        ->assertSeeText('Wrong turn.')
        ->assertSee('href="'.route('home').'"', false);
});

test('each error status shows its own page', function (int $status, string $headline) {
    Route::get('/_error-page-test', fn () => abort($status));

    $this->get('/_error-page-test')
        ->assertStatus($status)
        ->assertSeeText((string) $status)
        ->assertSeeText($headline);
})->with([
    'forbidden' => [403, 'This route is closed.'],
    'page expired' => [419, 'You took a long break.'],
    'too many requests' => [429, 'Easy, pacer.'],
    'server error' => [500, 'We hit the wall.'],
    'service unavailable' => [503, 'Water break.'],
    'any other client error' => [418, 'Off course.'],
    'any other server error' => [502, 'We hit the wall.'],
]);

test('an unexpected exception shows the server error page without its details', function () {
    Route::get('/_exception-page-test', fn () => throw new RuntimeException('Database password is hunter2'));

    $this->get('/_exception-page-test')
        ->assertInternalServerError()
        ->assertSeeText('We hit the wall.')
        ->assertDontSeeText('hunter2');
});
