<?php

namespace Tests\Feature;

use App\Models\User;
// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_guest_visiting_the_homepage_is_redirected_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirectToRoute('login');
    }

    public function test_authenticated_user_visiting_the_homepage_is_redirected_to_dashboard(): void
    {
        $this->actingAs(User::factory()->make());

        $response = $this->get('/');

        $response->assertRedirectToRoute('dashboard');
    }
}
