<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_guest_is_redirected_to_login_from_homepage(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('dashboard'));

        $this->get($response->headers->get('Location'))
            ->assertRedirect(route('login'));
    }
}
