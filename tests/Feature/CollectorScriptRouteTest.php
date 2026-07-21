<?php

it('serves the collector script with a js content type and long cache', function () {
    $response = $this->get('/__analytics.js');

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/javascript; charset=utf-8');

    expect($response->headers->get('Cache-Control'))->toContain('max-age=31536000')
        ->toContain('immutable');

    expect($response->getContent())->toContain('sendBeacon')
        ->toContain('__falconAnalytics')
        // The fetch fallback must swallow its rejection: an unreachable endpoint
        // may never surface an uncaught promise in the host console.
        ->toContain('.catch(');
});
