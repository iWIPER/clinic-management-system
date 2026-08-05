<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\Treatment;

class TreatmentMaterialConsumptionService
{
    /**
     * Decrementa o estoque dos materiais vinculados a um procedimento do
     * catálogo (Treatment::materials(), pivot com quantidade). Extraído de
     * ConsultationController::addExecution() para ser reaproveitado também
     * ao finalizar um PatientTreatment.
     */
    public function consume(Treatment $treatment): void
    {
        foreach ($treatment->materials as $materialPivot) {
            $itemId = $materialPivot->pivot->inventory_item_id ?? null;
            $item = $itemId ? InventoryItem::find($itemId) : null;

            if ($item) {
                $qty = $materialPivot->pivot->quantidade ?? 1;
                $item->decrement('quantidade', $qty);
            }
        }
    }
}
