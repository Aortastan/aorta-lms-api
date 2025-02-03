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
    public function index()
    {
        try {
            // Mengambil data transaksi tanpa relasi terlebih dahulu
            $get_transactions = Transaction::get();
            \Log::info('Transactions: ', $get_transactions->toArray());

            if ($get_transactions->isEmpty()) {
                return response()->json([
                    'message' => 'No transactions found.',
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
                    "url" => $transaction->url,
                    "expired_date" => $transaction->expiry_date, // Format here
                    "created_at" => $transaction->created_at, // Format here
                    "updated_at" => $transaction->updated_at, // Format here if needed
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
        $startDate = $request->startDate;
        $endDate = $request->endDate;
        $selectedPackage = $request->selectedPackage;
        $cleanedPackage = str_replace('+', ' ', $selectedPackage);

        return Excel::download(new TransactionExport($startDate, $endDate, $cleanedPackage), 'transaction.xlsx');
    }



}
