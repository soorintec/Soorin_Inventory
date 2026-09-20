{{--
    سوییچِ کسب‌وکارِ فعال (چند-کسب‌وکاری) — دراپ‌داون در نوارِ بالا. با تغییرِ انتخاب،
    کسب‌وکارِ فعال در نشست ذخیره و صفحه با دیتای همان کسب‌وکار بارگذاری می‌شود. فقط
    وقتی دیده می‌شود که کاربر به بیش از یک کسب‌وکار دسترسی داشته باشد.
--}}
@php
    $user = auth()->user();
    $businesses = $user ? $user->accessibleBusinesses() : collect();
    $activeId = \App\Support\Tenancy::active()?->id
        ?? session('active_business_id')
        ?? optional($businesses->firstWhere('is_default', true))->id
        ?? optional($businesses->first())->id;
@endphp

@if ($businesses->count() > 1)
    <form method="POST" action="{{ route('business.save') }}" class="fi-locale-switcher fi-business-switcher">
        @csrf
        <select name="business" class="fi-locale-select" onchange="this.form.submit()" aria-label="Business">
            @foreach ($businesses as $business)
                <option value="{{ $business->id }}" @selected($business->id === (int) $activeId)>{{ $business->name }}</option>
            @endforeach
        </select>
    </form>
@endif
