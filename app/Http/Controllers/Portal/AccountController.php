<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Notifications\EndClientProforma;
use App\Support\Proforma;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class AccountController extends Controller
{
    public function index(Request $request)
    {
        $client = $request->user('client');

        return view('portal.account.index', [
            'client' => $client,
            'orders' => $client->orders()->with('lines')->latest()->get(),
        ]);
    }

    public function order(Request $request, string $number)
    {
        $order = $request->user('client')->orders()->where('number', $number)
            ->with('lines.article.mainPhoto', 'venue')->firstOrFail();

        return view('portal.account.order', compact('order'));
    }

    public function proforma(Request $request, string $number)
    {
        $order = $request->user('client')->orders()->where('number', $number)->whereNotNull('proforma_sent_at')->firstOrFail();

        return Proforma::pdf($order)->download(Proforma::filename($order));
    }

    /** Proforma a precio de tarifa para el cliente final de un profesional: descargar o enviar por email. */
    public function endClientProforma(Request $request, string $number)
    {
        $client = $request->user('client');
        abort_unless($client->professionLabel(), 403);
        $order = $client->orders()->where('number', $number)->whereNotNull('proforma_sent_at')->firstOrFail();

        $data = $request->validate([
            'action' => ['required', 'in:download,send'],
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'required_if:action,send', 'email', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
        ], [
            'name.required' => 'Escribe el nombre de tu cliente.',
            'email.required_if' => 'Escribe el email de tu cliente para enviársela.',
        ]);
        $endClient = collect($data)->except('action')->all();
        // Para no tener que volver a escribirlo
        session()->put("end_client.{$order->id}", $endClient);

        if ($data['action'] === 'download') {
            return Proforma::forEndClient($order, $endClient)->download(Proforma::filename($order));
        }

        try {
            Notification::route('mail', $data['email'])->notify(new EndClientProforma($order, $endClient));
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'No se ha podido enviar el email. Prueba más tarde o descarga el PDF y envíaselo tú.');
        }

        return back()->with('status', "Proforma enviada a {$data['email']}. Te hemos puesto en copia.");
    }
}
