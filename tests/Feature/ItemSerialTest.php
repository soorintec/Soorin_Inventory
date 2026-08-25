<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ItemSerial;
use App\Models\ItemVersion;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * شماره سریال اقلام گران. جدول سریال فقط برای کالایی دیده می‌شود که تیک
 * «ثبت سریال» دارد.
 */
class ItemSerialTest extends TestCase
{
    use RefreshDatabase;

    private Item $tracked;
    private Item $plain;
    private ItemVersion $version;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $category = ItemCategory::create(['name' => 'قطعات کامپیوتر']);

        $this->tracked = Item::create([
            'item_category_id' => $category->id, 'code' => 'CMP-1',
            'name' => 'هارد SSD 128 GB Lexar', 'track_serial' => true,
        ]);
        $this->plain = Item::create([
            'item_category_id' => $category->id, 'code' => 'CBL-1',
            'name' => 'کابل VGA', 'track_serial' => false,
        ]);

        $this->version = $this->tracked->versions()->create(['version_code' => 'اصلی']);
        Warehouse::create(['name' => 'انبار مرکزی', 'code' => 'MAIN']);
    }

    private function admin(): User
    {
        $u = User::create([
            'name' => 'مدیر', 'email' => 'admin@yoursite.com',
            'password' => 'secret123', 'user_type' => User::TYPE_ADMIN,
        ]);
        $u->assignRole(User::TYPE_ADMIN);

        return $u;
    }

    public function test_the_serials_table_appears_only_for_a_tracked_item(): void
    {
        $manager = \App\Filament\Resources\Items\RelationManagers\SerialsRelationManager::class;

        $this->assertTrue($manager::canViewForRecord($this->tracked, \App\Filament\Resources\Items\Pages\EditItem::class));
        $this->assertFalse($manager::canViewForRecord($this->plain, \App\Filament\Resources\Items\Pages\EditItem::class));
    }

    public function test_serials_are_reachable_from_the_item(): void
    {
        ItemSerial::create(['item_version_id' => $this->version->id, 'serial' => 'SN-001']);
        ItemSerial::create(['item_version_id' => $this->version->id, 'serial' => 'SN-002']);

        $this->assertSame(2, $this->tracked->serials()->count());
        $this->assertSame(0, $this->plain->serials()->count());
    }

    public function test_the_item_page_renders_the_serials_table(): void
    {
        ItemSerial::create(['item_version_id' => $this->version->id, 'serial' => 'SN-ABC-123']);

        Filament::setCurrentPanel('admin');

        \Livewire\Livewire::actingAs($this->admin())->test(
            \App\Filament\Resources\Items\RelationManagers\SerialsRelationManager::class,
            ['ownerRecord' => $this->tracked, 'pageClass' => \App\Filament\Resources\Items\Pages\EditItem::class],
        )
            ->assertSuccessful()
            ->assertSee('SN-ABC-123');
    }

    public function test_bulk_add_creates_one_serial_per_line_and_skips_duplicates(): void
    {
        ItemSerial::create(['item_version_id' => $this->version->id, 'serial' => 'SN-002']);

        Filament::setCurrentPanel('admin');

        \Livewire\Livewire::actingAs($this->admin())->test(
            \App\Filament\Resources\Items\RelationManagers\SerialsRelationManager::class,
            ['ownerRecord' => $this->tracked, 'pageClass' => \App\Filament\Resources\Items\Pages\EditItem::class],
        )
            // اکشن روی سربرگ جدول است، نه روی خود کامپوننت — پس باید table() بگیرد
            ->callAction(\Filament\Actions\Testing\TestAction::make('bulkAdd')->table(), [
                'item_version_id' => $this->version->id,
                'serials'         => "SN-001\nSN-002\n SN-003 \n\nSN-001",
            ])
            ->assertHasNoActionErrors();

        // SN-002 از قبل بود و SN-001 دو بار آمده بود
        $this->assertSame(3, ItemSerial::count());
        $this->assertEqualsCanonicalizing(
            ['SN-001', 'SN-002', 'SN-003'],
            ItemSerial::pluck('serial')->all(),
        );
    }

    public function test_a_new_serial_defaults_to_in_stock(): void
    {
        $serial = ItemSerial::create(['item_version_id' => $this->version->id, 'serial' => 'SN-009']);

        $this->assertSame(ItemSerial::STATUS_IN_STOCK, $serial->status);
    }
}
