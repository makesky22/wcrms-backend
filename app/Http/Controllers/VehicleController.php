<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Models\AuditTrail;
use Illuminate\Http\Request;

class VehicleController extends Controller
{
    /**
     * Display a listing of vehicles.
     */
    public function index()
    {
        return response()->json(Vehicle::all());
    }

    /**
     * Store a newly created vehicle.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'registration' => 'required|string|unique:vehicles,registration',
            'make'         => 'required|string',
            'model'        => 'required|string',
        ]);

        $vehicle = Vehicle::create($validated);

        AuditTrail::log($request->user(), 'create_vehicle', 'Vehicle', $vehicle->id, $validated);

        return response()->json([
            'message' => 'Vehicle created successfully',
            'vehicle' => $vehicle
        ], 201);
    }

    /**
     * Update the specified vehicle.
     */
    public function update(Request $request, Vehicle $vehicle)
    {
        $validated = $request->validate([
            'registration' => 'sometimes|string|unique:vehicles,registration,' . $vehicle->id,
            'make'         => 'sometimes|string',
            'model'        => 'sometimes|string',
            'is_active'    => 'sometimes|boolean'
        ]);

        $vehicle->update($validated);

        AuditTrail::log($request->user(), 'update_vehicle', 'Vehicle', $vehicle->id, $validated);

        return response()->json(['message' => 'Vehicle updated successfully', 'vehicle' => $vehicle]);
    }

    /**
     * Remove the specified vehicle.
     */
    public function destroy(Request $request, Vehicle $vehicle)
    {
        $reg = $vehicle->registration;
        $id  = $vehicle->id;
        $vehicle->delete();
        AuditTrail::log($request->user(), 'delete_vehicle', 'Vehicle', $id, ['registration' => $reg]);
        return response()->json(['message' => 'Vehicle deleted successfully']);
    }
}