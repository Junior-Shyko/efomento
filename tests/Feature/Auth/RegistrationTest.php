<?php

namespace Tests\Feature\Auth;

use App\Mail\LoginCodeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_is_disabled(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(404);
    }

    public function test_user_created_by_admin_can_authenticate(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertRedirect(route('two-factor.show'));
        $code = Mail::sent(LoginCodeMail::class)->first()->code;
        $this->post(route('two-factor.verify'), ['code' => $code])->assertRedirect('/editais');
        $this->assertAuthenticatedAs($user);
    }
}
