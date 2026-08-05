<?php

namespace App\Http\Controllers;

use App\Models\Clinic;
use App\Services\ClinicLogoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ClinicSettingsController extends Controller
{
    public function edit()
    {
        $clinic = Clinic::findOrFail(session('current_clinic_id'));

        return Inertia::render('ClinicSettings/Edit', [
            'clinic'        => $clinic->only(['id', 'name', 'trade_name', 'slogan', 'logo_path', 'logo_type', 'default_logo']),
            'logoUrl'       => $clinic->logoUrl(),
            'defaultLogos'  => ClinicLogoService::defaultLogos(),
        ]);
    }

    public function update(Request $request)
    {
        $clinic = Clinic::findOrFail(session('current_clinic_id'));

        $validDefaultFiles = array_column(ClinicLogoService::DEFAULT_LOGOS, 'filename');

        $validated = $request->validate([
            'trade_name'   => 'nullable|string|max:150',
            'slogan'       => 'nullable|string|max:255',
            'logo'         => 'nullable|image|max:2048',
            'logo_type'    => 'nullable|in:custom,default',
            'default_logo' => 'nullable|string|in:' . implode(',', $validDefaultFiles),
        ]);

        $updateData = [
            'trade_name' => $validated['trade_name'] ?? $clinic->trade_name,
            'slogan'     => $validated['slogan'] ?? $clinic->slogan,
        ];

        if ($request->hasFile('logo')) {
            if ($clinic->logo_path) {
                Storage::disk('public')->delete($clinic->logo_path);
            }
            $updateData['logo_path'] = $request->file('logo')->store('clinic-logos', 'public');
            $updateData['logo_type'] = 'custom';
        } elseif (($validated['logo_type'] ?? null) === 'default') {
            $updateData['logo_type']    = 'default';
            $updateData['default_logo'] = $validated['default_logo'];
        }

        $clinic->update($updateData);

        return redirect()
            ->route('clinic-settings.edit')
            ->with('success', 'Configurações atualizadas.');
    }

    public function removeLogo()
    {
        $clinic = Clinic::findOrFail(session('current_clinic_id'));

        if ($clinic->logo_path) {
            Storage::disk('public')->delete($clinic->logo_path);
        }

        $clinic->update([
            'logo_path' => null,
            'logo_type' => 'default',
        ]);

        return redirect()
            ->route('clinic-settings.edit')
            ->with('success', 'Logo personalizado removido.');
    }
}
