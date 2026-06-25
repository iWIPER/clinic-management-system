<?php

namespace App\Http\Controllers;

use App\Models\Clinic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ClinicSettingsController extends Controller
{
    public function edit()
    {
        $clinic = Clinic::findOrFail(session('current_clinic_id'));

        return Inertia::render('ClinicSettings/Edit', [
            'clinic' => $clinic->only(['id', 'name', 'trade_name', 'slogan', 'logo_path']),
            'logoUrl' => $clinic->logo_path
                ? Storage::disk('public')->url($clinic->logo_path)
                : null,
        ]);
    }

    public function update(Request $request)
    {
        $clinic = Clinic::findOrFail(session('current_clinic_id'));

        $validated = $request->validate([
            'trade_name' => 'nullable|string|max:150',
            'slogan' => 'nullable|string|max:255',
            'logo' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('logo')) {
            if ($clinic->logo_path) {
                Storage::disk('public')->delete($clinic->logo_path);
            }

            $validated['logo_path'] = $request->file('logo')->store('clinic-logos', 'public');
        }

        $clinic->update([
            'trade_name' => $validated['trade_name'] ?? $clinic->trade_name,
            'slogan' => $validated['slogan'] ?? $clinic->slogan,
            'logo_path' => $validated['logo_path'] ?? $clinic->logo_path,
        ]);

        session(['current_clinic' => array_merge(
            session('current_clinic', []),
            ['name' => $clinic->trade_name ?: $clinic->name]
        )]);

        return redirect()
            ->route('clinic-settings.edit')
            ->with('success', 'Configurações da clínica atualizadas.');
    }
}