<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccessLog;
use App\Models\Plan;
use Illuminate\Http\Request;
use Inertia\Inertia;

// Fase System Admin/Backoffice — extraído de Admin\DashboardController
// (RC-16). Comportamento preservado byte-a-byte.
class PlanController extends Controller
{
    public function index(): \Inertia\Response
    {
        $plans = Plan::with('features')->orderBy('sort_order')->get();

        return Inertia::render('Admin/Plans/Index', [
            'plans' => $plans->map(fn ($p) => [
                'id'            => $p->id,
                'name'          => $p->name,
                'slug'          => $p->slug,
                'description'   => $p->description,
                'price_monthly' => $p->price_monthly,
                'price_yearly'  => $p->price_yearly,
                'trial_days'    => $p->trial_days,
                'max_patients'  => $p->max_patients,
                'max_users'     => $p->max_users,
                'is_active'     => $p->is_active,
                'is_featured'   => $p->is_featured,
                'features'      => $p->features->map(fn ($f) => [
                    'label'    => $f->feature_label,
                    'included' => $f->included,
                ]),
            ]),
        ]);
    }

    public function update(Request $request, Plan $plan): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:100',
            'price_monthly' => 'required|numeric|min:0',
            'price_yearly'  => 'required|numeric|min:0',
            'trial_days'    => 'required|integer|min:0',
            'max_patients'  => 'nullable|integer|min:1',
            'max_users'     => 'nullable|integer|min:1',
            'is_active'     => 'required|boolean',
            'description'   => 'nullable|string|max:500',
        ]);

        $plan->update($validated);

        AccessLog::record(
            action: 'admin_plan_updated',
            description: "Plano {$plan->name} atualizado pelo administrador da plataforma",
            metadata: $validated,
        );

        return response()->json(['ok' => true, 'plan' => $plan->fresh()]);
    }
}
