<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Services\InventoryReportExcelService;
use App\Services\InventoryReportPdfService;
use App\Services\InventoryReportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReportController extends Controller
{
    public function excel(Request $request, InventoryReportService $reports, InventoryReportExcelService $excel): Response
    {
        $this->authorizeAccess();

        $report = $reports->generate($this->from($request), $this->to($request));
        $writer = new Xlsx($excel->build($report));

        $tmp = tempnam(sys_get_temp_dir(), 'inv-report');
        $writer->save($tmp);
        $content = file_get_contents($tmp);
        unlink($tmp);

        return response($content, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="inventory-report-' . now()->format('Ymd') . '.xlsx"',
        ]);
    }

    public function pdf(Request $request, InventoryReportService $reports, InventoryReportPdfService $pdf): Response
    {
        $this->authorizeAccess();

        $report = $reports->generate($this->from($request), $this->to($request));

        // پیش‌نمایش چاپ در مرورگر به‌جای دانلود PDF — کاربر خودش پرینت یا
        // ذخیره در PDF می‌کند.
        return response($pdf->html($report), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    private function authorizeAccess(): void
    {
        abort_unless(auth()->user()?->can(Permission::ViewReports->value), 403);
    }

    private function from(Request $request): \Carbon\Carbon
    {
        return $request->filled('from') ? \Carbon\Carbon::parse($request->string('from')) : now()->startOfYear();
    }

    private function to(Request $request): \Carbon\Carbon
    {
        return $request->filled('to') ? \Carbon\Carbon::parse($request->string('to'))->endOfDay() : now()->endOfDay();
    }
}
