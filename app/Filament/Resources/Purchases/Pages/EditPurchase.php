<?php

namespace App\Filament\Resources\Purchases\Pages;

use App\Actions\ReceivePurchase;
use App\Enums\Permission;
use App\Filament\Resources\Purchases\PurchaseResource;
use App\Models\Purchase;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use RuntimeException;

class EditPurchase extends EditRecord
{
    protected static string $resource = PurchaseResource::class;

    protected function getHeaderActions(): array
    {
        /** @var Purchase $purchase */
        $purchase = $this->getRecord();

        return [
            Action::make('receive')
                ->label(__('purchasing.receive'))
                ->icon('heroicon-o-arrow-down-on-square')
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription(__('purchasing.receive_confirm'))
                ->visible(fn () => $purchase->status !== Purchase::STATUS_RECEIVED
                    && (auth()->user()?->can(Permission::ManagePurchases->value) ?? false))
                ->action(function () use ($purchase) {
                    try {
                        // ابتدا تغییرات فرم فعلی ذخیره شود تا دریافت روی داده به‌روز انجام شود
                        $this->save(shouldRedirect: false);

                        app(ReceivePurchase::class)($purchase->fresh());

                        Notification::make()->success()->title(__('common.saved'))->send();

                        $this->redirect($this->getResource()::getUrl('edit', ['record' => $purchase]));
                    } catch (RuntimeException $e) {
                        Notification::make()->danger()->title($e->getMessage())->send();
                    }
                }),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
