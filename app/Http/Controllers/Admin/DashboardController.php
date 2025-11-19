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

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $startDate = $request->input('startDate', Carbon::now()->startOfMonth());
        $endDate = $request->input('endDate', Carbon::now()->endOfMonth());

        $data["student_total"] = User::where('role', 'student')->whereBetween('created_at', [$startDate, $endDate])->count();
        $data['package_sold_total'] = PurchasedPackage::whereBetween('created_at', [$startDate, $endDate])->count();
        $data['transaction_total'] = Transaction::where('transaction_status', 'settled')->whereBetween('created_at', [$startDate, $endDate])->count();
        $data['revenue_total'] = Transaction::where('transaction_status', 'settled')->whereBetween('created_at', [$startDate, $endDate])->sum('transaction_amount');


        // Fetch settled transactions within the specified month
        $settledTransactions = Transaction::where('transaction_status', 'settled')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        // Create a range of dates for the entire month
        $allDates = collect($startDate->toPeriod($endDate, '1 day'))
            ->map(function ($date) {
                return $date->format('Y-m-d');
            });

        // Group the transactions by date and count the number of transactions and sum the transaction amounts
        $chartData = $allDates->map(function ($date) use ($settledTransactions) {
            $transactionsForDate = $settledTransactions->filter(function ($transaction) use ($date) {
                return $transaction->created_at->format('Y-m-d') === $date;
            });

            return [
                'date' => $date,
                'count' => $transactionsForDate->count(),
                'sum_amount' => $transactionsForDate->sum('transaction_amount'),
            ];
        });
        $data['chart_data'] = $chartData;
        return response()->json([
            'message' => 'Success get data',
            'data' => $data,
        ], 200);
    }
}
