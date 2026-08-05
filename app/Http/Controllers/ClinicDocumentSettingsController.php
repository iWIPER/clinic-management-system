<?php

namespace App\Http\Controllers;

use App\Models\Clinic;
use App\Models\ClinicDocumentSetting;
use App\Models\DocumentCategory;
use App\Models\DocumentTemplate;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ClinicDocumentSettingsController extends Controller
{
    public function edit()
    {
        $clinic = Clinic::findOrFail(session('current_clinic_id'));
        $settings = $clinic->documentSettings ?? new ClinicDocumentSetting([
            'default_signature_expiration_hours' => 72,
            'footer_show_qrcode' => true,
            'footer_show_hash'   => true,
        ]);

        $templates = DocumentTemplate::query()
            ->forClinic($clinic->id)
            ->active()
            ->with('category')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (DocumentTemplate $t) => [
                'id'          => $t->id,
                'name'        => $t->name,
                'category'    => $t->category?->name,
                'is_default'  => $t->is_default,
                'requires_patient_signature'      => $t->requires_patient_signature,
                'requires_professional_signature' => $t->requires_professional_signature,
            ]);

        return Inertia::render('ClinicSettings/Documents', [
            'clinic' => [
                'phone'                 => $clinic->phone,
                'email'                 => $clinic->email,
                'website'               => $clinic->website,
                'address_street'        => $clinic->address_street,
                'address_number'        => $clinic->address_number,
                'address_complement'    => $clinic->address_complement,
                'address_neighborhood'  => $clinic->address_neighborhood,
                'address_city'          => $clinic->address_city,
                'address_state'         => $clinic->address_state,
                'address_zipcode'       => $clinic->address_zipcode,
            ],
            'settings' => [
                'default_signature_expiration_hours' => $settings->default_signature_expiration_hours,
                'footer_show_qrcode'                 => $settings->footer_show_qrcode,
                'footer_show_hash'                    => $settings->footer_show_hash,
                'footer_custom_text'                  => $settings->footer_custom_text,
            ],
            'templates' => $templates,
        ]);
    }

    public function update(Request $request)
    {
        $clinic = Clinic::findOrFail(session('current_clinic_id'));

        $validated = $request->validate([
            'phone'                 => 'nullable|string|max:30',
            'email'                 => 'nullable|email|max:160',
            'website'               => 'nullable|string|max:255',
            'address_street'        => 'nullable|string|max:255',
            'address_number'        => 'nullable|string|max:20',
            'address_complement'    => 'nullable|string|max:255',
            'address_neighborhood'  => 'nullable|string|max:255',
            'address_city'          => 'nullable|string|max:255',
            'address_state'         => 'nullable|string|max:2',
            'address_zipcode'       => 'nullable|string|max:10',
            'default_signature_expiration_hours' => 'nullable|integer|min:1|max:8760',
            'footer_show_qrcode'    => 'boolean',
            'footer_show_hash'      => 'boolean',
            'footer_custom_text'    => 'nullable|string|max:500',
        ]);

        $clinic->update(array_intersect_key($validated, array_flip([
            'phone', 'email', 'website', 'address_street', 'address_number',
            'address_complement', 'address_neighborhood', 'address_city', 'address_state', 'address_zipcode',
        ])));

        ClinicDocumentSetting::updateOrCreate(
            ['clinic_id' => $clinic->id],
            array_intersect_key($validated, array_flip([
                'default_signature_expiration_hours', 'footer_show_qrcode', 'footer_show_hash', 'footer_custom_text',
            ]))
        );

        return back()->with('success', 'Configurações de documentos salvas.');
    }
}
