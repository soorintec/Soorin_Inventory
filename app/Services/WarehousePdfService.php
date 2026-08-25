<?php

namespace App\Services;

use Mpdf\Mpdf;

/**
 * خروجی PDF گزارش‌های انبار: موجودی کل، ورود و خروج، فهرست شمارش و گزارش
 * انبارگردانی.
 *
 * useOTL = 0xFF اجباری است؛ بدون آن حروف فارسی در PDF از هم جدا چاپ می‌شوند.
 */
class WarehousePdfService
{
    /**
     * @param  string  $view  نام قالب blade زیر resources/views/pdf
     * @param  array<string, mixed>  $data
     */
    public function render(string $view, array $data, string $title, string $orientation = 'L'): Mpdf
    {
        $defaultConfig     = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();

        $mpdf = new Mpdf([
            'mode'           => 'utf-8',
            'format'         => 'A4-' . $orientation,
            'default_font'   => 'vazirmatn',
            'directionality' => 'rtl',
            'fontDir'        => array_merge($defaultConfig['fontDir'], [storage_path('fonts/vazirmatn')]),
            'fontdata'       => array_merge($defaultFontConfig['fontdata'], [
                'vazirmatn' => [
                    'R' => 'Vazirmatn-Regular.ttf',
                    'B' => 'Vazirmatn-Bold.ttf',
                    // بدون این دو، حروف فارسی جدا از هم چاپ می‌شوند
                    'useOTL'     => 0xFF,
                    'useKashida' => 75,
                ],
            ]),
            'margin_top'    => 14,
            'margin_bottom' => 14,
        ]);

        $mpdf->SetTitle($title);
        $mpdf->WriteHTML($this->renderView($view, $data, $title));

        return $mpdf;
    }

    /**
     * همان قالب، ولی به‌صورت صفحه HTML با پنجره چاپ خودکار — برای پیش‌نمایش
     * چاپ در مرورگر به‌جای دانلود PDF.
     *
     * @param  array<string, mixed>  $data
     */
    public function html(string $view, array $data, string $title): string
    {
        return \App\Support\PrintableHtml::wrap($this->renderView($view, $data, $title));
    }

    /** @param array<string, mixed> $data */
    private function renderView(string $view, array $data, string $title): string
    {
        return view("pdf.{$view}", array_merge($data, [
            'company'  => \App\Support\Branding::company(),
            'logo'     => \App\Support\Branding::logoData('light'),
            'app'      => ['title' => \App\Support\Branding::appTitle()],
            'title'    => $title,
            'printedAt' => \App\Support\Jalali::formatDateTime(now()),
            'money'    => fn ($amount) => \App\Support\Jalali::money((int) $amount),
            'qty'      => fn ($value) => \App\Support\Jalali::quantity($value),
            'date'     => fn ($value) => \App\Support\Jalali::format($value),
            'datetime' => fn ($value) => \App\Support\Jalali::formatDateTime($value),
            'digits'   => fn ($value) => \App\Support\Jalali::digits((string) $value),
        ]))->render();
    }
}
