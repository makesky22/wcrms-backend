<?php

namespace App\Http\Controllers;

use App\Models\Ward;
use App\Models\AuditTrail;
use Illuminate\Http\Request;

class WardController extends Controller
{
    /**
     * Display a listing of wards.
     */
    public function index()
    {
        // We return the collection directly so Vue can map it easily
        return response()->json(Ward::all());
    }

    /**
     * Store a newly created ward.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:wards,code',
            'lat'  => 'nullable|numeric',
            'lng'  => 'nullable|numeric',
        ]);

        $ward = Ward::create($validated);

        AuditTrail::log($request->user(), 'create_ward', 'Ward', $ward->id, $validated);

        return response()->json([
            'message' => 'Ward created successfully',
            'ward' => $ward
        ], 201);
    }

    /**
     * Update the specified ward.
     */
    public function update(Request $request, Ward $ward)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|unique:wards,code,' . $ward->id,
            'lat'  => 'nullable|numeric',
            'lng'  => 'nullable|numeric',
            'is_active' => 'sometimes|boolean'
        ]);

        $ward->update($validated);

        AuditTrail::log($request->user(), 'update_ward', 'Ward', $ward->id, $validated);

        return response()->json(['message' => 'Ward updated successfully', 'ward' => $ward]);
    }

    /**
     * Remove the specified ward.
     */
    public function destroy(Request $request, Ward $ward)
    {
        $name = $ward->name;
        $id   = $ward->id;
        $ward->delete();
        AuditTrail::log($request->user(), 'delete_ward', 'Ward', $id, ['name' => $name]);
        return response()->json(['message' => 'Ward deleted successfully']);
    }
}