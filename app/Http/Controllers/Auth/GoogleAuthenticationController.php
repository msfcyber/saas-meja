<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Exceptions\DriverMissingConfigurationException;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

final class GoogleAuthenticationController extends Controller
{
    public function redirect(): SymfonyRedirectResponse
    {
        if (! $this->configured()) {
            return $this->failure('Login Google belum dikonfigurasi oleh administrator.');
        }

        try {
            return Socialite::driver('google')->redirect();
        } catch (DriverMissingConfigurationException $exception) {
            return $this->failure('Login Google belum dapat digunakan.', $exception);
        } catch (Throwable $exception) {
            return $this->failure('Login Google belum dapat digunakan.', $exception);
        }
    }

    public function callback(): RedirectResponse
    {
        if (! $this->configured()) {
            return $this->failure('Login Google belum dikonfigurasi oleh administrator.');
        }

        try {
            $profile = Socialite::driver('google')->user();
        } catch (InvalidStateException|DriverMissingConfigurationException $exception) {
            return $this->failure('Sesi login Google sudah kedaluwarsa. Silakan coba lagi.', $exception);
        } catch (Throwable $exception) {
            return $this->failure('Login Google gagal. Silakan coba lagi.', $exception);
        }

        if (! $this->hasVerifiedEmail($profile)) {
            return $this->failure('Akun Google harus memiliki email yang terverifikasi.');
        }

        $googleId = trim((string) $profile->getId());
        $email = Str::lower(trim((string) $profile->getEmail()));

        if ($googleId === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->failure('Profil Google tidak menyediakan identitas yang valid.');
        }

        try {
            $user = DB::transaction(function () use ($profile, $googleId, $email): User {
                $user = User::query()
                    ->where('google_id', $googleId)
                    ->lockForUpdate()
                    ->first();

                if ($user !== null && Str::lower((string) $user->email) !== $email) {
                    throw new OAuthAccountConflictException;
                }

                if ($user === null) {
                    $user = User::query()
                        ->where('email', $email)
                        ->lockForUpdate()
                        ->first();
                }

                if ($user === null) {
                    $user = User::query()->create([
                        'name' => $this->profileName($profile, $email),
                        'email' => $email,
                        'google_id' => $googleId,
                        'password' => Str::random(64),
                    ]);

                    $user->forceFill(['email_verified_at' => now()])->save();

                    return $user;
                }

                if ($user->google_id !== null && $user->google_id !== $googleId) {
                    throw new OAuthAccountConflictException;
                }

                $user->forceFill([
                    'google_id' => $googleId,
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ])->save();

                return $user;
            });
        } catch (OAuthAccountConflictException) {
            return $this->failure('Akun Google tersebut sudah terhubung ke akun yang berbeda.');
        } catch (Throwable $exception) {
            return $this->failure('Akun Google tidak dapat diproses. Silakan coba lagi.', $exception);
        }

        Auth::login($user);
        request()->session()->regenerate();

        return $user->tenants()->wherePivot('status', 'active')->exists()
            ? to_route('dashboard')
            : to_route('onboarding.create');
    }

    private function configured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect'));
    }

    private function hasVerifiedEmail(SocialiteUser $profile): bool
    {
        if (! method_exists($profile, 'getRaw')) {
            return true;
        }

        $raw = $profile->getRaw();
        $verified = $raw['email_verified'] ?? $raw['verified_email'] ?? true;

        return $verified === true || $verified === 1 || $verified === '1' || $verified === 'true';
    }

    private function profileName(SocialiteUser $profile, string $email): string
    {
        $name = trim((string) ($profile->getName() ?: $profile->getNickname()));

        return Str::limit($name !== '' ? $name : Str::before($email, '@'), 255, '');
    }

    private function failure(string $message, ?Throwable $exception = null): RedirectResponse
    {
        if ($exception !== null) {
            Log::warning('Google OAuth failed', [
                'exception' => $exception::class,
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'error',
            'message' => $message,
        ]);

        return to_route('login');
    }
}

final class OAuthAccountConflictException extends \RuntimeException {}
