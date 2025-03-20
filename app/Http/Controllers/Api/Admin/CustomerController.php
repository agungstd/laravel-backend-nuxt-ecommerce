<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Customer;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use Illuminate\Support\Facades\Validator;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $customers = Customer::when(request()->q, function($customers) {
            $customers = $customers->where('name', 'like', '%'. request()->q . '%');
         })->latest()->paginate(5);

        //return with Api Resource
        return new CustomerResource(true, 'List Data Customer', $customers);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'      => 'required',
            'email'     => 'required|email|unique:customers',
            'phone'     => 'required|unique:customers',
            'address'   => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        //create customer
        $customer = Customer::create([
            'name'      => $request->name,
            'email'     => $request->email,
            'phone'     => $request->phone,
            'address'   => $request->address,
        ]);

        if($customer) {
            //return success with Api Resource
            return new CustomerResource(true, 'Data Customer Berhasil Disimpan!', $customer);
        }

        //return failed with Api Resource
        return new CustomerResource(false, 'Data Customer Gagal Disimpan!', null);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $customer = Customer::whereId($id)->first();
        
        if($customer) {
            //return success with Api Resource
            return new CustomerResource(true, 'Detail Data Customer!', $customer);
        }

        //return failed with Api Resource
        return new CustomerResource(false, 'Detail Data Customer Tidak Ditemukan!', null);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Customer $customer)
    {
        $validator = Validator::make($request->all(), [
            'name'      => 'required',
            'email'     => 'required|email|unique:customers,email,'.$customer->id,
            'phone'     => 'required|unique:customers,phone,'.$customer->id,
            'address'   => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        //update customer
        $customer->update([
            'name'      => $request->name,
            'email'     => $request->email,
            'phone'     => $request->phone,
            'address'   => $request->address,
        ]);

        if($customer) {
            //return success with Api Resource
            return new CustomerResource(true, 'Data Customer Berhasil Diupdate!', $customer);
        }

        //return failed with Api Resource
        return new CustomerResource(false, 'Data Customer Gagal Diupdate!', null);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Customer $customer)
    {
        if($customer->delete()) {
            //return success with Api Resource
            return new CustomerResource(true, 'Data Customer Berhasil Dihapus!', null);
        }

        //return failed with Api Resource
        return new CustomerResource(false, 'Data Customer Gagal Dihapus!', null);
    }

    /**
     * Get customer order history.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function getOrderHistory($id)
    {
        $customer = Customer::with('orders')->find($id);
        
        if(!$customer) {
            return new CustomerResource(false, 'Customer Tidak Ditemukan!', null);
        }
        
        return new CustomerResource(true, 'Riwayat Pesanan Customer', $customer);
    }

    /**
     * Get all customers.
     *
     * @return \Illuminate\Http\Response
     */
    public function getAllCustomers()
    {
        $customers = Customer::orderBy('name', 'ASC')->get();
        
        return new CustomerResource(true, 'All Customers Data', $customers);
    }

    /**
     * Bulk delete customers.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function bulkDelete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids'     => 'required|array',
            'ids.*'   => 'exists:customers,id',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }
        
        $deletedCount = 0;
        
        foreach($request->ids as $id) {
            $customer = Customer::find($id);
            
            if($customer && $customer->delete()) {
                $deletedCount++;
            }
        }
        
        if($deletedCount > 0) {
            return new CustomerResource(true, "{$deletedCount} Customers Berhasil Dihapus!", null);
        }
        
        return new CustomerResource(false, 'Gagal Menghapus Customers!', null);
    }

    /**
     * Get customers statistics.
     *
     * @return \Illuminate\Http\Response
     */
    public function getStatistics()
    {
        $totalCustomers = Customer::count();
        $newCustomersThisMonth = Customer::whereMonth('created_at', now()->month)
                                        ->whereYear('created_at', now()->year)
                                        ->count();
        $topCustomers = Customer::withCount('orders')
                               ->withSum('orders', 'total_price')
                               ->orderByDesc('orders_sum_total_price')
                               ->limit(5)
                               ->get();
        
        $statistics = [
            'total_customers' => $totalCustomers,
            'new_customers_this_month' => $newCustomersThisMonth,
            'top_customers' => $topCustomers
        ];
        
        return new CustomerResource(true, 'Customer Statistics', $statistics);
    }
}