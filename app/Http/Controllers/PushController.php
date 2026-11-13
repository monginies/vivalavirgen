<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Support\Push;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Activar o desactivar los avisos en el dispositivo del usuario. */
class PushController extends Controller
{
    public function subscribe(Request $request)
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url', 'max:2000'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
        ]);
        PushSubscription::updateOrCreate(['endpoint_hash' => hash('sha256', $data['endpoint'])], [
            'user_id' => $request->user()->id,
            'endpoint' => $data['endpoint'],
            'public_key' => $data['keys']['p256dh'],
            'auth_token' => $data['keys']['auth'],
            'device' => Str::limit((string) $request->userAgent(), 250),
        ]);

        return response()->json(['ok' => true]);
    }

    public function unsubscribe(Request $request)
    {
        $endpoint = (string) $request->validate(['endpoint' => ['required', 'string']])['endpoint'];
        PushSubscription::where('endpoint_hash', hash('sha256', $endpoint))->where('user_id', $request->user()->id)->delete();

        return response()->json(['ok' => true]);
    }

    public function test(Request $request)
    {
        $sent = Push::send($request->user()->pushSubscriptions()->get(), 'Avisos activados', 'Así te llegarán los avisos de Viva la Virgen.', route('dashboard'));

        return response()->json(['sent' => $sent]);
    }
}
