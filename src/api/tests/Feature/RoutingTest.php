<?php

declare(strict_types=1);

it('returns a json error shape for unknown routes', function (): void
{
    $this->getJson('/v1/this-route-does-not-exist')
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/json')
        ->assertExactJson([
            'message' => 'Resource not found.',
            'code' => 'not_found',
        ]);
});
