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

    public function test_expense_management_supports_crud_and_rejects_cashiers(): void
    {
        $adminToken = $this->bearerToken();

        $expense = $this->withToken($adminToken)
            ->postJson('/api/expenses', [
                'date' => '2026-07-25',
                'category' => 'Fuel',
                'amount' => 750.50,
                'note' => 'Delivery fuel',
            ])
            ->assertCreated()
            ->assertJsonPath('category', 'Fuel')
            ->assertJsonPath('amount', 750.5)
            ->assertJsonPath('note', 'Delivery fuel')
            ->json();

        $this->withToken($adminToken)
            ->patchJson("/api/expenses/{$expense['id']}", [
                'date' => '2026-07-26',
                'category' => 'Transportation',
                'amount' => 825.75,
                'note' => 'Updated delivery cost',
            ])
            ->assertOk()
            ->assertJsonPath('date', '2026-07-26')
            ->assertJsonPath('category', 'Transportation')
            ->assertJsonPath('amount', 825.75)
            ->assertJsonPath('note', 'Updated delivery cost');

        $this->withToken($adminToken)
            ->postJson('/api/expenses', [
                'date' => '2026-07-25',
                'category' => 'Invalid',
                'amount' => 0,
            ])
            ->assertUnprocessable();

        $cashier = $this->withToken($adminToken)
            ->postJson('/api/users', [
                'name' => 'Expense Cashier',
                'username' => 'expensecashier',
                'email' => 'expensecashier@example.test',
                'role' => 'cashier',
                'password' => 'secret123',
                'active' => true,
            ])
            ->assertCreated()
            ->json();

        $cashierToken = $this->postJson('/api/login', [
            'username' => $cashier['username'],
            'password' => 'secret123',
        ])->assertOk()->json('token');

        $this->withToken($cashierToken)
            ->getJson('/api/expenses')
            ->assertForbidden()
            ->assertJsonPath('message', 'You are not allowed to manage expenses.');

        $this->withToken($cashierToken)
            ->postJson('/api/expenses', [
                'date' => '2026-07-25',
                'category' => 'Supplies',
                'amount' => 100,
            ])
            ->assertForbidden();

        $this->withToken($adminToken)
            ->deleteJson("/api/expenses/{$expense['id']}")
            ->assertNoContent();

        $this->withToken($adminToken)
            ->getJson('/api/expenses')
            ->assertOk()
            ->assertJsonCount(0);
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
            ->assertJsonPath('price', 18)
            ->assertJsonCount(2, 'priceHistory')
            ->assertJsonPath('priceHistory.0.type', 'updated')
            ->assertJsonPath('priceHistory.1.type', 'created');

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

    public function test_cashiers_only_see_their_own_transactions_and_orders(): void
    {
        $adminToken = $this->bearerToken();

        $cashier = $this->withToken($adminToken)
            ->postJson('/api/users', [
                'name' => 'Cashier One',
                'username' => 'cashierone',
                'email' => 'cashierone@example.test',
                'role' => 'cashier',
                'password' => 'secret123',
                'active' => true,
            ])
            ->assertCreated()
            ->json();

        $cashierToken = $this->postJson('/api/login', [
            'username' => 'cashierone',
            'password' => 'secret123',
        ])->assertOk()->json('token');

        $adminTransaction = $this->withToken($adminToken)
            ->postJson('/api/transactions', [
                'paymentMethod' => 'cash',
                'customer' => ['name' => 'Admin Customer'],
                'items' => [[
                    'productId' => '',
                    'name' => 'Admin Manual Item',
                    'qty' => 1,
                    'unit' => 'pc',
                    'price' => 100,
                    'total' => 100,
                ]],
                'cash' => 100,
                'change' => 0,
            ])
            ->assertCreated()
            ->json('transaction');

        $this->withToken($cashierToken)
            ->postJson('/api/transactions', [
                'paymentMethod' => 'cash',
                'customer' => ['name' => 'Cashier Customer'],
                'items' => [[
                    'productId' => '',
                    'name' => 'Cashier Manual Item',
                    'qty' => 1,
                    'unit' => 'pc',
                    'price' => 50,
                    'total' => 50,
                ]],
                'cash' => 100,
                'change' => 50,
            ])
            ->assertCreated();

        $this->withToken($adminToken)
            ->postJson('/api/orders', [
                'customer' => ['name' => 'Admin Order'],
                'items' => [[
                    'productId' => '',
                    'name' => 'Admin Order Item',
                    'qty' => 1,
                    'unit' => 'pc',
                    'price' => 75,
                    'total' => 75,
                ]],
            ])
            ->assertCreated();

        $this->withToken($cashierToken)
            ->postJson('/api/orders', [
                'customer' => ['name' => 'Cashier Order'],
                'items' => [[
                    'productId' => '',
                    'name' => 'Cashier Order Item',
                    'qty' => 1,
                    'unit' => 'pc',
                    'price' => 60,
                    'total' => 60,
                ]],
            ])
            ->assertCreated();

        $this->withToken($cashierToken)
            ->getJson('/api/transactions')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.cashierId', $cashier['id'])
            ->assertJsonPath('0.customer.name', 'Cashier Customer');

        $this->withToken($cashierToken)
            ->getJson('/api/orders')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.cashierId', $cashier['id'])
            ->assertJsonPath('0.customer.name', 'Cashier Order');

        $this->withToken($cashierToken)
            ->postJson("/api/transactions/{$adminTransaction['id']}/void", [
                'voidReason' => 'Cashier should not be allowed',
            ])
            ->assertStatus(403)
            ->assertJsonPath('message', 'Cashier accounts are not allowed to void transactions.');
    }

    public function test_backend_recomputes_line_totals_and_subtotals_from_qty_and_price(): void
    {
        $token = $this->bearerToken();

        $transaction = $this->withToken($token)
            ->postJson('/api/transactions', [
                'paymentMethod' => 'cash',
                'items' => [[
                    'productId' => '',
                    'name' => 'Tampered Item',
                    'qty' => 2,
                    'unit' => 'pc',
                    'price' => 15,
                    'total' => 9999,
                ]],
                'cash' => 40,
                'change' => 9000,
            ])
            ->assertCreated()
            ->assertJsonPath('transaction.subtotal', 30)
            ->assertJsonPath('transaction.items.0.total', 30)
            ->assertJsonPath('transaction.cash', 40)
            ->assertJsonPath('transaction.change', 10)
            ->json('transaction');

        $order = $this->withToken($token)
            ->postJson('/api/orders', [
                'items' => [[
                    'productId' => '',
                    'name' => 'Tampered Order Item',
                    'qty' => 3,
                    'unit' => 'pc',
                    'price' => 12.5,
                    'total' => 1,
                ]],
            ])
            ->assertCreated()
            ->assertJsonPath('subtotal', 37.5)
            ->assertJsonPath('items.0.total', 37.5)
            ->json();

        $this->withToken($token)
            ->patchJson("/api/orders/{$order['id']}", [
                'items' => [[
                    'productId' => '',
                    'name' => 'Tampered Order Item',
                    'qty' => 4,
                    'unit' => 'pc',
                    'price' => 12.5,
                    'total' => 2,
                ]],
                'subtotal' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('subtotal', 50)
            ->assertJsonPath('items.0.total', 50);

        $this->withToken($token)
            ->postJson('/api/transactions', [
                'paymentMethod' => 'cash',
                'items' => [[
                    'productId' => '',
                    'name' => 'Insufficient Cash Item',
                    'qty' => 1,
                    'unit' => 'pc',
                    'price' => 50,
                    'total' => 50,
                ]],
                'cash' => 20,
                'change' => 0,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Cash received is not enough to cover the total amount.');

        $this->withToken($token)
            ->getJson('/api/transactions')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $transaction['id']);
    }
}
