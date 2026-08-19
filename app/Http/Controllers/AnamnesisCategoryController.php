<?php

namespace App\Http\Controllers;

use App\Models\AnamnesisCategoryDefinition;
use App\Services\Anamnesis\CategoryDefinitionService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AnamnesisCategoryController extends Controller
{
    public function __construct(private CategoryDefinitionService $service) {}

    public function index()
    {
        $clinicId = session('current_clinic_id');

        return Inertia::render('Anamneses/Categories/Index', [
            'categories' => $this->service->listForClinic($clinicId, false),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'icon' => 'nullable|string|max:16',
            'icon_color' => 'nullable|string|max:16',
            'description' => 'nullable|string|max:500',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $this->service->store($validated, session('current_clinic_id'));

        return back()->with('success', 'Categoria criada.');
    }

    public function update(Request $request, AnamnesisCategoryDefinition $anamnesisCategory)
    {
        $this->authorize('manage', $anamnesisCategory);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'icon' => 'nullable|string|max:16',
            'icon_color' => 'nullable|string|max:16',
            'description' => 'nullable|string|max:500',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $this->service->update($anamnesisCategory, $validated);

        return back()->with('success', 'Categoria atualizada.');
    }

    public function deactivate(AnamnesisCategoryDefinition $anamnesisCategory)
    {
        $this->authorize('manage', $anamnesisCategory);

        if ($anamnesisCategory->questions()->exists()) {
            $anamnesisCategory->update(['is_active' => false]);

            return back()->with('success', 'Categoria desativada.');
        }

        return back()->with('error', 'Categoria sem perguntas vinculadas.');
    }
}