<?php

namespace App\Http\Controllers;

use App\Models\Language;
use App\Models\Badge;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
class LanguageController extends Controller
{
    public function index(): JsonResponse
    {
        $languages = Language::where('is_active', true)
            ->orderBy('order_index')
            ->withCount('sections')
            ->get();
            
        return response()->json($languages);
    }

    public function show($id): JsonResponse
    {
        $language = Language::with(['sections' => function($query) {
            $query->where('is_active', true)->orderBy('order_index');
        }])->findOrFail($id);
        
        return response()->json($language);
    }
// app/Http/Controllers/LanguageController.php

public function getLanguageBadges($languageId)
{
    try {
        $user = auth()->user();
        
        if (!$user) {
            return response()->json([]);
        }

        // GET ALL SECTIONS IN THIS LANGUAGE
        $sections = \App\Models\Section::where('language_id', $languageId)
            ->where('is_active', true)
            ->get();

        // GET ALL BADGES FOR THESE SECTIONS
        $badges = \App\Models\Badge::whereIn('section_id', $sections->pluck('id'))
            ->where('is_active', true)
            ->with(['section' => function($query) {
                $query->select('id', 'name');
            }])
            ->orderBy('order_index')
            ->get();

        // CHECK WHICH BADGES USER HAS EARNED
        $userBadgeIds = $user->badges()->pluck('badges.id')->toArray();
        
        $badgesWithStatus = $badges->map(function($badge) use ($userBadgeIds) {
            return [
                'id' => $badge->id,
                'name' => $badge->name,
                'description' => $badge->description,
                'icon_path' => $badge->icon_path, // ✅ EMOJI ONLY - "🏆", "⭐", "🚀"
                'color' => $badge->color,
                'earned' => in_array($badge->id, $userBadgeIds),
                'section_name' => $badge->section->name ?? 'General',
                'section_id' => $badge->section_id
            ];
        });

        // ✅ DEBUG: CEK DATA YANG DIKIRIM
        \Illuminate\Support\Facades\Log::info('Language Badges Response:', $badgesWithStatus->toArray());

        return response()->json($badgesWithStatus);

    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('getLanguageBadges Error: ' . $e->getMessage());
        return response()->json([]);
    }
}
}