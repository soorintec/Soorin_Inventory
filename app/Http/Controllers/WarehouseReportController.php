<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Stocktake;
use App\Models\Warehouse;
use App\Services\StocktakeService;
use App\Services\WarehousePdfService;
use App\Support\Jalali;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * گزارش‌های چاپی انبار.
 *
 * همه خروجی‌ها به‌صورت PDF درون مرورگر باز می‌شوند (inline) تا کاربر بتواند
 * مستقیم Ctrl+P بزند — خواسته «پرینت یا PDF» با یک خروجی هر دو را پوشش می‌دهد.
 */
class WarehouseReportController extends Controller
{
    public function __construct(private readonly WarehousePdfService $pdf)
    {
    }

    /** پرینت کل موجودی انبار، گروه‌بندی‌شده بر اساس دسته. */
    public function stockList(Request $request): Response
    {
        $this->authorizeView();

        $warehouseId = $request->integer('warehouse') ?: null;

        $balances = StockBalance::query()
            ->with(['itemVersion.item.category', 'warehouse'])
            ->whereHas('itemVersion.item')
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->when(! $request->boolean('include_zero'), fn ($q) => $q->where('quantity', '>', 0))
            ->get();

        $rows = $balances->map(fn (StockBalance $balance) => [
            'category'  => $balance->itemVersion->item->category?->name ?? '—',
            'code'      => $balance->itemVersion->item->code,
            'name'      => $balance->itemVersion->item->name,
            'version'   => $balance->itemVersion->version_code,
            'location'  => $balance->itemVersion->location ?: '—',
            'warehouse' => $balance->warehouse->name,
            'quantity'  => (float) $balance->quantity,
            'unit'      => $balance->itemVersion->item->unit,
            'fx'        => $balance->itemVersion->fxPriceLabel(),
        ]);

        $groups = $rows
            ->sortBy(fn (array $row) => [$row['category'], $row['name']])
            ->groupBy('category');

        return $this->stream($this->pdf->html('stock-list', [
            'groups'         => $groups,
            'warehouseName'  => $warehouseId
                ? (Warehouse::find($warehouseId)?->name ?? '—')
                : __('reports.all_warehouses'),
            'totalItems'     => $rows->pluck('code')->unique()->count(),
            'totalVersions'  => $rows->count(),
            'totalQuantity'  => $rows->sum('quantity'),
        ], __('reports.stock_list_title')));
    }

    /** گزارش ورود و خروج کالا در یک بازه. */
    public function stockFlow(Request $request): Response
    {
        $this->authorizeView();

        $from = $request->filled('from')
            ? Jalali::toGregorian($request->string('from')->toString())
            : now()->startOfMonth();
        $to = $request->filled('to')
            ? Jalali::toGregorian($request->string('to')->toString())
            : now();

        $from = ($from ?? now()->startOfMonth())->startOfDay();
        $to = ($to ?? now())->endOfDay();

        $warehouseId = $request->integer('warehouse') ?: null;
        $direction = $request->string('direction')->toString();

        $movements = StockMovement::query()
            ->with(['itemVersion.item', 'warehouse', 'user'])
            ->whereBetween('created_at', [$from, $to])
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->when(in_array($direction, ['in', 'out'], true), fn ($q) => $q->where('direction', $direction))
            ->orderBy('created_at')
            ->get();

        $rows = $movements->map(fn (StockMovement $movement) => [
            'at'        => $movement->created_at,
            'item'      => $movement->itemVersion?->item?->name ?? '—',
            'version'   => $movement->itemVersion?->version_code ?? '—',
            'warehouse' => $movement->warehouse?->name ?? '—',
            'direction' => $movement->direction,
            'quantity'  => (float) $movement->quantity,
            'reason'    => $movement->reason,
            'user'      => $movement->user?->name ?? __('activity.system'),
        ])->all();

        return $this->stream($this->pdf->html('stock-flow', [
            'rows'          => $rows,
            'from'          => $from,
            'to'            => $to,
            'warehouseName' => $warehouseId ? (Warehouse::find($warehouseId)?->name ?? null) : null,
            'totalIn'       => $movements->where('direction', 'in')->sum('quantity'),
            'totalOut'      => $movements->where('direction', 'out')->sum('quantity'),
        ], __('reports.stock_flow_title')));
    }

    /** فهرست شمارش انبارگردانی — بدون ستون موجودی. */
    public function stocktakeSheet(Stocktake $stocktake, StocktakeService $service): Response
    {
        $this->authorizeView();

        return $this->stream($this->pdf->html('stocktake-sheet', [
            'stocktake' => $stocktake->load('warehouse'),
            'rows'      => $service->countingSheet($stocktake),
        ], __('stocktake.sheet')));
    }

    /** گزارش نتیجه انبارگردانی با مغایرت‌ها. */
    public function stocktakeReport(Stocktake $stocktake): Response
    {
        $this->authorizeView();

        $stocktake->load(['warehouse', 'startedBy', 'closedBy', 'lines.itemVersion.item']);

        return $this->stream($this->pdf->html('stocktake-report', [
            'stocktake' => $stocktake,
        ], __('stocktake.report')));
    }

    private function authorizeView(): void
    {
        abort_unless(auth()->user()?->can(Permission::ViewStock->value), 403);
    }

    /**
     * نمایش گزارش به‌صورت صفحه HTML با پنجره چاپ خودکار.
     *
     * پیش از این PDF دانلود می‌شد؛ حالا گزارش در مرورگر باز می‌شود و پنجره چاپ
     * می‌آید تا کاربر پرینت بگیرد یا خودش در PDF ذخیره کند.
     */
    private function stream(string $html): Response
    {
        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
