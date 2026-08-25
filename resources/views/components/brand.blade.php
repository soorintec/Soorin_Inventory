{{--
    نشان برند در نوار بالا: لوگو + نام کامل سامانه.

    فیلامنت وقتی brandLogo یک Htmlable باشد، آن را داخل یک <div class="fi-logo">
    با ارتفاع brandLogoHeight می‌گذارد. پس اینجا فقط محتوای داخل آن div را
    می‌سازیم و ارتفاع را از والد می‌گیریم.
--}}
<span class="app-brand">
    <img class="app-brand__logo" src="{{ $logo }}" alt="{{ $name }}" />
    <span class="app-brand__text">{{ $name }}</span>
</span>
