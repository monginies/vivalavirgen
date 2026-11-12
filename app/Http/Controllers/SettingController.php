<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function edit()
    {
        return view('settings', [
            'definitions' => Setting::DEFINITIONS,
        ]);
    }

    public function update(Request $request)
    {
        $rules = collect(Setting::DEFINITIONS)->map(fn ($d) => $d['type'] === 'number'
            ? ['required', 'numeric', 'min:0', 'max:99999']
            : ['nullable', 'string', 'max:255'])->all();
        $data = $request->validate($rules);

        foreach ($data as $key => $value) {
            Setting::put($key, $value === null ? null : (string) $value);
        }

        return back()->with('status', 'Ajustes guardados. Se aplican a los pedidos nuevos o que se modifiquen.');
    }
}
