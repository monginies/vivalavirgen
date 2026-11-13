<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Setting;
use App\Notifications\NewClientRegistered;
use App\Support\Push;
use App\Support\Tracker;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

/** Registro, acceso y recuperación de contraseña de los clientes en la web. */
class AuthController extends Controller
{
    public function showLogin()
    {
        return view('portal.auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $key = 'portal|'.Str::lower($credentials['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Demasiados intentos. Vuelve a probar en '.RateLimiter::availableIn($key).' segundos.']);
        }
        if (! Auth::guard('client')->attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'Email o contraseña incorrectos.']);
        }
        RateLimiter::clear($key);
        $request->session()->regenerate();

        // Volver a donde iba, salvo que fuera el backoffice
        $to = $request->session()->pull('url.intended', route('portal.home'));

        return redirect(str_starts_with((string) parse_url($to, PHP_URL_PATH), '/app') ? route('portal.home') : $to);
    }

    public function logout(Request $request)
    {
        Auth::guard('client')->logout();
        $request->session()->regenerateToken();

        return redirect()->route('portal.home');
    }

    public function showRegister()
    {
        return view('portal.auth.register');
    }

    public function register(Request $request)
    {
        $key = 'portal-register|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            abort(429, 'Demasiados registros desde esta conexión. Prueba más tarde.');
        }
        RateLimiter::hit($key, 3600);

        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(Client::TYPES))],
            'wedding_planner' => ['nullable', 'boolean'],
            'name' => ['required', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'tax_id' => ['required', 'string', 'max:20'],
            'contact_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:255', Rule::unique('clients', 'email')->whereNull('deleted_at')],
            'address' => ['required', 'string', 'max:255'],
            'postal_code' => ['required', 'regex:/^\d{5}$/'],
            'city' => ['required', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
            'privacy' => ['accepted'],
            'website' => ['prohibited'], // trampa para bots
        ], [
            'email.unique' => 'Ya hay una cuenta con ese email. ¿Has olvidado la contraseña?',
            'privacy.accepted' => 'Tienes que aceptar la política de privacidad.',
            'postal_code.regex' => 'El código postal tiene 5 cifras.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ]);

        $client = new Client(collect($data)->only(['type', 'name', 'trade_name', 'tax_id', 'contact_name', 'phone', 'email', 'address', 'postal_code', 'city', 'province'])->all());
        // Entra sin tipo (precio base); el equipo le asigna Esparto, Mimbre o Ratán al aprobarlo
        if ($request->boolean('wedding_planner')) {
            $client->notes = 'Al registrarse indicó que es wedding planner u organizador de eventos.';
        }
        $client->password = $data['password'];
        $client->privacy_accepted_at = now();
        $client->source = 'web';
        $client->save();

        Auth::guard('client')->login($client);
        $request->session()->regenerate();
        Tracker::record('register', ['client_id' => $client->id]);
        rescue(fn () => $client->sendEmailVerificationNotification(), report: true);

        return redirect()->route('portal.account')->with('status', 'Cuenta creada. Te hemos enviado un email para confirmar tu dirección.');
    }

    public function verify(Request $request, int $id, string $hash)
    {
        $client = Client::findOrFail($id);
        abort_unless(hash_equals(sha1($client->getEmailForVerification()), $hash), 403);

        if (! $client->hasVerifiedEmail()) {
            $client->markEmailAsVerified();
            rescue(fn () => Notification::route('mail', Setting::get('notify_email'))->notify(new NewClientRegistered($client)), report: true);
            rescue(fn () => Push::toOffice(
                'Cliente nuevo pendiente de aprobar',
                collect([$client->name, $client->trade_name, $client->typeLabel(), $client->city])->filter()->join(' · '),
                route('clients.show', $client), 'client-'.$client->id,
            ), report: true);
        }

        return redirect()->route(auth('client')->check() ? 'portal.account' : 'portal.login')
            ->with('status', 'Email confirmado. Revisaremos tus datos y te avisaremos cuando tu cuenta esté activa.');
    }

    public function resendVerification(Request $request)
    {
        $client = $request->user('client');
        if (! $client->hasVerifiedEmail()) {
            $key = 'portal-verify|'.$client->id;
            if (! RateLimiter::tooManyAttempts($key, 3)) {
                RateLimiter::hit($key, 600);
                $client->sendEmailVerificationNotification();
            }
        }

        return back()->with('status', 'Te hemos vuelto a enviar el email de confirmación.');
    }

    public function showForgot()
    {
        return view('portal.auth.forgot');
    }

    public function sendResetLink(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);
        Password::broker('clients')->sendResetLink($request->only('email'));

        // Mismo mensaje exista o no la cuenta
        return back()->with('status', 'Si hay una cuenta con ese email, te hemos enviado un enlace para cambiar la contraseña.');
    }

    public function showReset(Request $request, string $token)
    {
        return view('portal.auth.reset', ['token' => $token, 'email' => $request->email]);
    }

    public function reset(Request $request)
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ], ['password.confirmed' => 'Las contraseñas no coinciden.']);

        $status = Password::broker('clients')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (Client $client, string $password) {
                $client->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                event(new PasswordReset($client));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('portal.login')->with('status', 'Contraseña cambiada. Ya puedes entrar.')
            : back()->withErrors(['email' => 'El enlace no es válido o ha caducado. Pide uno nuevo.']);
    }
}
