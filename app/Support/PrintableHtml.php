<?php

namespace App\Support;

/**
 * تبدیل خروجی HTML یک گزارش به صفحه «پیش‌نمایش چاپ».
 *
 * خواسته مالک پروژه: گزارش به‌جای دانلود فایل PDF، در خود مرورگر باز شود و
 * پنجره چاپ بیاید تا کاربر یا پرینت بگیرد یا خودش در PDF ذخیره کند.
 *
 * همان قالب‌های blade که برای mPDF نوشته شده بودند اینجا هم استفاده می‌شوند؛
 * فقط فونت وب و اسکریپت چاپ و یک دکمه به سرصفحه‌شان تزریق می‌شود تا نه چیزی
 * دوباره‌نویسی شود و نه ظاهر گزارش فرق کند.
 */
class PrintableHtml
{
    public static function wrap(string $html): string
    {
        // فونت وزیرمتن به‌صورت وب (woff2 در public/fonts) چون قالب‌ها با نام
        // «vazirmatn» فونت را صدا می‌زنند و مرورگر باید همان را داشته باشد.
        $head = <<<'HTML'
<style>
@font-face{font-family:vazirmatn;src:url('/fonts/vazirmatn/Vazirmatn-400.woff2') format('woff2');font-weight:400;font-display:swap}
@font-face{font-family:vazirmatn;src:url('/fonts/vazirmatn/Vazirmatn-500.woff2') format('woff2');font-weight:500;font-display:swap}
@font-face{font-family:vazirmatn;src:url('/fonts/vazirmatn/Vazirmatn-700.woff2') format('woff2');font-weight:700;font-display:swap}
@media screen{html{background:#e9eef1}body{max-width:1050px;margin:20px auto;padding:28px 34px;background:#fff;box-shadow:0 1px 8px rgba(11,43,63,.15)}}
.print-toolbar{max-width:1050px;margin:14px auto 0;text-align:center}
.print-toolbar button{font-family:vazirmatn,sans-serif;font-size:12.5pt;padding:9px 28px;border:0;border-radius:9px;background:#0f766e;color:#fff;cursor:pointer}
.print-toolbar button:hover{background:#0d655e}
@media print{.print-toolbar{display:none}body{box-shadow:none;margin:0;max-width:none;padding:0}}
</style>
<script>window.addEventListener('load',function(){setTimeout(function(){window.print();},350);});</script>
HTML;

        $toolbar = '<div class="print-toolbar"><button type="button" onclick="window.print()">'
            . e(__('reports.print_save')) . '</button></div>';

        $html = preg_replace('/<\/head>/i', $head . '</head>', $html, 1);
        $html = preg_replace('/<body([^>]*)>/i', '<body$1>' . $toolbar, $html, 1);

        return $html;
    }
}
