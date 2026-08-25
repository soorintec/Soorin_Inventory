<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /** @var array<int, string> */
    private array $chosenPermissions = [];

    /**
     * تیک‌ها ستون جدول نیستند و باید پیش از ساخت رکورد از داده جدا شوند.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        [$this->chosenPermissions, $data] = UserResource::extractPermissionFields($data);

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var User $user */
        $user = $this->getRecord();

        /*
        | مدل User هنگام ساخت، پیش‌فرض نوع حساب را می‌دهد. اینجا با انتخاب
        | صریح مدیر جایگزین می‌شود — حتی اگر خالی باشد، چون «هیچ تیکی نزدم»
        | یعنی «این کاربر هیچ دسترسی‌ای ندارد».
        */
        $user->syncPermissions($this->chosenPermissions);
    }
}
