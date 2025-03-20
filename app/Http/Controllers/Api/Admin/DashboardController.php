<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Invoice;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //count invoice
        $pending = Invoice::where('status', 'pending')->count();
        $success = Invoice::where('status', 'success')->count();
        $expired = Invoice::where('status', 'expired')->count();
        $failed  = Invoice::where('status', 'failed')->count();

        //yearth
        $year   = date('Y');

        //chart 
        $transactions = DB::table('invoices')
            ->addSelect(DB::raw('SUM(grand_total) as grand_total'))
            ->addSelect(DB::raw('MONTH(created_at) as month'))
            ->addSelect(DB::raw('MONTHNAME(created_at) as month_name'))
            ->addSelect(DB::raw('YEAR(created_at) as year'))
            ->whereYear('created_at', '=', $year)
            ->where('status', 'success')
            ->groupBy('month')
            ->orderByRaw('month ASC')
            ->get();
        if(count($transactions)) {
            foreach ($transactions as $result) {
                $month_name[]    = $result->month_name;
                $grand_total[]   = (int)$result->grand_total;
            }
        }else {
            $month_name[]   = "";
            $grand_total[]  = "";
        } 

        //response 
        return response()->json([
            'success' => true,
            'message' => 'Statistik Data',  
            'data'    => [
                'count' => [
                    'pending'   => $pending,
                    'success'   => $success,
                    'expired'   => $expired,
                    'failed'    => $failed
                ],
                'chart' => [
                    'month_name'    => $month_name,
                    'grand_total'   => $grand_total
                ]
            ]  
        ], 200);
    }

    /**
     * Get detailed statistics.
     *
     * @return \Illuminate\Http\Response
     */
    public function getDetailedStats()
    {
        // Total Revenue
        $totalRevenue = Invoice::where('status', 'success')->sum('grand_total');
        
        // Total Customers
        $totalCustomers = Customer::count();
        
        // Total Products
        $totalProducts = Product::count();
        
        // Total Categories
        $totalCategories = Category::count();
        
        // Recent Transactions
        $recentTransactions = Invoice::with('customer')
                                    ->orderBy('created_at', 'desc')
                                    ->limit(5)
                                    ->get();
        
        // Best Selling Products
        $bestSellingProducts = DB::table('invoice_details')
                                ->join('products', 'invoice_details.product_id', '=', 'products.id')
                                ->join('invoices', 'invoice_details.invoice_id', '=', 'invoices.id')
                                ->select(
                                    'products.id',
                                    'products.name',
                                    DB::raw('SUM(invoice_details.qty) as total_qty_sold'),
                                    DB::raw('SUM(invoice_details.price * invoice_details.qty) as total_revenue')
                                )
                                ->where('invoices.status', 'success')
                                ->groupBy('products.id', 'products.name')
                                ->orderBy('total_qty_sold', 'desc')
                                ->limit(5)
                                ->get();

        return response()->json([
            'success' => true,
            'message' => 'Statistik Detail',  
            'data'    => [
                'total_revenue' => $totalRevenue,
                'total_customers' => $totalCustomers,
                'total_products' => $totalProducts,
                'total_categories' => $totalCategories,
                'recent_transactions' => $recentTransactions,
                'best_selling_products' => $bestSellingProducts
            ]  
        ], 200);
    }

    /**
     * Get sales report by date range.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getSalesReport(Request $request)
    {
        $startDate = $request->start_date ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $request->end_date ?? now()->format('Y-m-d');
        
        // Daily sales within period
        $dailySales = DB::table('invoices')
                        ->select(
                            DB::raw('DATE(created_at) as date'),
                            DB::raw('COUNT(*) as total_orders'),
                            DB::raw('SUM(grand_total) as total_sales')
                        )
                        ->whereBetween(DB::raw('DATE(created_at)'), [$startDate, $endDate])
                        ->where('status', 'success')
                        ->groupBy(DB::raw('DATE(created_at)'))
                        ->orderBy('date', 'asc')
                        ->get();
        
        // Category distribution
        $categorySales = DB::table('invoice_details')
                            ->join('products', 'invoice_details.product_id', '=', 'products.id')
                            ->join('categories', 'products.category_id', '=', 'categories.id')
                            ->join('invoices', 'invoice_details.invoice_id', '=', 'invoices.id')
                            ->select(
                                'categories.name',
                                DB::raw('SUM(invoice_details.price * invoice_details.qty) as total_sales')
                            )
                            ->whereBetween(DB::raw('DATE(invoices.created_at)'), [$startDate, $endDate])
                            ->where('invoices.status', 'success')
                            ->groupBy('categories.name')
                            ->orderBy('total_sales', 'desc')
                            ->get();

        // Summary statistics
        $summary = [
            'total_orders' => Invoice::whereBetween(DB::raw('DATE(created_at)'), [$startDate, $endDate])
                                ->where('status', 'success')
                                ->count(),
            'total_revenue' => Invoice::whereBetween(DB::raw('DATE(created_at)'), [$startDate, $endDate])
                                ->where('status', 'success')
                                ->sum('grand_total'),
            'average_order_value' => Invoice::whereBetween(DB::raw('DATE(created_at)'), [$startDate, $endDate])
                                ->where('status', 'success')
                                ->avg('grand_total')
        ];

        return response()->json([
            'success' => true,
            'message' => 'Laporan Penjualan',
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ],
            'data' => [
                'summary' => $summary,
                'daily_sales' => $dailySales,
                'category_sales' => $categorySales
            ]
        ], 200);
    }

    /**
     * Get customer acquisition statistics.
     *
     * @return \Illuminate\Http\Response
     */
    public function getCustomerAcquisition()
    {
        $year = date('Y');
        
        $customerAcquisition = DB::table('customers')
                                ->select(
                                    DB::raw('MONTH(created_at) as month'),
                                    DB::raw('MONTHNAME(created_at) as month_name'),
                                    DB::raw('COUNT(*) as new_customers')
                                )
                                ->whereYear('created_at', '=', $year)
                                ->groupBy('month', 'month_name')
                                ->orderBy('month', 'asc')
                                ->get();
                                
        $customerRetention = DB::table('invoices')
                                ->join('customers', 'invoices.customer_id', '=', 'customers.id')
                                ->select(
                                    DB::raw('COUNT(DISTINCT customers.id) as returning_customers'),
                                    DB::raw('MONTH(invoices.created_at) as month'),
                                    DB::raw('MONTHNAME(invoices.created_at) as month_name')
                                )
                                ->whereYear('invoices.created_at', '=', $year)
                                ->whereRaw('invoices.created_at > customers.created_at + INTERVAL 30 DAY')
                                ->where('invoices.status', 'success')
                                ->groupBy('month', 'month_name')
                                ->orderBy('month', 'asc')
                                ->get();

        return response()->json([
            'success' => true,
            'message' => 'Statistik Akuisisi Pelanggan',
            'data' => [
                'customer_acquisition' => $customerAcquisition,
                'customer_retention' => $customerRetention
            ]
        ], 200);
    }
}