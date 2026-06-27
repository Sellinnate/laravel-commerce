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
        $prefix = $this->prefix();

        Schema::create($prefix.'orders', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('tenant_id')->nullable()->index();
            $table->string('number')->unique();
            $table->nullableMorphs('customer');
            $table->char('currency', 3);
            $table->string('state')->index();

            foreach (['subtotal', 'discount_total', 'tax_total', 'shipping_total', 'grand_total'] as $money) {
                $table->bigInteger($money.'_amount')->default(0);
                $table->char($money.'_currency', 3)->nullable();
            }

            $table->json('billing_address')->nullable();
            $table->json('shipping_address')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('placed_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_type', 'customer_id', 'tenant_id'], 'order_customer_lookup');
            $table->index(['tenant_id', 'state', 'placed_at'], 'order_state_lookup');
        });

        Schema::create($prefix.'order_lines', function (Blueprint $table) use ($prefix): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('order_id')->constrained($prefix.'orders')->cascadeOnDelete();
            $table->string('purchasable_type');
            $table->string('purchasable_id');
            $table->string('name');
            $table->string('sku')->nullable();
            $table->unsignedInteger('quantity');

            foreach (['unit_price', 'line_subtotal', 'discount_total', 'tax_total', 'line_total'] as $money) {
                $table->bigInteger($money.'_amount')->default(0);
                $table->char($money.'_currency', 3)->nullable();
            }

            $table->json('snapshot')->nullable();
            $table->json('tax_detail')->nullable();
            $table->json('discount_detail')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['purchasable_type', 'purchasable_id']);
        });

        Schema::create($prefix.'order_state_transitions', function (Blueprint $table) use ($prefix): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('order_id')->constrained($prefix.'orders')->cascadeOnDelete();
            $table->string('tenant_id')->nullable()->index();
            $table->string('from_state')->nullable();
            $table->string('to_state');
            $table->nullableMorphs('actor');
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        $prefix = $this->prefix();

        Schema::dropIfExists($prefix.'order_state_transitions');
        Schema::dropIfExists($prefix.'order_lines');
        Schema::dropIfExists($prefix.'orders');
    }
};
