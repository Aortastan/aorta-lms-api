<?php
namespace App\Traits\Payment;

use Xendit\Xendit;
use App\Models\Transaction;
use App\Models\ClaimedCoupon;
use App\Models\PaymentApiLog;
use App\Models\PaymentGatewaySetting;
use Illuminate\Support\Facades\DB;

trait CancelTransactionTrait
{
    public function cancelPendingTransaction(Transaction $transaction, $cancelledBy)
    {
        if ($transaction->transaction_status != 'pending') {
            return response()->json([
                'message' => 'Hanya transaksi berstatus pending yang bisa dibatalkan',
            ], 400);
        }

        if ($transaction->external_id && $transaction->external_id != '-') {
            $expireError = $this->expireXenditInvoice($transaction, $cancelledBy);
            if ($expireError != null) {
                return $expireError;
            }
        }

        DB::transaction(function () use ($transaction) {
            $transaction->transaction_status = 'cancelled';
            $transaction->save();

            ClaimedCoupon::where('transaction_uuid', $transaction->uuid)->update([
                'is_used' => 0,
            ]);
        });

        PaymentApiLog::create([
            'endpoint_url' => 'transactions/' . $transaction->uuid . '/cancel',
            'method' => 'POST',
            'status' => 'Transaction cancelled by ' . $cancelledBy,
        ]);

        return response()->json([
            'message' => 'Transaksi berhasil dibatalkan',
        ], 200);
    }

    private function expireXenditInvoice(Transaction $transaction, $cancelledBy)
    {
        $paymentGateway = PaymentGatewaySetting::first();
        if (!$paymentGateway) {
            return response()->json([
                'message' => 'Payment gateway not found',
            ], 422);
        }

        Xendit::setApiKey($paymentGateway->api_key);

        try {
            $invoice = \Xendit\Invoice::retrieve($transaction->external_id);
            $invoiceStatus = strtoupper($invoice['status'] ?? '');

            if (in_array($invoiceStatus, ['PAID', 'SETTLED'])) {
                return response()->json([
                    'message' => 'Transaksi sudah dibayar dan tidak bisa dibatalkan',
                ], 400);
            }

            if ($invoiceStatus == 'PENDING') {
                \Xendit\Invoice::expireInvoice($transaction->external_id);
            }
        } catch (\Xendit\Exceptions\ApiException $e) {
            if ($e->getCode() == 404 || $e->getErrorCode() == 'INVOICE_NOT_FOUND_ERROR') {
                return null;
            }
            PaymentApiLog::create([
                'endpoint_url' => 'transactions/' . $transaction->uuid . '/cancel',
                'method' => 'POST',
                'status' => 'Cancel by ' . $cancelledBy . ' failed: ' . json_encode($e->getMessage()),
            ]);
            return response()->json([
                'message' => 'Gagal membatalkan invoice pembayaran, silakan coba lagi',
            ], 500);
        } catch (\Exception $e) {
            PaymentApiLog::create([
                'endpoint_url' => 'transactions/' . $transaction->uuid . '/cancel',
                'method' => 'POST',
                'status' => 'Cancel by ' . $cancelledBy . ' failed: ' . json_encode($e->getMessage()),
            ]);
            return response()->json([
                'message' => 'Gagal membatalkan invoice pembayaran, silakan coba lagi',
            ], 500);
        }

        return null;
    }
}
