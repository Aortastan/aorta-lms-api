<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\PurchasedPackage;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\Package;
use Carbon\CarbonPeriod;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $startDate = Carbon::parse($request->input('start_date', Carbon::now()->startOfMonth()));
        $endDate   = Carbon::parse($request->input('end_date', Carbon::now()->endOfMonth()));

        // Summary numbers
        $data["student_total"]      = User::where('role', 'student')->whereBetween('created_at', [$startDate, $endDate])->count();
        $data['package_sold_total'] = PurchasedPackage::whereBetween('created_at', [$startDate, $endDate])->count();
        $data['transaction_total']  = Transaction::where('transaction_status', 'settled')->whereBetween('created_at', [$startDate, $endDate])->count();
        $data['revenue_total']      = Transaction::where('transaction_status', 'settled')->whereBetween('created_at', [$startDate, $endDate])->sum('transaction_amount');

        // Chart: GROUP BY DATE(created_at)
        $grouped = Transaction::select(
            DB::raw("DATE(created_at) as date"),
            DB::raw("COUNT(*) as count"),
            DB::raw("SUM(transaction_amount) as sum_amount")
        )
            ->where('transaction_status', 'settled')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy(DB::raw("DATE(created_at)"))
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        // Build full date range
        $period = CarbonPeriod::create($startDate, $endDate);

        $chartData = collect($period)->map(function ($date) use ($grouped) {
            $formatted = $date->format('Y-m-d');

            return [
                'date'       => $formatted,
                'count'      => $grouped[$formatted]->count      ?? 0,
                'sum_amount' => $grouped[$formatted]->sum_amount ?? 0,
            ];
        });

        $data['chart_data'] = $chartData;

        return response()->json([
            'message' => 'Success get data',
            'data'    => $data,
        ]);
    }
}
