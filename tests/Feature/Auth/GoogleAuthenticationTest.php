<?php

namespace Tests\Feature\Auth;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class GoogleAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirect_route_sends_the_user_to_google()
    {
        $response = $this->get(route('auth.google.redirect'));

        $response->assertRedirect();
        $this->assertStringContainsString('accounts.google.com', $response->headers->get('Location'));
    }

    public function test_a_new_verified_google_account_registers_and_logs_in_a_user()
    {
        $this->fakeGoogleUser([
            'id' => 'google-1',
            'name' => 'Budi Owner',
            'email' => 'budi@example.com',
            'email_verified' => true,
        ]);

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticated();

        $user = User::query()->where('email', 'budi@example.com')->firstOrFail();
        $this->assertNotNull($user->email_verified_at);
        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'google-1',
        ]);
    }

    public function test_an_unverified_google_email_is_rejected()
    {
        $this->fakeGoogleUser([
            'id' => 'google-2',
            'name' => 'Belum Verifikasi',
            'email' => 'belum@example.com',
            'email_verified' => false,
        ]);

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'belum@example.com']);
    }

    public function test_logging_in_again_reuses_the_existing_social_account_without_duplicating_it()
    {
        $user = User::factory()->create(['email' => 'budi@example.com']);
        SocialAccount::factory()->create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'google-1',
        ]);

        $this->fakeGoogleUser([
            'id' => 'google-1',
            'name' => 'Budi Owner',
            'email' => 'budi@example.com',
            'email_verified' => true,
        ]);

        $this->get(route('auth.google.callback'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, SocialAccount::query()->where('provider_user_id', 'google-1')->count());
    }

    public function test_a_verified_google_email_matching_an_existing_user_links_instead_of_duplicating()
    {
        $existingUser = User::factory()->create(['email' => 'budi@example.com']);

        $this->fakeGoogleUser([
            'id' => 'google-3',
            'name' => 'Budi Owner',
            'email' => 'budi@example.com',
            'email_verified' => true,
        ]);

        $this->get(route('auth.google.callback'));

        $this->assertAuthenticatedAs($existingUser);
        $this->assertSame(1, User::query()->where('email', 'budi@example.com')->count());
    }

    private function fakeGoogleUser(array $attributes): void
    {
        // SocialiteUser::map() alone only populates ->attributes, not the
        // ->user array getRaw() reads from — setRaw() is what the real
        // OAuth flow calls under the hood, so fake() (which does both) is
        // the correct way to build a test double here, not map() alone.
        $socialiteUser = SocialiteUser::fake($attributes);

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }
}
