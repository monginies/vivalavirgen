<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Support\Proforma;
use Illuminate\Http\Request;

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
}
