<?php

namespace App\Http\Controllers;

use App\Models\ClientCategory;
use Illuminate\Http\Request;

class ClientCategoryController extends Controller
{
    public function index()
    {
        return view('client-categories.index', ['categories' => ClientCategory::withCount('clients')->orderBy('sort')->orderBy('name')->get()]);
    }

    public function create()
    {
        return view('client-categories.form', ['category' => new ClientCategory(['discount_percent' => 0])]);
    }

    public function store(Request $request)
    {
        ClientCategory::create($this->validated($request));

        return redirect()->route('client-categories.index')->with('status', 'Tipo de cliente creado.');
    }

    public function edit(ClientCategory $clientCategory)
    {
        return view('client-categories.form', ['category' => $clientCategory]);
    }

    public function update(Request $request, ClientCategory $clientCategory)
    {
        $clientCategory->update($this->validated($request));

        return redirect()->route('client-categories.index')->with('status', 'Tipo de cliente actualizado. El nuevo descuento se aplica a los pedidos nuevos.');
    }

    public function destroy(ClientCategory $clientCategory)
    {
        $clientCategory->delete();

        return redirect()->route('client-categories.index')->with('status', 'Tipo de cliente eliminado. Sus clientes quedan sin tipo (sin descuento).');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'discount_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]) + ['sort' => 0];
    }
}
