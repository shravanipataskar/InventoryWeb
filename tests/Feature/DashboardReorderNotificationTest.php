<?php

namespace Tests\Feature;

use App\Category;
use App\Product;
use App\Unit;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class DashboardReorderNotificationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_dashboard_notifies_for_active_products_below_minimum_stock()
    {
        $suffix = strtoupper(Str::random(8));
        $category = Category::create(['name' => 'Reorder Category ' . $suffix, 'is_active' => true]);
        $unit = Unit::create(['name' => 'Reorder Unit ' . $suffix, 'short_name' => 'RU', 'is_active' => true]);
        $this->actingAs(User::create([
            'name' => 'Reorder Dashboard User',
            'email' => Str::uuid() . '@example.test',
            'password' => Hash::make('test-password'),
            'role' => 'ADMIN',
        ]));

        $existingAlertCount = Product::where('is_active', true)
            ->whereColumn('current_stock', '<', 'minimum_stock')
            ->count();
        $low = $this->makeProduct($category, $unit, $suffix . '-LOW', 4, 5, 12, 0, true);
        $out = $this->makeProduct($category, $unit, $suffix . '-OUT', 0, 4, 0, 0, true);
        $more = $this->makeProduct($category, $unit, $suffix . '-MORE', 9, 10, 0, 0, true);
        $atMinimum = $this->makeProduct($category, $unit, $suffix . '-MIN', 5, 5, 12, 0, true);
        $aboveMinimum = $this->makeProduct($category, $unit, $suffix . '-OK', 8, 5, 12, 0, true);
        $inactive = $this->makeProduct($category, $unit, $suffix . '-OFF', 0, 5, 10, 0, false);

        $response = $this->get(route('dashboard'));
        $response
            ->assertOk()
            ->assertSee('aria-label="Notifications, ' . ($existingAlertCount + 3) . ' reorder alerts"', false)
            ->assertSee(($existingAlertCount + 3) . ' alerts')
            ->assertDontSee('<h2>Reorder notification</h2>', false)
            ->assertSee('Products to reorder')
            ->assertSee('More (' . ($existingAlertCount + 1) . ')')
            ->assertSee('href="' . route('dashboard') . '#dashboard-reorder"', false)
            ->assertSee('4.00 RU')
            ->assertSee('Min 5.00')
            ->assertSee($low->product_code)
            ->assertSee($out->product_code)
            ->assertSee($more->product_code)
            ->assertDontSee($atMinimum->product_code)
            ->assertDontSee($aboveMinimum->product_code)
            ->assertDontSee($inactive->product_code)
            ->assertSee(route('reports.index', ['tab' => 'low-reorder']), false);

        $notificationList = substr(
            $response->getContent(),
            strpos($response->getContent(), '<ul class="dashboard-notification-list">')
        );
        $notificationList = substr($notificationList, 0, strpos($notificationList, '</ul>'));
        $this->assertSame(2, substr_count($notificationList, 'class="dashboard-notification-item"'));
    }

    public function test_dashboard_does_not_alert_for_a_healthy_product()
    {
        $suffix = strtoupper(Str::random(8));
        $category = Category::create(['name' => 'Healthy Category ' . $suffix, 'is_active' => true]);
        $unit = Unit::create(['name' => 'Healthy Unit ' . $suffix, 'short_name' => 'HU', 'is_active' => true]);
        $this->actingAs(User::create([
            'name' => 'Healthy Dashboard User',
            'email' => Str::uuid() . '@example.test',
            'password' => Hash::make('test-password'),
            'role' => 'ADMIN',
        ]));
        $existingAlertCount = Product::where('is_active', true)
            ->whereColumn('current_stock', '<', 'minimum_stock')
            ->count();
        $healthy = $this->makeProduct($category, $unit, $suffix . '-OK', 8, 5, 0, 0, true);

        $response = $this->get(route('dashboard'))->assertOk();
        $response->assertDontSee($healthy->product_code);

        if ($existingAlertCount > 0) {
            $response->assertSee('aria-label="Notifications, ' . $existingAlertCount . ' reorder alerts"', false);
        } else {
            $response->assertSee('aria-label="Notifications"', false)
                ->assertDontSee('<span class="dashboard-notification-count">', false)
                ->assertSee('You have no current notifications.');
        }
    }

    public function test_reorder_notification_is_available_on_other_app_pages()
    {
        $suffix = strtoupper(Str::random(8));
        $category = Category::create(['name' => 'Header Category ' . $suffix, 'is_active' => true]);
        $unit = Unit::create(['name' => 'Header Unit ' . $suffix, 'short_name' => 'HU', 'is_active' => true]);
        $this->actingAs(User::create([
            'name' => 'Header Notification User',
            'email' => Str::uuid() . '@example.test',
            'password' => Hash::make('test-password'),
            'role' => 'ADMIN',
        ]));
        $existingAlertCount = Product::where('is_active', true)
            ->whereColumn('current_stock', '<', 'minimum_stock')
            ->count();
        $product = $this->makeProduct($category, $unit, $suffix . '-LOW', 2, 5, 0, 0, true);

        $this->get(route('products.index'))
            ->assertOk()
            ->assertSee('data-notification-toggle', false)
            ->assertSee('aria-label="Notifications, ' . ($existingAlertCount + 1) . ' reorder alerts"', false)
            ->assertSee($product->product_code)
            ->assertSee(route('dashboard') . '#dashboard-reorder', false);
    }

    private function makeProduct(Category $category, Unit $unit, $code, $stock, $minimum, $reorderLevel, $reorderQuantity, $active)
    {
        $product = Product::create([
            'product_code' => 'REO-' . $code,
            'name' => 'Reorder Product ' . $code,
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'purchase_price' => 10,
            'selling_price' => 15,
            'minimum_stock' => $minimum,
            'reorder_level' => $reorderLevel,
            'reorder_quantity' => $reorderQuantity,
            'is_active' => $active,
        ]);
        $product->current_stock = $stock;
        $product->save();

        return $product;
    }
}
