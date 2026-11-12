<?php

namespace App\Http\Controllers;

use App\Models\PostalCode;

class PostalCodeController extends Controller
{
    /** Localidades de un CP, para rellenar la población en el formulario del pedido. */
    public function __invoke(string $code)
    {
        $rows = PostalCode::lookup($code);
        $main = PostalCode::bestMatch($rows, null);

        return response()->json([
            'found' => $rows->isNotEmpty(),
            'municipality' => $main?->municipality ?? $main?->place,
            'province' => $main?->province,
            'places' => $rows->pluck('place')->unique()->values(),
        ]);
    }
}
