<?php

declare(strict_types=1);

it('responds with 200 on the health endpoint', function (): void
{
    $this->get('/up')->assertOk();
});
