<?php
// app/Http/Controllers/Admin/AdminLanguageController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Language;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
class AdminLanguageController extends Controller
{
    public function index(): JsonResponse
    {
        $languages = Language::orderBy('order_index')->get();
        return response()->json($languages);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'icon' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048', // ✅ File upload
            'description' => 'nullable|string',
            'order_index' => 'sometimes|integer',
            'is_active' => 'sometimes|boolean',
        ]);

        $data = $request->all();
        
        // Handle icon upload
        if ($request->hasFile('icon')) {
            $iconPath = $request->file('icon')->store('language-icons', 'public');
            $data['icon'] = $iconPath; // Simpan path-nya saja
        } else {
            $data['icon'] = null;
        }

        // Auto order_index jika tidak disediakan
        if (!isset($data['order_index'])) {
            $maxOrder = Language::max('order_index') ?? 0;
            $data['order_index'] = $maxOrder + 1;
        }

        // Default is_active ke true jika tidak disediakan
        if (!isset($data['is_active'])) {
            $data['is_active'] = true;
        }

        $language = Language::create($data);

        return response()->json($language, 201);
    }

// app/Http/Controllers/Admin/AdminLanguageController.php

public function update(Request $request, $id): JsonResponse
{
    $language = Language::findOrFail($id);
    
    // DEBUG: Log incoming request
    \Log::info('🔄 Update Language Request:', [
        'id' => $id,
        'all_data' => $request->all(),
        'has_file' => $request->hasFile('icon'),
        'file_info' => $request->file('icon') ? [
            'name' => $request->file('icon')->getClientOriginalName(),
            'size' => $request->file('icon')->getSize(),
        ] : null,
        'icon_action' => $request->input('icon_action')
    ]);

    $request->validate([
        'name' => 'sometimes|string|max:255',
        'icon' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        'description' => 'nullable|string',
        'order_index' => 'sometimes|integer',
        'icon_action' => 'sometimes|in:KEEP,REMOVE',
        'is_active' => 'sometimes|boolean',
    ]);

    $data = $request->except(['icon', '_method', 'icon_action']);

    // --- DEBUGGED ICON LOGIC ---
    \Log::info('🔧 Icon Processing:', [
        'has_new_icon' => $request->hasFile('icon'),
        'icon_action' => $request->input('icon_action'),
        'current_icon' => $language->icon
    ]);

    // Kasus 1: Ada file ikon baru yang di-upload
    if ($request->hasFile('icon')) {
        \Log::info('📤 New icon file uploaded');
        
        // Hapus ikon lama jika ada
        if ($language->icon && Storage::disk('public')->exists($language->icon)) {
            Storage::disk('public')->delete($language->icon);
            \Log::info('🗑️ Deleted old icon:', ['path' => $language->icon]);
        }
        
        // Simpan ikon baru
        $iconPath = $request->file('icon')->store('language-icons', 'public');
        $data['icon'] = $iconPath;
        \Log::info('💾 Saved new icon:', ['path' => $iconPath]);

    } 
    // Kasus 2: Tidak ada file baru, tapi ada instruksi REMOVE
    else if ($request->input('icon_action') === 'REMOVE') {
        \Log::info('🗑️ REMOVE icon action requested');
        
        // Hapus ikon lama dari storage
        if ($language->icon && Storage::disk('public')->exists($language->icon)) {
            Storage::disk('public')->delete($language->icon);
            \Log::info('✅ Deleted icon from storage');
        }
        // Update field 'icon' di database menjadi NULL
        $data['icon'] = null;
        
    }
    // Kasus 3: Tidak ada file baru, tapi ada instruksi KEEP
    else if ($request->input('icon_action') === 'KEEP') {
        \Log::info('💾 KEEP icon action - no changes');
        // Jangan update field icon, biarkan tetap yang lama
        unset($data['icon']);
    }
    // Kasus 4: Tidak ada file dan tidak ada instruksi
    else {
        \Log::info('⚡ No icon action - keeping existing');
        unset($data['icon']);
    }

    \Log::info('📝 Final data for update:', $data);

    $language->update($data);

    \Log::info('✅ Language updated successfully');

    return response()->json($language);
}

    public function destroy($id): JsonResponse
    {
        $language = Language::findOrFail($id);
        
        // Delete icon file jika ada
        if ($language->icon && Storage::disk('public')->exists($language->icon)) {
            Storage::disk('public')->delete($language->icon);
        }
        
        // Cek apakah language punya sections sebelum delete
        if ($language->sections()->exists()) {
            return response()->json([
                'message' => 'Cannot delete language with existing sections'
            ], 422);
        }

        $language->delete();

        return response()->json(['message' => 'Language deleted successfully']);
    }
}