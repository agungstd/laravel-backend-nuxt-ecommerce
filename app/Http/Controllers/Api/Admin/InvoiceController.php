<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Invoice;
use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PDF;

class InvoiceController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $invoices = Invoice::with('customer')->when(request()->q, function($invoices) {
            $invoices = $invoices->where('invoice', 'like', '%'. request()->q . '%');
        })->latest()->paginate(5);

        //return with Api Resource
        return new InvoiceResource(true, 'List Data Invoices', $invoices);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $invoice = Invoice::with('orders.product', 'customer', 'city', 'province')->whereId($id)->first();
        
        if($invoice) {
            //return success with Api Resource
            return new InvoiceResource(true, 'Detail Data Invoice!', $invoice);
        }

        //return failed with Api Resource
        return new InvoiceResource(false, 'Detail Data Invoice Tidak Ditemukan!', null);
    }

    /**
     * Filter invoices by status.
     *
     * @param  string  $status
     * @return \Illuminate\Http\Response
     */
    public function filterByStatus($status)
    {
        $invoices = Invoice::with('customer')
                    ->where('status', $status)
                    ->when(request()->q, function($invoices) {
                        $invoices = $invoices->where('invoice', 'like', '%'. request()->q . '%');
                    })
                    ->latest()
                    ->paginate(5);

        return new InvoiceResource(true, "List Data Invoices dengan Status: {$status}", $invoices);
    }

    /**
     * Filter invoices by date range.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function filterByDate(Request $request)
    {
        $startDate = $request->start_date;
        $endDate = $request->end_date;

        $invoices = Invoice::with('customer')
                    ->whereBetween(DB::raw('DATE(created_at)'), [$startDate, $endDate])
                    ->when(request()->q, function($invoices) {
                        $invoices = $invoices->where('invoice', 'like', '%'. request()->q . '%');
                    })
                    ->latest()
                    ->paginate(5);

        return new InvoiceResource(true, "List Data Invoices dari Tanggal {$startDate} sampai {$endDate}", $invoices);
    }

    /**
     * Get invoice statistics.
     *
     * @return \Illuminate\Http\Response
     */
    public function getStatistics()
    {
        $totalInvoices = Invoice::count();
        $totalRevenue = Invoice::where('status', 'success')->sum('grand_total');
        $averageOrderValue = Invoice::where('status', 'success')->avg('grand_total');
        
        $statusCounts = [
            'pending' => Invoice::where('status', 'pending')->count(),
            'success' => Invoice::where('status', 'success')->count(),
            'expired' => Invoice::where('status', 'expired')->count(),
            'failed' => Invoice::where('status', 'failed')->count(),
        ];
        
        $invoicesByMonth = DB::table('invoices')
                            ->select(
                                DB::raw('MONTH(created_at) as month'),
                                DB::raw('MONTHNAME(created_at) as month_name'),
                                DB::raw('COUNT(*) as total_invoices'),
                                DB::raw('SUM(CASE WHEN status = "success" THEN grand_total ELSE 0 END) as total_revenue')
                            )
                            ->whereYear('created_at', date('Y'))
                            ->groupBy('month', 'month_name')
                            ->orderBy('month', 'asc')
                            ->get();

        return new InvoiceResource(true, "Statistik Invoice", [
            'total_invoices' => $totalInvoices,
            'total_revenue' => $totalRevenue,
            'average_order_value' => $averageOrderValue,
            'status_counts' => $statusCounts,
            'monthly_stats' => $invoicesByMonth
        ]);
    }

    /**
     * Generate PDF invoice.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function generatePDF($id)
    {
        $invoice = Invoice::with('orders.product', 'customer', 'city', 'province')->whereId($id)->first();
        
        if(!$invoice) {
            return new InvoiceResource(false, 'Invoice Tidak Ditemukan!', null);
        }
        
        // Note: This assumes you have the PDF library configured in your project
        // If using Laravel PDF libraries like barryvdh/laravel-dompdf, this would work
        // Otherwise, you'd need to implement appropriate PDF generation logic
        
        // Placeholder for PDF generation logic
        $pdf = PDF::loadView('invoices.pdf', compact('invoice'));
        
        return $pdf->download('invoice-'.$invoice->invoice.'.pdf');
    }

    /**
     * Update invoice status.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function updateStatus(Request $request, $id)
    {
        $invoice = Invoice::find($id);
        
        if(!$invoice) {
            return new InvoiceResource(false, 'Invoice Tidak Ditemukan!', null);
        }
        
        $validStatuses = ['pending', 'success', 'expired', 'failed'];
        
        if(!in_array($request->status, $validStatuses)) {
            return new InvoiceResource(false, 'Status Tidak Valid!', null);
        }
        
        $invoice->status = $request->status;
        $invoice->save();
        
        return new InvoiceResource(true, 'Status Invoice Berhasil Diupdate!', $invoice);
    }
}