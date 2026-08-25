<?php

namespace App\Services;

use App\Support\Jalali;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/** خروجی اکسل گزارش انبار — چهار برگه راست‌چین. */
class InventoryReportExcelService
{
    public function build(array $report): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        $this->sheet($spreadsheet, __('reports.by_user'), $report['by_user'], [
            'user' => __('reports.col_user'), 'in_count' => __('reports.col_in_count'), 'out_count' => __('reports.col_out_count'),
            'in_qty' => __('reports.col_in_qty'), 'out_qty' => __('reports.col_out_qty'),
        ]);
        $this->sheet($spreadsheet, __('reports.system_costs'), $report['system_costs'], [
            'code' => __('reports.col_code'), 'name' => __('reports.col_system'), 'customer' => __('reports.col_customer'), 'cost' => __('reports.col_cost'),
        ]);
        $this->sheet($spreadsheet, __('reports.stock_levels'), $report['stock_levels'], [
            'item' => __('reports.col_item'), 'version' => __('reports.col_version'), 'qty' => __('reports.col_qty'),
            'currency_label' => __('items.fx_currency'), 'value' => __('reports.col_value'),
        ]);

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /** @param array<string,string> $headers */
    private function sheet(Spreadsheet $spreadsheet, string $title, iterable $rows, array $headers): void
    {
        $sheet = $spreadsheet->createSheet();
        // اکسل عنوان شیت را حداکثر ۳۱ کاراکتر می‌پذیرد؛ عنوان‌های ترجمه‌شده گاهی
        // بلندترند، پس امن کوتاه می‌شود.
        $sheet->setTitle(mb_substr($title, 0, 31));
        $sheet->setRightToLeft(config('locales.available.' . app()->getLocale() . '.dir', 'rtl') === 'rtl');

        $columns = array_keys($headers);
        $col = 'A';
        foreach ($headers as $label) {
            $sheet->setCellValue($col . '1', $label);
            $col++;
        }
        $lastCol = chr(ord('A') + count($headers) - 1);
        $sheet->getStyle("A1:{$lastCol}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0F2D4D');
        $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');

        $rowIndex = 2;
        foreach ($rows as $row) {
            $col = 'A';
            foreach ($columns as $key) {
                $value = is_array($row) ? ($row[$key] ?? '') : ($row->{$key} ?? '');
                $sheet->setCellValue($col . $rowIndex, $value ?? '—');
                $col++;
            }
            $rowIndex++;
        }

        foreach (range('A', $lastCol) as $c) {
            $sheet->getColumnDimension($c)->setAutoSize(true);
            $sheet->getStyle($c)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
    }
}
