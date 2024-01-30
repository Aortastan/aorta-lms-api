<?php

namespace App\Http\Controllers\AllRole;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Package;
use App\Models\DetailTransaction;

class DashboardController extends Controller
{
    public function popularPackages(Request $request, $package_type){
        $transactions = DB::table('transactions')
        ->select('detail_transactions.package_uuid', DB::raw('COUNT(*) as total_sales'))
        ->join('detail_transactions', 'transactions.uuid', '=', 'detail_transactions.transaction_uuid')
        ->where('transactions.transaction_status', 'settled');

        if($package_type != 'all'){
            $transactions = $transactions->where('detail_transactions.transaction_type', $package_type);
        }

        if ($request->has('year')) {
            $year = intval($request->input('year'));
            if(is_int($year)){
                $transactions = $transactions->where('transactions.created_at', '>=', now()->subYear($year));
            }
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
                    ->whereHas('transaction', function ($query) use ($request) {
                        if ($request->has('year')) {
                            $year = intval($request->input('year'));
                            if (is_int($year)) {
                                $query->where('created_at', '>=', now()->subYear($year));
                            }
                        }
                    })
                    ->with(['transaction']);

                $totalTransactions = $getData->count();
                $package->total_transactions = $totalTransactions;
            }

        return response()->json([
            'message' => 'Success get data',
            'packages' => $packages,
        ]);
    }
}
