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
        $data["student_total"] = User::where('role', 'student')->count();
        $data['package_sold_total'] = PurchasedPackage::count();
        $data['transaction_total'] = Transaction::where('transaction_status', 'settled')->count();
        $data['revenue_total'] = Transaction::where('transaction_status', 'settled')->sum('transaction_amount');

        // dd($request->input("date"));
        $year = $request->input('year', now()->year);
        $month = $request->input('month', now()->month);
        $day = $request->input("day", now()->day);

        // If neither month nor year is provided, use the current month and year
        if (!$request->has('month') && !$request->has('year') && !$request->has('date')) {
            $year = now()->year;
            $month = now()->month;
            $day = now()->day;
        }

        $startDate = Carbon::createFromDate($year, $month, $day)->startOfDay();
        $timezone = 'Asia/Jakarta';

        $year = (int) $request->input('year', now($timezone)->year);
        $month = (int) $request->input('month', now($timezone)->month);
        $day = $request->input('date'); // might be null

        if ($day !== 'null') {
            // 🔹 Specific day
            $startDate = Carbon::createFromDate($year, $month, (int) $day, $timezone)->startOfDay();
            $endDate   = Carbon::createFromDate($year, $month, (int) $day, $timezone)->endOfDay();
        } else {
            // 🔹 Whole month
            $startDate = Carbon::createFromDate($year, $month, 1, $timezone)->startOfMonth();
            $endDate   = Carbon::createFromDate($year, $month, 1, $timezone)->endOfMonth();
        }

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
