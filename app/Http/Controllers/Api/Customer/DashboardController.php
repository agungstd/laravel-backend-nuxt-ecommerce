<?php

namespace App\Http\Controllers\Api\Customer;

use App\Models\Invoice;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $customerId = auth()->guard('api_customer')->id();

        // Count invoices by status
        $statuses = ['pending', 'success', 'expired', 'failed'];
        $invoiceCounts = Invoice::where('customer_id', $customerId)
            ->selectRaw(
                collect($statuses)->map(fn($status) => "SUM(status = '{$status}') as {$status}")->join(', ')
            )
            ->first();

        // Response
        return response()->json([
            'success' => true,
            'message' => 'Statistik Data',
            'data' => [
                'count' => $invoiceCounts->toArray()
            ],
        ]);
    }
}
