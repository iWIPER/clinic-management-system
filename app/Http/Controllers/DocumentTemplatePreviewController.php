<?php

namespace App\Http\Controllers;

use App\Models\Clinic;
use App\Models\Patient;
use App\Services\Documents\DocumentPlaceholderResolver;
use App\Services\HtmlSanitizer;
use Illuminate\Http\Request;

class DocumentTemplatePreviewController extends Controller
{
    public function __construct(private DocumentPlaceholderResolver $resolver) {}

    public function preview(Request $request)
    {
        $validated = $request->validate([
            'content_html'      => 'nullable|string',
            'sample_patient_id' => 'nullable|integer',
        ]);

        $clinicId = session('current_clinic_id');
        $clinic   = Clinic::find($clinicId);

        $patient = null;
        if (! empty($validated['sample_patient_id'])) {
            $patient = Patient::where('clinic_id', $clinicId)->find($validated['sample_patient_id']);
        }
        $patient ??= $this->mockPatient();

        $html = $this->resolver->resolve(HtmlSanitizer::richText($validated['content_html'] ?? ''), [
            'patient'      => $patient,
            'clinic'       => $clinic,
            'professional' => $request->user(),
            'treatment'    => null,
            'budget'       => null,
        ], forPreview: true);

        return response()->json(['html' => $html]);
    }

    public function placeholders()
    {
        return response()->json(['placeholders' => $this->resolver->availablePlaceholders()]);
    }

    private function mockPatient(): Patient
    {
        return new Patient([
            'nome'       => 'João',
            'sobrenome'  => 'da Silva (exemplo)',
            'doc_tipo'   => 'cpf',
            'doc_numero' => '000.000.000-00',
            'telefone'   => '(11) 90000-0000',
            'email'      => 'exemplo@paciente.com',
            'nascimento' => now()->subYears(35),
            'sexo'       => 'Não informado',
            'logradouro' => 'Rua Exemplo',
            'numero'     => '123',
            'bairro'     => 'Centro',
            'cidade'     => 'São Paulo',
            'estado'     => 'SP',
            'cep'        => '00000-000',
        ]);
    }
}
