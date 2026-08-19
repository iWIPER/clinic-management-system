<?php

namespace App\Http\Controllers;

use App\Models\DocumentCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DocumentCategoryController extends Controller
{
    public function index()
    {
        $clinicId = session('current_clinic_id');

        return response()->json(
            DocumentCategory::query()->active()->forClinic($clinicId)->orderBy('sort_order')->get()
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:120',
            'description' => 'nullable|string|max:500',
            'icon'        => 'nullable|string|max:60',
            'color'       => 'nullable|string|max:30',
        ]);

        $clinicId = session('current_clinic_id');
        $slug = Str::slug($validated['name']);

        $category = DocumentCategory::create([
            'clinic_id'   => $clinicId,
            'name'        => $validated['name'],
            'slug'        => $slug . '-' . Str::random(4),
            'description' => $validated['description'] ?? null,
            'icon'        => $validated['icon'] ?? null,
            'color'       => $validated['color'] ?? 'teal',
            'is_system'   => false,
            'is_active'   => true,
        ]);

        return back()->with('success', "Categoria \"{$category->name}\" criada.");
    }

    public function update(Request $request, DocumentCategory $documentCategory)
    {
        $this->authorize('manage', $documentCategory);
        abort_if($documentCategory->is_system, 403, 'Categorias do sistema não podem ser editadas.');

        $validated = $request->validate([
            'name'        => 'required|string|max:120',
            'description' => 'nullable|string|max:500',
            'icon'        => 'nullable|string|max:60',
            'color'       => 'nullable|string|max:30',
        ]);

        $documentCategory->update($validated);

        return back()->with('success', 'Categoria atualizada.');
    }

    public function deactivate(DocumentCategory $documentCategory)
    {
        $this->authorize('manage', $documentCategory);
        abort_if($documentCategory->is_system, 403, 'Categorias do sistema não podem ser desativadas.');

        $documentCategory->update(['is_active' => false]);

        return back()->with('success', 'Categoria arquivada.');
    }

}
