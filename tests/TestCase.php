<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Views load built assets with @vite; tests should not need `npm run build`.
        $this->withoutVite();
    }
}
