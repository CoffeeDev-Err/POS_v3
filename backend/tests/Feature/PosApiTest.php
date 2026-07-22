<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PosApiTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): void
    {
        DB::table('users')->insert([
            'name' => 'Test Owner',
            'username' => 'owner',
            'username_normalized' => 'owner',
            'email' => 'owner@example.test',
            'role' => 'superadmin',
            'active' => true,
            'password' => Hash::make('owner123'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('settings')->insert([
            'key' => 'global',
            'value' => json_encode([
                'storeName' => 'Test Store',
                'address' => 'Test Address',
                'phone' => '123',
                'receiptFooter' => 'Thanks',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('counters')->insert([
            'name' => 'orNumber',
            'count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function bearerToken(): string
    {
        $this->createAdmin();

        return $this->postJson('/api/login', [
            'username' => 'owner',
            'password' => 'owner123',
        ])->assertOk()->json('token');
    }

    public function test_protected_api_routes_require_authentication(): void
    {
        $this->getJson('/api/me')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Not authenticated.');
    }

    public function test_auth_and_current_user_work(): void
    {
        $token = $this->bearerToken();

        $this->withToken($token)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.username', 'owner')
            ->assertJsonPath('user.role', 'superadmin');
    }

    public function test_login_returns_specific_messages_for_missing_and_deactivated_accounts(): void
    {
        $this->createAdmin();

        DB::table('users')->insert([
            'name' => 'Disabled Admin',
            'username' => 'admin',
            'username_normalized' => 'admin',
            'email' => 'admin@example.test',
            'role' => 'admin',
            'active' => false,
            'password' => Hash::make('admin123'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/api/login', [
            'username' => 'missing-user',
            'password' => 'whatever123',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'No account was found for that username.');

        $this->postJson('/api/login', [
            'username' => 'admin',
            'password' => 'admin123',
        ])->assertStatus(403)
            ->assertJsonPath('message', 'This account has been deactivated. Contact your administrator.');
    }

    public function test_login_is_temporarily_throttled_after_five_failed_attempts(): void
    {
        $this->createAdmin();

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $this->postJson('/api/login', [
                'username' => 'owner',
                'password' => 'wrong-password',
            ])->assertUnauthorized();
        }

        $this->postJson('/api/login', [
            'username' => 'owner',
            'password' => 'wrong-password',
        ])->assertStatus(429)
            ->assertJsonPath('message', 'Too many failed sign-in attempts. Please wait before trying again.')
            ->assertJsonStructure(['retryAfterSeconds']);
    }

    public function test_user_account_crud_and_password_change_work(): void
    {
        $token = $this->bearerToken();

        $createdUser = $this->withToken($token)
            ->postJson('/api/users', [
                'name' => 'Staff One',
                'username' => 'staffone',
                'email' => 'staffone@example.test',
                'role' => 'admin',
                'password' => 'secret123',
                'active' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('name', 'Staff One')
            ->assertJsonPath('role', 'admin')
            ->json();

        $this->withToken($token)
            ->patchJson("/api/users/{$createdUser['id']}", [
                'name' => 'Staff One Updated',
                'username' => 'staffone',
                'email' => 'staffone.updated@example.test',
                'role' => 'cashier',
            ])
            ->assertOk()
            ->assertJsonPath('name', 'Staff One Updated')
            ->assertJsonPath('role', 'cashier')
            ->assertJsonPath('email', 'staffone.updated@example.test');

        $this->withToken($token)
            ->patchJson("/api/users/{$createdUser['id']}/status", ['active' => false])
            ->assertOk()
            ->assertJsonPath('active', false);

        $this->withToken($token)
            ->deleteJson("/api/users/{$createdUser['id']}")
            ->assertNoContent();

        $this->withToken($token)
            ->getJson('/api/users')
            ->assertOk()
            ->assertJsonCount(1);

        $this->withToken($token)
            ->postJson('/api/change-password', [
                'currentPassword' => 'owner123',
                'newPassword' => 'owner456',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->postJson('/api/login', [
            'username' => 'owner',
            'password' => 'owner123',
        ])->assertUnauthorized();

        $this->postJson('/api/login', [
            'username' => 'owner',
            'password' => 'owner456',
        ])->assertOk()->assertJsonPath('user.username', 'owner');
    }

    public function test_category_and_product_delete_flows_work(): void
    {
        $token = $this->bearerToken();

        $this->withToken($token)
            ->postJson('/api/categories', ['name' => 'Disposable'])
            ->assertCreated();

        $product = $this->withToken($token)
            ->postJson('/api/products', [
                'name' => 'Disposable Item',
                'category' => 'Disposable',
                'price' => 12,
                'unit' => 'pc',
                'stock' => 8,
                'lowStockAlert' => 2,
            ])
            ->assertCreated()
            ->json();

        $this->withToken($token)
            ->deleteJson("/api/products/{$product['id']}")
            ->assertNoContent();

        $this->withToken($token)
            ->getJson('/api/products')
            ->assertOk()
            ->assertJsonCount(0);

        $productToCascadeDelete = $this->withToken($token)
            ->postJson('/api/products', [
                'name' => 'Cascade Item',
                'category' => 'Disposable',
                'price' => 22,
                'unit' => 'pc',
                'stock' => 5,
                'lowStockAlert' => 1,
            ])
            ->assertCreated()
            ->json();

        $this->withToken($token)
            ->deleteJson('/api/categories/Disposable?deleteProducts=1')
            ->assertNoContent();

        $this->withToken($token)
            ->getJson('/api/categories')
            ->assertOk()
            ->assertJsonCount(0);

        $this->withToken($token)
            ->getJson('/api/products')
            ->assertOk()
            ->assertJsonMissing(['id' => $productToCascadeDelete['id']]);
    }

    public function test_direct_credit_creation_and_order_lock_conflict_work(): void
    {
        $token = $this->bearerToken();

        $createdCredit = $this->withToken($token)
            ->postJson('/api/credits', [
                'transactionId' => null,
                'orNumber' => '0000000123',
                'customerName' => 'Walk-in Credit',
                'items' => [['name' => 'Sample', 'qty' => 1, 'price' => 50, 'total' => 50]],
                'totalAmount' => 50,
                'dueDate' => now()->addDays(10)->format('Y-m-d'),
                'cashierId' => '1',
                'cashierName' => 'Test Owner',
            ])
            ->assertCreated()
            ->assertJsonPath('customerName', 'Walk-in Credit')
            ->assertJsonPath('remainingBalance', 50)
            ->json();

        $this->withToken($token)
            ->postJson("/api/credits/{$createdCredit['id']}/payments", [
                'amount' => 25,
                'note' => 'Half payment',
            ])
            ->assertOk()
            ->assertJsonPath('amountPaid', 25)
            ->assertJsonPath('remainingBalance', 25);

        $order = $this->withToken($token)
            ->postJson('/api/orders', [
                'cashierId' => '1',
                'customer' => ['name' => 'Lock Test'],
                'items' => [[
                    'productId' => '',
                    'name' => 'Manual Item',
                    'qty' => 1,
                    'unit' => 'pc',
                    'price' => 10,
                    'total' => 10,
                ]],
            ])
            ->assertCreated()
            ->json();

        $this->withToken($token)
            ->postJson("/api/orders/{$order['id']}/lock", [
                'actor' => ['id' => '1', 'name' => 'Test Owner'],
                'ttlMinutes' => 5,
            ])
            ->assertOk();

        $this->withToken($token)
            ->postJson("/api/orders/{$order['id']}/lock", [
                'actor' => ['id' => '2', 'name' => 'Another User'],
                'ttlMinutes' => 5,
            ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'This order is currently being edited by Test Owner. Please try again shortly.');
    }

    public function test_catalog_inventory_pos_orders_credits_and_reports_endpoints_work(): void
    {
        $token = $this->bearerToken();

        $this->withToken($token)
            ->patchJson('/api/settings', ['storeName' => 'Updated Store'])
            ->assertOk()
            ->assertJsonPath('storeName', 'Updated Store');

        $this->withToken($token)
            ->postJson('/api/categories', ['name' => 'Test Category'])
            ->assertCreated()
            ->assertJsonPath('name', 'Test Category');

        $product = $this->withToken($token)
            ->postJson('/api/products', [
                'name' => 'Test Product',
                'category' => 'Test Category',
                'price' => 15,
                'unit' => 'pc',
                'stock' => 20,
                'lowStockAlert' => 5,
            ])
            ->assertCreated()
            ->assertJsonPath('name', 'Test Product')
            ->json();

        $this->withToken($token)
            ->patchJson("/api/products/{$product['id']}", ['price' => 18])
            ->assertOk()
            ->assertJsonPath('price', 18);

        $this->withToken($token)
            ->postJson('/api/stock-movements', [
                'productId' => $product['id'],
                'productName' => 'Test Product',
                'type' => 'stock-in',
                'qty' => 5,
            ])
            ->assertCreated()
            ->assertJsonPath('product.stock', 25);

        $this->withToken($token)
            ->postJson('/api/expenses', [
                'date' => now()->format('Y-m-d'),
                'name' => 'Test Expense',
                'category' => 'Supplies',
                'amount' => 99.5,
            ])
            ->assertCreated()
            ->assertJsonPath('name', 'Test Expense');

        $this->withToken($token)
            ->postJson('/api/audit-logs', [
                'user' => 'Test Owner',
                'action' => 'Smoke test action',
            ])
            ->assertCreated();

        $cashier = $this->withToken($token)
            ->postJson('/api/users', [
                'name' => 'Cashier One',
                'username' => 'cashierone',
                'email' => 'cashier@example.test',
                'role' => 'cashier',
                'password' => 'secret123',
                'active' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('username', 'cashierone')
            ->json();

        $this->withToken($token)
            ->patchJson("/api/users/{$cashier['id']}/status", ['active' => false])
            ->assertOk()
            ->assertJsonPath('active', false);

        $transactionResponse = $this->withToken($token)
            ->postJson('/api/transactions', [
                'cashierId' => '1',
                'paymentMethod' => 'credit',
                'customer' => [
                    'name' => 'Credit Customer',
                    'contact' => '09170000000',
                    'address' => 'Test Address',
                ],
                'dueDate' => now()->addWeek()->format('Y-m-d'),
                'items' => [[
                    'productId' => $product['id'],
                    'name' => 'Test Product',
                    'qty' => 3,
                    'unit' => 'pc',
                    'price' => 18,
                    'total' => 54,
                    'conversionRate' => 1,
                ]],
                'cash' => 0,
                'change' => 0,
            ])
            ->assertCreated()
            ->assertJsonPath('transaction.orNumber', '0000000001')
            ->assertJsonPath('updatedProducts.0.stock', 22)
            ->assertJsonPath('credit.customerName', 'Credit Customer')
            ->json();

        $creditId = $transactionResponse['credit']['id'];

        $this->withToken($token)
            ->postJson("/api/credits/{$creditId}/payments", [
                'amount' => 20,
                'note' => 'Partial payment',
            ])
            ->assertOk()
            ->assertJsonPath('amountPaid', 20)
            ->assertJsonPath('remainingBalance', 34);

        $this->withToken($token)
            ->patchJson("/api/credits/{$creditId}/due-date", [
                'dueDate' => now()->addWeeks(2)->format('Y-m-d'),
            ])
            ->assertOk();

        $order = $this->withToken($token)
            ->postJson('/api/orders', [
                'cashierId' => '1',
                'customer' => ['name' => 'Order Customer'],
                'items' => [[
                    'productId' => $product['id'],
                    'name' => 'Test Product',
                    'qty' => 1,
                    'unit' => 'pc',
                    'price' => 18,
                    'total' => 18,
                ]],
            ])
            ->assertCreated()
            ->assertJsonPath('status', 'pending')
            ->json();

        $this->withToken($token)
            ->postJson("/api/orders/{$order['id']}/lock", [
                'actor' => ['id' => '1', 'name' => 'Test Owner'],
                'ttlMinutes' => 5,
            ])
            ->assertOk()
            ->assertJsonPath('editLock.byId', '1');

        $this->withToken($token)
            ->patchJson("/api/orders/{$order['id']}", [
                'status' => 'onprocess',
                '__actor' => ['id' => '1', 'name' => 'Test Owner'],
            ])
            ->assertOk()
            ->assertJsonPath('status', 'onprocess');

        $this->withToken($token)
            ->deleteJson("/api/orders/{$order['id']}/lock", [
                'actor' => ['id' => '1', 'name' => 'Test Owner'],
            ])
            ->assertOk();

        $transactionId = $transactionResponse['transaction']['id'];

        $this->withToken($token)
            ->postJson("/api/transactions/{$transactionId}/void", [
                'voidReason' => 'Smoke test void',
            ])
            ->assertOk()
            ->assertJsonPath('transaction.status', 'void')
            ->assertJsonPath('updatedProducts.0.stock', 25);

        $this->withToken($token)
            ->postJson('/api/migrate-or-numbers')
            ->assertOk()
            ->assertContent('1');

        $this->withToken($token)->getJson('/api/products')->assertOk()->assertJsonCount(1);
        $this->withToken($token)->getJson('/api/categories')->assertOk()->assertJsonFragment(['Test Category']);
        $this->withToken($token)->getJson('/api/transactions')->assertOk()->assertJsonCount(1);
        $this->withToken($token)->getJson('/api/orders')->assertOk()->assertJsonCount(1);
        $this->withToken($token)->getJson('/api/credits')->assertOk()->assertJsonCount(1);
        $this->withToken($token)->getJson('/api/expenses')->assertOk()->assertJsonCount(1);
        $this->withToken($token)->getJson('/api/audit-logs')->assertOk()->assertJsonCount(1);
        $this->withToken($token)->getJson('/api/stock-movements')->assertOk()->assertJsonCount(1);
    }
}
