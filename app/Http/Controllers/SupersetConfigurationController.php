<?php

namespace App\Http\Controllers;

use App\Models\SupersetConfiguration;
use Illuminate\Http\Request;

class SupersetConfigurationController extends Controller
{
    /**
     * Get all active Superset configurations (public keys only, secrets masked).
     */
    public function index()
    {
        $configurations = SupersetConfiguration::where('is_active', true)
            ->get()
            ->mapWithKeys(function ($config) {
                return [$config->key => $config->is_secret ? null : $config->value];
            })
            ->toArray();

        return response()->json([
            'status' => 'success',
            'data' => $configurations,
        ]);
    }

    /**
     * Get all configurations with full details (admin use — includes is_secret flag).
     */
    public function adminIndex()
    {
        $configurations = SupersetConfiguration::where('is_active', true)
            ->get()
            ->map(function ($config) {
                return [
                    'key' => $config->key,
                    'value' => $config->value,
                    'description' => $config->description,
                    'is_secret' => $config->is_secret,
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => $configurations,
        ]);
    }

    /**
     * Update a configuration value (admin only).
     */
    public function update(Request $request, string $key)
    {
        $request->validate([
            'value' => 'required|string',
        ]);

        $config = SupersetConfiguration::where('key', $key)->first();

        if (!$config) {
            return response()->json([
                'status' => 'error',
                'message' => 'Configuration not found',
            ], 404);
        }

        $config->update(['value' => $request->value]);

        return response()->json([
            'status' => 'success',
            'message' => 'Configuration updated successfully',
            'data' => [
                'key' => $config->key,
                'value' => $config->is_secret ? null : $config->value,
            ],
        ]);
    }
}
