<?php
namespace App\Traits\Student;
use App\Models\Cart;

trait DeleteCartTrait
{
    public function deleteCart($packages, $user_uuid)
    {
        $packageUuids = collect($packages)->pluck('package_uuid')->toArray();

        Cart::where('user_uuid', $user_uuid)
            ->whereIn('package_uuid', $packageUuids)
            ->delete();
    }
}
