<?php

namespace App\Http\Controllers;

use App\Enums\Documents\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentTemplate;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DocumentController extends Controller
{
    private const PENDING_STATUSES = [
        DocumentStatus::Issued->value,
        DocumentStatus::AwaitingSignature->value,
        DocumentStatus::PatientSigned->value,
        DocumentStatus::ProfessionalSigned->value,
    ];

    public function index()
    {
        $clinicId = session('current_clinic_id');

        $categories = DocumentCategory::query()
            ->active()
            ->forClinic($clinicId)
            ->orderBy('sort_order')
            ->get();

        $cards = $categories->map(function (DocumentCategory $category) use ($clinicId) {
            $templateIds = DocumentTemplate::query()
                ->forClinic($clinicId)
                ->where('category_id', $category->id)
                ->pluck('id');

            $docs = Document::where('clinic_id', $clinicId)->whereIn('template_id', $templateIds);

            $issuedCount = (clone $docs)->count();
            $lastIssued = (clone $docs)->latest('created_at')->first();
            $pending = (clone $docs)->whereIn('status', self::PENDING_STATUSES)->count();

            return [
                'id'                 => $category->id,
                'name'               => $category->name,
                'slug'               => $category->slug,
                'icon'               => $category->icon,
                'color'              => $category->color,
                'is_system'          => $category->is_system,
                'templates_count'    => DocumentTemplate::query()->forClinic($clinicId)->active()->where('category_id', $category->id)->count(),
                'issued_count'       => $issuedCount,
                'last_issued_at'     => $lastIssued?->created_at->format('d/m/Y'),
                'pending_signatures' => $pending,
            ];
        });

        return Inertia::render('Documents/Index', [
            'categories' => $cards,
        ]);
    }

    public function category(DocumentCategory $category)
    {
        $clinicId = session('current_clinic_id');

        $templates = DocumentTemplate::query()
            ->forClinic($clinicId)
            ->active()
            ->where('category_id', $category->id)
            ->with('currentVersion')
            ->withCount('documents')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (DocumentTemplate $t) => [
                'id'            => $t->id,
                'name'          => $t->name,
                'slug'          => $t->slug,
                'description'   => $t->description,
                'is_system'     => $t->is_system,
                'is_default'    => $t->is_default,
                'version'       => $t->currentVersion?->version,
                'issued_count'  => $t->documents_count,
            ]);

        return Inertia::render('Documents/Category', [
            'category'  => $category,
            'templates' => $templates,
        ]);
    }
}
