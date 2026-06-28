<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function prefix(): string
    {
        return Config::string('commerce.table_prefix', 'commerce_');
    }

    public function up(): void
    {
        Schema::create($this->prefix().'warehouses', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('tenant_id')->nullable()->index();
            $table->string('code');
            $table->string('name');
            // Lower priority number is preferred when allocating stock.
            $table->integer('priority')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
        });

        Schema::create($this->prefix().'stock_items', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('tenant_id')->nullable()->index();
            $table->foreignUlid('warehouse_id');
            $table->string('purchasable_type');
            $table->string('purchasable_id');
            // Materialised projection of the ledger: on_hand is the counted
            // quantity, reserved is held against active reservations. ATP is
            // on_hand − reserved. Both are signed so a backorder can drive
            // on_hand below zero when the policy allows it.
            $table->integer('on_hand')->default(0);
            $table->integer('reserved')->default(0);
            // Per-item override of the global backorder policy: null = inherit.
            $table->boolean('allow_backorder')->nullable();
            // Optimistic-lock counter, bumped on every write.
            $table->unsignedBigInteger('version')->default(0);
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'warehouse_id', 'purchasable_type', 'purchasable_id'],
                'stock_item_unique'
            );
            $table->index(['purchasable_type', 'purchasable_id'], 'stock_item_purchasable');
        });

        Schema::create($this->prefix().'stock_movements', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('tenant_id')->nullable()->index();
            $table->foreignUlid('warehouse_id');
            $table->string('purchasable_type');
            $table->string('purchasable_id');
            // receipt | adjustment | reservation | release | shipment
            $table->string('type');
            // Signed quantity moved (e.g. −2 on a shipment).
            $table->integer('quantity');
            $table->string('reason')->nullable();
            // What caused the movement (a cart, an order, a manual action).
            $table->string('reference_type')->nullable();
            $table->string('reference_id')->nullable();
            // Append-only: a created_at only, never updated.
            $table->timestamp('created_at')->nullable();

            $table->index(['purchasable_type', 'purchasable_id'], 'stock_movement_purchasable');
            $table->index(['reference_type', 'reference_id'], 'stock_movement_reference');
        });

        Schema::create($this->prefix().'stock_reservations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('tenant_id')->nullable()->index();
            $table->foreignUlid('warehouse_id');
            $table->string('purchasable_type');
            $table->string('purchasable_id');
            $table->integer('quantity');
            // active | released | consumed
            $table->string('status')->default('active');
            // Who holds it (a cart while shopping, an order once placed).
            $table->string('reference_type')->nullable();
            $table->string('reference_id')->nullable();
            // When the hold lapses; null never expires.
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['reference_type', 'reference_id'], 'stock_reservation_reference');
            $table->index(['status', 'expires_at'], 'stock_reservation_expiry');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->prefix().'stock_reservations');
        Schema::dropIfExists($this->prefix().'stock_movements');
        Schema::dropIfExists($this->prefix().'stock_items');
        Schema::dropIfExists($this->prefix().'warehouses');
    }
};
