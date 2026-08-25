<?php

namespace App\Services;

use Mpdf\Mpdf;

/** خروجی PDF گزارش انبار — فونت وزیرمتن + useOTL (اتصال حروف فارسی). */
class InventoryReportPdfService
{
    public function render(array $report): Mpdf
    {
        $defaultConfig     = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();

        $mpdf = new Mpdf([
            'mode'           => 'utf-8',
            'format'         => 'A4-L',
            'default_font'   => 'vazirmatn',
            'directionality' => 'rtl',
            'fontDir'        => array_merge($defaultConfig['fontDir'], [storage_path('fonts/vazirmatn')]),
            'fontdata'       => array_merge($defaultFontConfig['fontdata'], [
                'vazirmatn' => [
                    'R' => 'Vazirmatn-Regular.ttf',
                    'B' => 'Vazirmatn-Bold.ttf',
                    'useOTL'     => 0xFF,
                    'useKashida' => 75,
                ],
            ]),
            'margin_top'    => 12,
            'margin_bottom' => 12,
        ]);

        $mpdf->SetTitle(__('reports.pdf_title'));
        $mpdf->WriteHTML($this->renderView($report));

        return $mpdf;
    }

    /** همان گزارش، به‌صورت صفحه HTML با پنجره چاپ خودکار. */
    public function html(array $report): string
    {
        return \App\Support\PrintableHtml::wrap($this->renderView($report));
    }

    private function renderView(array $report): string
    {
        return view('pdf.report', [
            'report'  => $report,
            'company' => \App\Support\Branding::company(),
            'logo'    => \App\Support\Branding::logoData('light'),
            'money'   => fn (int $amount) => \App\Support\Jalali::money($amount),
            'date'    => fn ($d) => \App\Support\Jalali::format($d),
            // همه مقدارهایی که با این تابع چاپ می‌شوند عددی‌اند (تعداد و گردش)،
            // پس اعشار صفر حذف و جداکننده هزارگان اضافه می‌شود.
            'digits'  => fn ($n) => \App\Support\Jalali::quantity($n),
        ])->render();
    }
}
