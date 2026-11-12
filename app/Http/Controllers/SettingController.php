<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Support\ImageResizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
        $rules = collect(Setting::DEFINITIONS)->map(fn ($d) => match ($d['type']) {
            'number' => ['required', 'numeric', 'min:0', 'max:99999'],
            'image' => ['nullable', 'image', 'max:20480'],
            'textarea' => ['nullable', 'string', 'max:5000'],
            default => ['nullable', 'string', 'max:255'],
        })->all();
        $data = $request->validate($rules);

        foreach (Setting::DEFINITIONS as $key => $d) {
            if ($d['type'] === 'image') {
                $old = Setting::get($key);
                if ($request->hasFile($key) || $request->boolean('remove_'.$key)) {
                    if ($old) {
                        Storage::disk('public')->delete($old);
                    }
                    Setting::put($key, $request->hasFile($key) ? ImageResizer::store($request->file($key), 'site', 2400)['path'] : null);
                }

                continue;
            }
            if (array_key_exists($key, $data)) {
                Setting::put($key, $data[$key] === null ? null : (string) $data[$key]);
            }
        }

        return back()->with('status', 'Ajustes guardados. Se aplican a los pedidos nuevos o que se modifiquen.');
    }
}
