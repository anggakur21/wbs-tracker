<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login()
    {
        $this->get(route('home'))->assertRedirect(route('login'));
    }

    public function test_login_page_loads()
    {
        $this->get(route('login'))->assertOk();
    }
}
