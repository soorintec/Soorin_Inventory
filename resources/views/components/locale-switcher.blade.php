{{--
    سوییچ زبان — دراپ‌داون. با تغییرِ انتخاب، فرم ثبت می‌شود و زبان در نشست (و برای
    کاربر واردشده در پروفایل) ذخیره و صفحه با همان زبان بارگذاری می‌شود. فهرست
    زبان‌ها از config/locales.php می‌آید، پس افزودن زبان تازه اینجا تغییری لازم ندارد.
--}}
@php
    $locales = config('locales.available', []);
    $current = app()->getLocale();
@endphp

@if (count($locales) > 1)
    <form method="POST" action="{{ route('locale.save') }}" class="fi-locale-switcher">
        @csrf
        <select name="locale" class="fi-locale-select" onchange="this.form.submit()" aria-label="Language">
            @foreach ($locales as $code => $meta)
                <option value="{{ $code }}" @selected($code === $current)>{{ $meta['name'] }}</option>
            @endforeach
        </select>
    </form>
@endif
