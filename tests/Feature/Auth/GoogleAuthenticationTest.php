<?php

use App\Models\User;
use Illuminate\Support\Facades\Config;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;

beforeEach(function () {
    Config::set('services.google', [
        'client_id' => 'google-client-id',
        'client_secret' => 'google-client-secret',
        'redirect' => 'http://localhost/auth/google/callback',
    ]);
});

test('google login redirects to the provider', function () {
    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('redirect')
        ->once()
        ->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));
    Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);

    $this->get(route('auth.google.redirect'))
        ->assertRedirect('https://accounts.google.com/o/oauth2/auth');
});

test('google callback creates a verified user and starts onboarding', function () {
    $profile = Mockery::mock(SocialiteUser::class);
    $profile->shouldReceive('getId')->andReturn('google-user-123');
    $profile->shouldReceive('getEmail')->andReturn('owner@example.com');
    $profile->shouldReceive('getName')->andReturn('Google Owner');
    $profile->shouldReceive('getNickname')->andReturn(null);

    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->once()->andReturn($profile);
    Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('onboarding.create'));

    $user = User::query()->where('email', 'owner@example.com')->firstOrFail();

    expect($user->google_id)->toBe('google-user-123')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($this->isAuthenticated())->toBeTrue();
});

test('google callback links an existing account by verified email', function () {
    $user = User::factory()->unverified()->create(['email' => 'owner@example.com']);
    $profile = Mockery::mock(SocialiteUser::class);
    $profile->shouldReceive('getId')->andReturn('google-user-456');
    $profile->shouldReceive('getEmail')->andReturn('OWNER@example.com');
    $profile->shouldReceive('getName')->andReturn('Owner');
    $profile->shouldReceive('getNickname')->andReturn(null);

    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->once()->andReturn($profile);
    Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('onboarding.create'));

    expect($user->fresh()->google_id)->toBe('google-user-456')
        ->and($user->fresh()->email_verified_at)->not->toBeNull();
});

test('google callback sends platform admins to the platform dashboard', function () {
    $user = User::factory()->platformAdmin()->create(['email' => 'admin@example.com']);
    $profile = Mockery::mock(SocialiteUser::class);
    $profile->shouldReceive('getId')->andReturn('google-admin-789');
    $profile->shouldReceive('getEmail')->andReturn($user->email);
    $profile->shouldReceive('getName')->andReturn($user->name);
    $profile->shouldReceive('getNickname')->andReturn(null);

    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->once()->andReturn($profile);
    Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('platform.dashboard'));
});
