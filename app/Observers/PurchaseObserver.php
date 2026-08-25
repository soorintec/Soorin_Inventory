<?php

namespace App\Observers;

use App\Models\Purchase;

class PurchaseObserver
{
    public function creating(Purchase $purchase): void
    {
        if (blank($purchase->number)) {
            $purchase->number = $this->nextNumber();
        }
    }

    /** شماره خرید به قالب P-1405-0001 — سال شمسی + شمارنده. */
    private function nextNumber(): string
    {
        $year = \Hekmatinasser\Verta\Verta::now()->format('Y');

        $last = Purchase::where('number', 'like', "P-{$year}-%")->orderByDesc('id')->value('number');
        $sequence = $last ? ((int) substr($last, -4)) + 1 : 1;

        return sprintf('P-%s-%04d', $year, $sequence);
    }
}
