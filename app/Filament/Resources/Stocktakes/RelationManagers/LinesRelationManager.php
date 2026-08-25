<?php

namespace App\Filament\Resources\Stocktakes\RelationManagers;

use App\Enums\Permission;
use App\Models\Stocktake;
use App\Models\StocktakeLine;
use App\Support\Jalali;
use Filament\Actions\BulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * سطرهای شمارش. انباردار عدد شمارش را همین‌جا وارد می‌کند و ستون «اختلاف»
 * بلافاصله مغایرت را نشان می‌دهد.
 */
class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('stocktake.lines');
    }

    /** صفحه View پیش‌فرض فقط‌خواندنی است، ولی اینجا باید شمارش وارد شود. */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        /** @var Stocktake $stocktake */
        $stocktake = $this->getOwnerRecord();

        $canCount = $stocktake->isEditable()
            && (auth()->user()?->can(Permission::ManageStocktakes->value) ?? false);

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('itemVersion.item.category'))
            // چیدمان بر اساس دسته‌بندی — کالاهای هم‌دسته زیر هم می‌آیند
            ->groups([
                Group::make('itemVersion.item.category.name')
                    ->label(__('items.category_label')),
            ])
            ->defaultGroup('itemVersion.item.category.name')
            ->columns([
                TextColumn::make('itemVersion.item.name')
                    ->label(__('stocktake.item'))
                    ->searchable()
                    ->weight('medium')
                    ->description(fn (StocktakeLine $r) => $r->itemVersion?->item?->code),

                TextColumn::make('itemVersion.version_code')->label(__('stocktake.version')),

                TextColumn::make('itemVersion.location')
                    ->label(__('stocktake.location'))
                    ->placeholder('—')
                    ->extraHeaderAttributes(['class' => 'hidden md:table-cell'])
                    ->extraCellAttributes(['class' => 'hidden md:table-cell']),

                TextColumn::make('system_quantity')
                    ->label(__('stocktake.system_qty'))
                    ->formatStateUsing(fn ($state) => Jalali::quantity($state)),

                // اینپوت درجا: عدد شمارش را همین‌جا وارد کن؛ با کلیک بیرون
                // (blur) خودش ذخیره می‌شود — دیگر پنجره جدا لازم نیست.
                TextInputColumn::make('counted_quantity')
                    ->label(__('stocktake.counted_qty'))
                    ->type('number')
                    ->rules(['nullable', 'numeric', 'min:0'])
                    ->extraInputAttributes(['step' => '1', 'min' => '0', 'inputmode' => 'numeric', 'class' => 'w-24'])
                    ->disabled(! $canCount),

                TextColumn::make('difference')
                    ->label(__('stocktake.difference'))
                    ->state(function (StocktakeLine $record) {
                        $difference = $record->difference();

                        if ($difference === null) {
                            return '—';
                        }

                        if (abs($difference) < 0.0001) {
                            return '✓';
                        }

                        $label = $difference > 0 ? __('stocktake.surplus') : __('stocktake.shortage');

                        return $label . ' ' . Jalali::quantity(abs($difference));
                    })
                    ->color(fn (StocktakeLine $record) => match (true) {
                        ! $record->isCounted()     => 'gray',
                        $record->hasDiscrepancy()  => 'danger',
                        default                    => 'success',
                    })
                    ->weight('bold'),
            ])
            ->filters([
                Filter::make('uncounted')
                    ->label(__('stocktake.not_counted'))
                    ->query(fn (Builder $query) => $query->whereNull('counted_quantity'))
                    ->toggle(),

                // مغایرت در SQL قابل بیان است چون هر دو عدد ستون واقعی‌اند
                Filter::make('discrepancy')
                    ->label(__('stocktake.discrepancy_lines'))
                    ->query(fn (Builder $query) => $query
                        ->whereNotNull('counted_quantity')
                        ->whereColumn('counted_quantity', '!=', 'system_quantity'))
                    ->toggle(),
            ])
            // ویرایش تک‌سطری با پنجره حذف شد؛ شمارش با اینپوت درجای بالا انجام
            // می‌شود. فقط اکشن گروهی «شمارش = موجودی» می‌ماند.
            ->toolbarActions([
                // پرکاربردترین حالت: بیشتر قفسه‌ها درست‌اند و انباردار فقط
                // می‌خواهد بگوید «این‌ها با دفتر می‌خوانند».
                BulkAction::make('markAsMatching')
                    ->label(__('stocktake.mark_matching'))
                    ->icon('heroicon-o-check')
                    ->requiresConfirmation()
                    ->visible(fn () => $canCount)
                    ->action(fn ($records) => $records->each(
                        fn (StocktakeLine $line) => $line->update(['counted_quantity' => $line->system_quantity]),
                    )),
            ])
            ->defaultSort('id')
            ->emptyStateHeading(__('stocktake.empty_lines'))
            // پیش‌فرض ۵۰ (نه ۲۰۰): روی گوشی رندر ۲۰۰ کارت با اینپوت و گروه‌بندی
            // سنگین بود و ناقص لود می‌شد. کاربر دسته‌به‌دسته می‌شمارد، پس ۵۰ کافی است.
            ->paginated([25, 50, 100, 200, 500])
            ->defaultPaginationPageOption(50);
    }
}
