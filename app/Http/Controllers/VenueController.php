<?php

namespace App\Http\Controllers;

use App\Models\Venue;
use App\Services\Distance;
use Illuminate\Http\Request;
use RuntimeException;

class VenueController extends Controller
{
    public function index(Request $request)
    {
        $venues = Venue::query()
            ->withCount('orders')
            ->when($request->q, fn ($q, $term) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$term}%")
                ->orWhere('city', 'like', "%{$term}%")
                ->orWhere('postal_code', 'like', "{$term}%")))
            ->orderByDesc('active')->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('venues.index', compact('venues'));
    }

    public function create()
    {
        return view('venues.form', ['venue' => new Venue(['active' => true]), 'mapsEnabled' => Distance::configured()]);
    }

    public function store(Request $request)
    {
        $venue = Venue::create($this->validated($request));

        return redirect()->route('venues.index')->with(...$this->withKm($venue, 'Finca guardada.'));
    }

    public function edit(Venue $venue)
    {
        return view('venues.form', ['venue' => $venue, 'mapsEnabled' => Distance::configured()]);
    }

    public function update(Request $request, Venue $venue)
    {
        $venue->update($this->validated($request));

        return redirect()->route('venues.index')->with(...$this->withKm($venue, 'Finca actualizada.'));
    }

    public function destroy(Venue $venue)
    {
        $venue->delete(); // los pedidos antiguos conservan su dirección

        return redirect()->route('venues.index')->with('status', 'Finca eliminada.');
    }

    /** Si no se han puesto los km, se intentan calcular al guardar. */
    private function withKm(Venue $venue, string $message): array
    {
        if ($venue->distance_km === null && Distance::configured() && ($venue->address || $venue->city || $venue->postal_code)) {
            try {
                $venue->update(['distance_km' => Distance::fromBase($venue->address, $venue->city, $venue->postal_code)['km']]);
                $message .= " Distancia calculada: {$venue->distance_km} km.";
            } catch (RuntimeException $e) {
                return ['error', $message.' No se han podido calcular los km: '.$e->getMessage()];
            }
        }

        return ['status', $message];
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'regex:/^\d{5}$/'],
            'city' => ['nullable', 'string', 'max:255'],
            'distance_km' => ['nullable', 'integer', 'min:0', 'max:5000'],
            'contact' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ], ['postal_code.regex' => 'El código postal tiene 5 cifras.']);
        $data['active'] = $request->boolean('active');

        return $data;
    }
}
