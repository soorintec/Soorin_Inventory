<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\Permission as Perm;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /** @var array<int, string> */
    private array $chosenPermissions = [];

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /**
     * تیک‌ها از مجوزهای فعلی کاربر پر می‌شوند.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var User $user */
        $user = $this->getRecord();

        $split = Perm::splitIntoGroups($user->directPermissionNames());

        foreach (array_keys(Perm::grouped()) as $group) {
            $data[UserForm::FIELD_PREFIX . $group] = $split[$group] ?? [];
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        [$this->chosenPermissions, $data] = UserResource::extractPermissionFields($data);

        return $data;
    }

    protected function afterSave(): void
    {
        /** @var User $user */
        $user = $this->getRecord();

        // syncPermissions هم می‌دهد هم می‌گیرد — همان چیزی که «برداشتن تیک»
        // باید انجام دهد.
        $user->syncPermissions($this->chosenPermissions);
    }
}
