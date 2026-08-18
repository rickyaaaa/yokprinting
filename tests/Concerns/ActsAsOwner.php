<?php

namespace Tests\Concerns;

use App\Models\User;

trait ActsAsOwner
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }
}
