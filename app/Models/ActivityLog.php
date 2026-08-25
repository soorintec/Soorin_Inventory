<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** ثبت تغییرات مهم — چه کسی، کِی، چه چیزی. این رکوردها هرگز حذف نمی‌شوند. */
class ActivityLog extends Model
{
    use HasFactory;

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

    /** تاریخ و ساعت شمسی به وقت تهران. */
    public function happenedAt(): string
    {
        return \App\Support\Jalali::formatDateTime($this->created_at) ?? '—';
    }
}
