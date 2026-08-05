<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessFinancialWebhookJob;
use App\Models\ClinicFinancialConnection;
use Illuminate\Http\Request;

class FinancialWebhookController extends Controller
{
    public function receive(string $provider, int $connectionId, Request $request)
    {
        $connection = ClinicFinancialConnection::where('id', $connectionId)
            ->where('provider', $provider)
            ->firstOrFail();

        ProcessFinancialWebhookJob::dispatch(
            $connection->id,
            $request->all(),
            $request->header('X-Financial-Signature'),
        );

        return response()->json(['received' => true]);
    }
}