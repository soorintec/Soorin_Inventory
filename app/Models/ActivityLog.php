<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Concerns\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * ثبت تغییرات مهم — چه کسی، کِی، چه چیزی.
 *
 * این رکوردها در جریانِ عادیِ کار حذف نمی‌شوند؛ تنها استثنا، «پاک‌سازیِ سیاههٔ
 * قدیمی» است که مدیر با مجوزِ اختصاصی برای سبک‌نگه‌داشتنِ دیتابیس انجام می‌دهد
 * (pruneOlderThan).
 */
class ActivityLog extends Model
{
    use HasFactory, UsesTenantConnection;

    protected $fillable = ['user_id', 'action', 'subject_type', 'subject_id', 'changes', 'ip_address'];

    protected function casts(): array
    {
        return ['changes' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public static function record(string $action, ?Model $subject = null, array $changes = []): self
    {
        return self::create([
            'user_id'      => auth()->id(),
            'action'       => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id'   => $subject?->getKey(),
            'changes'      => $changes ?: null,
            'ip_address'   => request()->ip(),
        ]);
    }

    /** شرح فارسی نوع تغییر — «ورود کالا»، «ویرایش کالا». */
    public function actionLabel(): string
    {
        $key = 'activity.actions.' . $this->action;

        // اگر برای این نوع ترجمه‌ای نگذاشته‌ایم، خودِ کلید بهتر از رشته خام است
        return __($key) === $key ? $this->action : __($key);
    }

    /**
     * شرح خوانای موضوع تغییر — نام کالا، نه «App\Models\Item#12».
     *
     * موضوع ممکن است حذف شده باشد؛ در آن صورت فقط نوع آن نشان داده می‌شود تا
     * سیاهه ناقص به نظر نرسد.
     */
    public function subjectLabel(): ?string
    {
        if (! $this->subject_type) {
            return null;
        }

        $subject = $this->subject;

        if (! $subject) {
            return __('activity.subjects.' . class_basename($this->subject_type));
        }

        return match (true) {
            $subject instanceof StockMovement => $subject->itemVersion?->displayName(),
            $subject instanceof ItemVersion   => $subject->displayName(),
            $subject instanceof Item          => $subject->name,
            default                           => $subject->name ?? $subject->title ?? null,
        };
    }

    /**
     * گروهِ رنگیِ ردیف در داشبورد: ورود سبز، خروج قرمز، ویرایش زرد،
     * پشتیبان آبی، انبارگردانی بنفش — سایر موارد بی‌رنگ.
     */
    public function colorGroup(): ?string
    {
        return match (true) {
            str_starts_with($this->action, 'backup_')   => 'backup',
            str_starts_with($this->action, 'stocktake_') => 'stocktake',
            $this->action === 'stock_in' || $this->action === 'purchase_received' => 'in',
            $this->action === 'stock_out' => 'out',
            str_contains($this->action, 'created')
                || str_contains($this->action, 'updated')
                || str_contains($this->action, 'deleted') => 'edit',
            default => null,
        };
    }

    /** تاریخ و ساعت شمسی به وقت تهران. */
    public function happenedAt(): string
    {
        return \App\Support\Jalali::formatDateTime($this->created_at) ?? '—';
    }

    /**
     * پاک‌سازیِ ردیف‌های قدیمیِ سیاهه — برای سبک‌نگه‌داشتنِ دیتابیس.
     *
     * @param  int|null  $months  قدیمی‌تر از این تعداد ماه پاک شود؛ null یعنی همهٔ سیاهه.
     * @return int  تعداد ردیفِ پاک‌شده
     */
    public static function pruneOlderThan(?int $months): int
    {
        $cutoff = $months !== null ? now()->subMonths($months) : null;

        $query = static::query()
            ->when($cutoff, fn ($q) => $q->where('created_at', '<', $cutoff));

        $count = (clone $query)->count();

        $query->delete();

        return $count;
    }
}
