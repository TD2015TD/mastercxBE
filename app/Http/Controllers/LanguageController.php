<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class LanguageController extends Controller
{
    /**
     * Get all available languages
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getLanguages()
    {
        $languages = [
            [
                'code' => 'en',
                'name' => 'English',
                'nativeName' => 'English',
                'flag' => '🇺🇸'
            ],
            [
                'code' => 'ms',
                'name' => 'Malay',
                'nativeName' => 'Bahasa Malaysia',
                'flag' => '🇲🇾'
            ]
        ];

        return response()->json([
            'success' => true,
            'languages' => $languages
        ]);
    }

    /**
     * Get translations for a specific language
     *
     * @param string $locale
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTranslations($locale = 'en')
    {
        // Validate locale
        if (!in_array($locale, ['en', 'ms'])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid language code. Supported languages: en, ms'
            ], 400);
        }

        $langPath = base_path("lang/{$locale}.json");

        if (!File::exists($langPath)) {
            return response()->json([
                'success' => false,
                'message' => 'Translation file not found'
            ], 404);
        }

        $translations = json_decode(File::get($langPath), true);

        return response()->json([
            'success' => true,
            'locale' => $locale,
            'translations' => $translations
        ]);
    }

    /**
     * Get all translations for all languages
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllTranslations()
    {
        $translations = [];
        $locales = ['en', 'ms'];

        foreach ($locales as $locale) {
            $langPath = base_path("lang/{$locale}.json");
            
            if (File::exists($langPath)) {
                $translations[$locale] = json_decode(File::get($langPath), true);
            }
        }

        return response()->json([
            'success' => true,
            'translations' => $translations
        ]);
    }
}
