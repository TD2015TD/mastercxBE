<?php

namespace App\Http\Controllers;

use App\Models\MitelConfiguration;
use Illuminate\Http\Request;

class MitelConfigurationController extends Controller
{
    /**
     * Get all active Mitel configurations
     * Returns configurations as key-value pairs for frontend consumption
     */
    public function index()
    {
        $configurations = MitelConfiguration::getAllActive();

        return response()->json([
            'status' => 'success',
            'data' => $configurations,
        ]);
    }

    /**
     * Get a specific configuration by key
     */
    public function show(string $key)
    {
        $config = MitelConfiguration::where('key', $key)
            ->where('is_active', true)
            ->first();

        if (!$config) {
            return response()->json([
                'status' => 'error',
                'message' => 'Configuration not found',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'key' => $config->key,
                'value' => $config->value,
            ],
        ]);
    }

    /**
     * Update a configuration value (admin only)
     */
    public function update(Request $request, string $key)
    {
        $request->validate([
            'value' => 'required|string',
        ]);

        $config = MitelConfiguration::where('key', $key)->first();

        if (!$config) {
            return response()->json([
                'status' => 'error',
                'message' => 'Configuration not found',
            ], 404);
        }

        $config->update([
            'value' => $request->value,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Configuration updated successfully',
            'data' => [
                'key' => $config->key,
                'value' => $config->value,
            ],
        ]);
    }
}
