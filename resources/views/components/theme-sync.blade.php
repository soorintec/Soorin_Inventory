{{--
    همگام‌سازی تم کاربر با حالت شب فیلامنت.

    فیلامنت حالت شب را از localStorage.theme می‌خواند و کلاس dark را روی <html>
    می‌گذارد. تنظیم تم هر کاربر اما در دیتابیس است (ستون users.theme). بدون این
    همگام‌سازی دو کلید جدا داشتیم که با هم نمی‌خواندند.

    این اسکریپت در HEAD_START می‌نشیند، یعنی پیش از اسکریپت خود فیلامنت اجرا
    می‌شود و مقدار درست را در localStorage می‌گذارد.
--}}
<script>
    (function () {
        var saved = @js($theme);          // light | dark | system

        // اگر کاربر همین حالا با کلید خود فیلامنت تم را عوض کرده باشد، مقدار
        // دیتابیس هنوز به‌روز نشده؛ ولی چون هر تغییر بلافاصله ذخیره می‌شود،
        // مقدار دیتابیس همیشه تازه‌ترین انتخاب است و مرجع قرار می‌گیرد.
        localStorage.setItem('theme', saved);
    })();

    document.addEventListener('DOMContentLoaded', function () {
        window.addEventListener('theme-changed', function (event) {
            var theme = event.detail;

            if (! ['light', 'dark', 'system'].includes(theme)) {
                return;
            }

            fetch(@js(route('theme.save')), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify({ theme: theme }),
            });
        });
    });
</script>
