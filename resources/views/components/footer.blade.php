{{--
    فوتر سامانه — در پنل مدیریت، پرتال مشتری و صفحه ورود استفاده می‌شود.
    سال به‌صورت شمسی و پویا نمایش داده می‌شود؛ نسخه از فایل VERSION خوانده می‌شود
    (همان منبعی که صفحهٔ «به‌روزرسانی» می‌خواند) تا فوتر و آپدیت همیشه سینک باشند.
--}}
@php
    // از App\Support\Branding خوانده می‌شود تا شخصی‌سازیِ مدیر (نام شرکت، عنوان،
    // وب‌سایت، سال تأسیس) در فوتر هم اعمال شود، نه فقط پیش‌فرضِ config.
    $company     = \App\Support\Branding::company();
    $appTitle    = \App\Support\Branding::appTitle();
    $version     = \App\Support\AppVersion::current();
    $currentYear = \App\Support\Jalali::currentYear();   // شمسی در فارسی، میلادی در بقیه
    $founded     = $company['founded_year'];

    // بازهٔ «سال تأسیس – امسال». سال تأسیس همان عددی است که مدیر وارد کرده (شمسی
    // در فارسی، میلادی در انگلیسی). تا وقتی کوچک‌تر از سال جاریِ همان تقویم باشد،
    // بازه نشان داده می‌شود؛ پس اگر مدیر سال میلادی وارد کند، در انگلیسی هم دیده می‌شود.
    $yearRange = ($founded && $founded < $currentYear)
        ? $founded . ' – ' . $currentYear
        : (string) $currentYear;

    $yearRange = \App\Support\Jalali::digits((string) $yearRange);
@endphp

<footer class="app-footer">
    <div class="app-footer__inner">
        <p class="app-footer__copy">
            © {{ $yearRange }} — {{ __('common.footer_rights', ['name' => $company['name']]) }}
        </p>

        <div class="app-footer__meta">
            <a href="{{ $company['website'] }}" target="_blank" rel="noopener">
                {{ $company['website_label'] }}
            </a>
            <span aria-hidden="true">·</span>
            <span>
                {{ $appTitle }}
                — {{ __('common.version') }} {{ \App\Support\Jalali::digits($version) }}
            </span>
        </div>
    </div>
</footer>
