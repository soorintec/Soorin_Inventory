{{--
    نشانِ قرمزِ «نسخهٔ جدید» کنار تیترِ گروهِ منو («مدیریت و گزارش»).

    آیتمِ «به‌روزرسانی» خودش نشانِ عددیِ نسخه را دارد (getNavigationBadge)؛ این اسکریپت
    یک نقطهٔ قرمز هم کنارِ عنوانِ گروه می‌گذارد تا حتی وقتی گروه باز نشده، کاربر متوجه
    شود. با پیدا کردنِ لینکِ صفحهٔ به‌روزرسانی کار می‌کند، پس مستقل از زبان است.
--}}
@php $newVersion = app(\App\Services\AppUpdateService::class)->availableUpdate(); @endphp

@if ($newVersion)
    <style>
        .soorin-update-dot {
            display: inline-block;
            width: 0.55rem;
            height: 0.55rem;
            border-radius: 9999px;
            background: #ef4444;
            margin-inline-start: 0.4rem;
            vertical-align: middle;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.18);
        }
    </style>
    <script>
        (function () {
            function mark() {
                document.querySelectorAll('a[href$="/admin/app-update"]').forEach(function (a) {
                    var group = a.closest('.fi-sidebar-group');
                    if (!group) return;
                    var label = group.querySelector('.fi-sidebar-group-label');
                    if (label && !label.querySelector('.soorin-update-dot')) {
                        var dot = document.createElement('span');
                        dot.className = 'soorin-update-dot';
                        label.appendChild(dot);
                    }
                });
            }
            document.addEventListener('DOMContentLoaded', mark);
            document.addEventListener('livewire:navigated', mark);
            mark();
        })();
    </script>
@endif
