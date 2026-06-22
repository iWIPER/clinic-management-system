<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $query = InventoryItem::query();

        if ($search = $request->input('search')) {
            $query->where('nome', 'like', "%{$search}%");
        }

        $items = $query->orderBy('nome')->paginate(20)->withQueryString();

        return Inertia::render('Inventory/Index', [
            'items' => $items,
            'filters' => $request->only('search'),
        ]);
    }

    public function create()
    {
        return Inertia::render('Inventory/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nome' => 'required|string|max:255',
            'marca' => 'nullable|string',
            'lote' => 'nullable|string',
            'validade' => 'nullable|date',
            'custo_unitario' => 'nullable|numeric',
            'quantidade' => 'nullable|integer|min:0',
            'quantidade_minima' => 'nullable|integer|min:0',
            'local' => 'nullable|string',
        ]);

        InventoryItem::create($validated);

        return redirect()->route('inventory.index')->with('success', 'Item de estoque adicionado.');
    }

    // Basic entry
    public function addStock(Request $request, InventoryItem $item)
    {
        $validated = $request->validate([
            'quantidade' => 'required|integer|min:1',
        ]);

        $item->increment('quantidade', $validated['quantidade']);

        return back()->with('success', 'Estoque atualizado.');
    }
}
