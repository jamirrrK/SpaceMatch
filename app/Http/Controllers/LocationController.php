<?php

namespace App\Http\Controllers;

use App\Services\PhilippineLocations;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function index(Request $request, PhilippineLocations $locations)
    {
        $data = $request->validate(['kind' => ['required', 'in:region,province,city,barangay'], 'parent' => ['nullable', 'regex:/^\d{10}$/']]);
        return response()->json(['data' => $locations->options($data['kind'], $data['parent'] ?? null), 'version' => $locations->data()['metadata']['version']])->header('Cache-Control', 'public, max-age=86400');
    }
}
