<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentShare;
use App\Models\Patient;
use App\Services\Documents\DocumentShareService;
use Illuminate\Http\Request;

class DocumentShareController extends Controller
{
    public function __construct(private DocumentShareService $service) {}

    public function store(Request $request, Patient $patient, Document $document)
    {
        $this->authorize('update', $patient);
        abort_unless($document->patient_id === $patient->id, 404);

        $validated = $request->validate([
            'recipient_email' => 'required|email|max:255',
            'recipient_name'  => 'nullable|string|max:160',
        ]);

        $share = $this->service->share(
            $document,
            $patient,
            $validated['recipient_email'],
            $validated['recipient_name'] ?? null,
            $request->user()->id,
            $request->ip(),
            $request->userAgent(),
        );

        return back()->with('success', "Documento compartilhado com {$validated['recipient_email']}.")
            ->with('shareId', $share->id);
    }

    public function index(Patient $patient, Document $document)
    {
        $this->authorize('view', $patient);
        abort_unless($document->patient_id === $patient->id, 404);

        $shares = DocumentShare::where('shareable_type', Document::class)
            ->where('shareable_id', $document->id)
            ->latest()
            ->get(['id', 'recipient_email', 'recipient_name', 'status', 'generation_status', 'generation_failed_reason', 'sent_at', 'expires_at', 'password_revealed_at', 'created_at']);

        return response()->json(['shares' => $shares]);
    }

    public function revoke(Request $request, Patient $patient, Document $document, DocumentShare $share)
    {
        $this->authorize('update', $patient);
        abort_unless($document->patient_id === $patient->id, 404);
        abort_unless($share->shareable_type === Document::class && $share->shareable_id === $document->id, 404);

        $this->service->revoke($share, $request->user()->id);

        return back()->with('success', 'Compartilhamento revogado.');
    }
}
