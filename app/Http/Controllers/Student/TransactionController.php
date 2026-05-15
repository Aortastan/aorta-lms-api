<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\Transaction;
use App\Models\MembershipHistory;

class TransactionController extends Controller
{
    public function index(){
        try{
            $user = JWTAuth::parseToken()->authenticate();
            $get_transactions = Transaction::where([
                'user_uuid' => $user->uuid,
            ])
            ->with(['detailTransaction', 'detailTransaction.package'])
            ->get();

            // Preload semua membership terbaru user — key: package_uuid -> latest expired_date
            $latestMemberships = MembershipHistory::where('user_uuid', $user->uuid)
                ->orderBy('expired_date', 'desc')
                ->get()
                ->groupBy('package_uuid')
                ->map(function ($group) {
                    return $group->first()->expired_date;
                });

            $transactions = [];
            foreach ($get_transactions as $index => $transaction) {
                $packages = [];
                $membershipExpiredDate = null;

                foreach ($transaction->detailTransaction as $index1 => $detail) {
                    $packages[] = [
                        "package_uuid" => $detail->package_uuid,
                        "type_of_purchase" => $detail->type_of_purchase,
                        "name" => $detail->package['name'],
                        "image" => $detail->package['image'],
                        "price" => $detail->detail_amount,
                    ];

                    // Ambil membership_expired_date terbaru dari package dalam transaksi ini
                    $pkgExpiry = $latestMemberships->get($detail->package_uuid);
                    if ($pkgExpiry && (!$membershipExpiredDate || $pkgExpiry > $membershipExpiredDate)) {
                        $membershipExpiredDate = $pkgExpiry;
                    }
                }

                $transactions[] = [
                    "transaction_uuid" => $transaction->uuid,
                    "amount" => $transaction->transaction_amount,
                    "status" => $transaction->transaction_status,
                    "url" => $transaction->url,
                    "packages" => $packages,
                    "expired_date" => $transaction->expiry_date,
                    "membership_expired_date" => $membershipExpiredDate,
                    "created_at" => $transaction->created_at,
                    "updated_at" => $transaction->updated_at,
                ];
            }

            return response()->json([
                'message' => 'Sukses mengambil data',
                'transaction' => $transactions,
            ]);
        }catch (\Exception $e) {
            return response()->json([
                'message' => $e
            ]);
        }

    }
}
