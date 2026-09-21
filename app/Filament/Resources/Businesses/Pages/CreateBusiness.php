<?php

namespace App\Filament\Resources\Businesses\Pages;

use App\Filament\Resources\Businesses\BusinessResource;
use App\Models\Business;
use App\Services\TenantProvisioner;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateBusiness extends CreateRecord
{
    protected static string $resource = BusinessResource::class;

    /**
     * ساختِ کسب‌وکار از مسیرِ provisioner: رکورد + دیتابیس/پیشوند + مهاجرت‌های
     * tenant + دادهٔ پایه. (به‌جای ساختِ سادهٔ رکورد.)
     */
    protected function handleRecordCreation(array $data): Model
    {
        $business = app(TenantProvisioner::class)->create(
            $data['name'],
            $data['code'] ?? null,
            auth()->user(),
        );

        if (array_key_exists('is_active', $data)) {
            $business->update(['is_active' => (bool) $data['is_active']]);
        }

        return $business;
    }

    protected function afterCreate(): void
    {
        // سازنده همیشه به کسب‌وکارِ تازه دسترسی داشته باشد (حتی اگر در فهرستِ
        // کاربران تیکش نزده باشد؛ فیلد رابطه ممکن است او را برداشته باشد).
        /** @var Business $business */
        $business = $this->getRecord();

        if ($user = auth()->user()) {
            $business->users()->syncWithoutDetaching([$user->id]);
        }
    }
}
