<?php

use Illuminate\Support\Facades\Context;

it('assigns a request id header to every response', function () {
    $response = $this->get('/up');

    $response->assertHeader('X-Request-Id');
});

it('echoes back a client-supplied request id instead of generating a new one', function () {
    $response = $this->withHeader('X-Request-Id', 'client-supplied-id')->get('/up');

    $response->assertHeader('X-Request-Id', 'client-supplied-id');
});

it('adds the request id to the log context', function () {
    $this->get('/up');

    expect(Context::get('request_id'))->not->toBeNull();
});

it('still attaches the request id header to error responses', function () {
    $response = $this->get('/this-route-does-not-exist');

    $response->assertNotFound();
    $response->assertHeader('X-Request-Id');
});
