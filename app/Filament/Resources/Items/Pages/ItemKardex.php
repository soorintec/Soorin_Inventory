<?php

namespace App\Filament\Resources\Items\Pages;

use App\Enums\Permission;
use App\Filament\Resources\Items\ItemResource;
use App\Models\Item;
use App\Models\ItemVersion;
use App\Models\StockMovement;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * کاردکسِ کالا — دفترِ کاملِ ورود/خروجِ یک کالا (همهٔ ورژن‌ها و انبارها) با
 * ماندهٔ در حرکت. فقط خواندنی است؛ حرکت‌ها هرگز اینجا ویرایش نمی‌شوند.
 */
class ItemKardex extends Page
{
    use InteractsWithRecord;

    protected static string $resource = ItemResource::class;

    protected string $view = 'filament.resources.items.pages.item-kardex';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    /** @var array<int, array{movement: StockMovement, balance: float}> */
    public array $rows = [];

    public float $balance = 0;

    public function mount(int|string $record): void
    {
        abort_unless(auth()->user()?->can(Permission::ViewItems->value) ?? false, 403);

        $this->record = $this->resolveRecord($record);
        $this->rows = $this->buildLedger();
        $this->balance = $this->rows === [] ? 0 : end($this->rows)['balance'];
    }

    public function getTitle(): string
    {
        return __('stock.kardex') . ' — ' . $this->record->name;
    }

    /**
     * دفترِ زمان‌مرتب با ماندهٔ در حرکت. حتی ورژن‌های حذف‌شده هم می‌آیند تا
     * تاریخچه کامل بماند؛ ماندهٔ کل (همهٔ انبارها) بعد از هر حرکت محاسبه می‌شود.
     *
     * @return array<int, array{movement: StockMovement, balance: float}>
     */
    private function buildLedger(): array
    {
        $versionIds = ItemVersion::withTrashed()->where('item_id', $this->record->id)->pluck('id');

        $movements = StockMovement::with(['warehouse', 'user', 'itemVersion'])
            ->whereIn('item_version_id', $versionIds)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $balance = 0.0;
        $rows = [];

        foreach ($movements as $movement) {
            $qty = (float) $movement->quantity;
            $balance += $movement->direction === StockMovement::DIRECTION_IN ? $qty : -$qty;
            $rows[] = ['movement' => $movement, 'balance' => $balance];
        }

        return $rows;
    }
}
