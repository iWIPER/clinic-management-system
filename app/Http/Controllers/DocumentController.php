<?php

namespace App\Http\Controllers;

use App\Enums\Documents\DocumentStatus;
use App\Models\DocumentCategory;
use App\Models\DocumentTemplate;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        if ($categories->isEmpty()) {
            return Inertia::render('Documents/Index', ['categories' => []]);
        }

        // Fase B2: antes, cada categoria disparava 5 queries dentro do
        // map() (pluck de template ids, count/latest/count de documents,
        // count de templates ativos) — 5×N queries para N categorias. Agora
        // são só 2 queries no total, não importa quantas categorias existam:
        // 1) todos os templates da clínica de uma vez (substitui o
        //    pluck(id) + o count(templates ativos) por categoria);
        // 2) uma agregação por template_id em documents (substitui os 3
        //    counts/latest por categoria) — soma-se por categoria em PHP a
        //    partir do mapeamento template → categoria da query 1.
        $templates = DocumentTemplate::query()
            ->forClinic($clinicId)
            ->get(['id', 'category_id', 'is_active']);

        $templateIdsByCategory = $templates->groupBy('category_id')->map(fn ($group) => $group->pluck('id'));
        $activeTemplatesCountByCategory = $templates->where('is_active', true)->groupBy('category_id')->map->count();

        $documentStatsByTemplate = DB::table('documents')
            ->where('clinic_id', $clinicId)
            ->whereIn('template_id', $templates->pluck('id'))
            ->selectRaw(
                'template_id, COUNT(*) as issued_count, MAX(created_at) as last_issued_at, '
                . 'SUM(CASE WHEN status IN (?, ?, ?, ?) THEN 1 ELSE 0 END) as pending_signatures',
                self::PENDING_STATUSES
            )
            ->groupBy('template_id')
            ->get()
            ->keyBy('template_id');

        $cards = $categories->map(function (DocumentCategory $category) use ($templateIdsByCategory, $activeTemplatesCountByCategory, $documentStatsByTemplate) {
            $templateIds = $templateIdsByCategory->get($category->id, collect());
            $stats = $templateIds->map(fn ($id) => $documentStatsByTemplate->get($id))->filter();

            $lastIssuedAt = $stats->pluck('last_issued_at')->filter()->max();

            return [
                'id'                 => $category->id,
                'name'               => $category->name,
                'slug'               => $category->slug,
                'icon'               => $category->icon,
                'color'              => $category->color,
                'is_system'          => $category->is_system,
                'templates_count'    => $activeTemplatesCountByCategory->get($category->id, 0),
                'issued_count'       => (int) $stats->sum('issued_count'),
                'last_issued_at'     => $lastIssuedAt ? Carbon::parse($lastIssuedAt)->format('d/m/Y') : null,
                'pending_signatures' => (int) $stats->sum('pending_signatures'),
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
