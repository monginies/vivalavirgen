<?php

namespace App\Http\Controllers;

use App\Enums\UnitStatus;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UnitController extends Controller
{
    /** Destino de la URL impresa en el QR. */
    public function resolve(string $token)
    {
        $unit = Unit::where('qr_token', $token)->firstOrFail();

        return redirect()->route('units.show', $unit);
    }

    public function show(Unit $unit)
    {
        $unit->load('article.category');

        return view('units.show', compact('unit'));
    }

    public function update(Request $request, Unit $unit)
    {
        $unit->update($request->validate([
            'status' => ['required', Rule::enum(UnitStatus::class)],
            'acquired_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]));

        return back()->with('status', 'Unidad actualizada.');
    }

    public function destroy(Unit $unit)
    {
        $article = $unit->article;
        $unit->delete();

        return redirect()->route('articles.show', $article)->with('status', "Unidad {$unit->code} eliminada.");
    }

    public function qr(Unit $unit)
    {
        return response($unit->qrSvg(), 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'inline; filename="'.$unit->code.'.svg"',
        ]);
    }
}
