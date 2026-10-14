<?php

namespace App\Http\Controllers\AllRole;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\Package;
use App\Models\DetailTransaction;

class DashboardController extends Controller
{
    public function latestPackages(Request $request)
    {
        $limit = (int) $request->query('limit', 3);
        if ($limit < 1) $limit = 1;
        if ($limit > 20) $limit = 20;

        $onlyWithTransactions = filter_var(
            $request->query('only_with_transactions', false),
            FILTER_VALIDATE_BOOLEAN
        );

        $cacheKey = 'dashboard_latest_packages_' . $limit . '_' . ($onlyWithTransactions ? '1' : '0');

        $packages = Cache::remember($cacheKey, 600, function () use ($limit, $onlyWithTransactions) {
            $query = Package::where('status', 'Published');

            if ($onlyWithTransactions) {
                $packageUuidsWithSales = DB::table('detail_transactions')
                    ->join('transactions', 'transactions.uuid', '=', 'detail_transactions.transaction_uuid')
                    ->where('transactions.transaction_status', 'settled')
                    ->pluck('detail_transactions.package_uuid')
                    ->unique()
                    ->toArray();

                $query->whereIn('uuid', $packageUuidsWithSales);
            }

            $packages = $query
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get(['uuid', 'name', 'image', 'package_type', 'description', 'created_at']);

            $packageUuids = $packages->pluck('uuid')->toArray();
            $salesCounts = DB::table('detail_transactions')
                ->join('transactions', 'transactions.uuid', '=', 'detail_transactions.transaction_uuid')
                ->where('transactions.transaction_status', 'settled')
                ->whereIn('detail_transactions.package_uuid', $packageUuids)
                ->select('detail_transactions.package_uuid', DB::raw('COUNT(*) as cnt'))
                ->groupBy('detail_transactions.package_uuid')
                ->pluck('cnt', 'detail_transactions.package_uuid')
                ->toArray();

            $packages = $packages->map(function ($p) use ($salesCounts) {
                $p->total_transactions = $salesCounts[$p->uuid] ?? 0;
                return $p;
            });

            return $packages;
        });

        return response()->json([
            'message' => 'Success get data',
            'packages' => $packages,
        ]);
    }

    public function popularPackages(Request $request, $package_type){
        $year = $request->has('year') ? intval($request->input('year')) : null;
        $cacheKey = 'dashboard_popular_packages_' . $package_type . '_' . ($year ?? 'all');

        $packages = Cache::remember($cacheKey, 600, function () use ($request, $package_type, $year) {
            $transactions = DB::table('transactions')
                ->select('detail_transactions.package_uuid', DB::raw('COUNT(*) as total_sales'))
                ->join('detail_transactions', 'transactions.uuid', '=', 'detail_transactions.transaction_uuid')
                ->where('transactions.transaction_status', 'settled');

            if($package_type != 'all'){
                $transactions = $transactions->where('detail_transactions.transaction_type', $package_type);
            }

            if ($year !== null && is_int($year)) {
                $transactions = $transactions->where('transactions.created_at', '>=', now()->subYear($year));
            }

            $transactions = $transactions->groupBy('detail_transactions.package_uuid')
                ->orderByDesc('total_sales')
                ->limit(5)
                ->get();

            $package_uuids = [];
            foreach ($transactions as $index => $transaction) {
                $package_uuids[] = $transaction->package_uuid;
            }

            $packages = Package::whereIn('uuid', $package_uuids)
                ->withCount(['packageTests', 'packageCourses'])
                ->get();

            foreach ($packages as $index => $package) {
                $getData = DetailTransaction::where('package_uuid', $package->uuid)
                    ->whereHas('transaction', function ($query) use ($request, $year) {
                        if ($year !== null && is_int($year)) {
                            $query->where('created_at', '>=', now()->subYear($year));
                        }
                    })
                    ->with(['transaction']);

                $totalTransactions = $getData->count();
                $package->total_transactions = $totalTransactions;
            }

            return $packages;
        });

        return response()->json([
            'message' => 'Success get data',
            'packages' => $packages,
        ]);
    }
}
