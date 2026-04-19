<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Exports\TransactionExport;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        try {

            $startDate = Carbon::parse($request->input('startDate', Carbon::now()->startOfMonth()));
            $endDate   = Carbon::parse($request->input('endDate', Carbon::now()->endOfMonth()));
            // Mengambil data transaksi tanpa relasi terlebih dahulu
            $get_transactions = Transaction::whereBetween('created_at', [$startDate, $endDate])->with(['user', 'detailTransaction.package', 'claimedCoupons', 'claimedCoupons.coupon'])->get();

            if ($get_transactions->isEmpty()) {
                return response()->json([
                    'message' => 'No transactions found.',
                    'date_range' => [
                        'start_date' => $startDate->toDateString(),
                        'end_date' => $endDate->toDateString()
                    ],
                ]);
            }

            $transactions = [];
            foreach ($get_transactions as $index => $transaction) {
                $packages = $transaction->detailTransaction && !$transaction->detailTransaction->isEmpty()
                    ? $transaction->detailTransaction
                    : [];

                foreach ($transaction->detailTransaction as $index1 => $detail) {
                    $packages[] = [
                        "name" => isset($detail->package) ? $detail->package->name : 'No Package',  // Cek apakah package ada
                        "type_of_purchase" => $detail->type_of_purchase ?? 'N/A',  // Cek apakah type_of_purchase ada
                        "transaction_type" => $detail->transaction_type ?? 'N/A',  // Cek apakah transaction_type ada
                        "price" => 'Rp ' . number_format($detail->detail_amount, 0, ',', '.')
                    ];
                }

                $transactions[] = [
                    "username" => $transaction->user->name ?? 'N/A', // Cek null untuk user
                    "mobile_number" => $transaction->user->mobile_number ?? 'N/A', // Cek null untuk user
                    "transaction_uuid" => $transaction->uuid,
                    "amount" => 'Rp ' . number_format($transaction->transaction_amount, 0, ',', '.'),
                    "status" => $transaction->transaction_status,
                    'packages' => $packages,
                    'claimed_coupons' => $transaction->claimedCoupons ? $transaction->claimedCoupons->pluck('coupon.code')->implode(', ') : 'N/A',
                    "url" => $transaction->url,
                    "expired_date" => Carbon::parse($transaction->expiry_date)->format('d/m/Y H:i:s'),
                    "created_at" => Carbon::parse($transaction->created_at)->format('d/m/Y H:i:s'),
                    "updated_at" => Carbon::parse($transaction->updated_at)->format('d/m/Y H:i:s'),
                ];
            }

            return response()->json([
                'message' => 'success get data',
                'transaction' => $transactions,
            ]);
        } catch (\Exception $e) {
            \Log::error('Error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error: ' . $e->getMessage()
            ]);
        }
    }

    public function exportTransaction(Request $request)
    {
        // $startDate = $request->input('startDate');
        // $endDate = $request->input('endDate');
        $startDate = Carbon::parse($request->input('startDate', Carbon::now()->startOfMonth()));
        $endDate   = Carbon::parse($request->input('endDate', Carbon::now()->endOfMonth()));
        if (!$startDate || !$endDate) {
            return response()->json([
                'message' => 'startDate and endDate parameters are required.'
            ], 400);
        }
        $selectedPackage = $request->input('selectedPackage');
        $cleanedPackage = str_replace('+', ' ', $selectedPackage);

        return Excel::download(new TransactionExport($startDate, $endDate, $cleanedPackage), $startDate->toDateString() . '-' . $endDate->toDateString() . '-transaction.xlsx');
    }
}
