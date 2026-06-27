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

        Schema::create($prefix.'carts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('tenant_id')->nullable()->index();
            $table->nullableMorphs('owner');
            $table->char('currency', 3);
            $table->string('status')->default('active')->index();
            $table->json('metadata')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();

            $table->index(['owner_type', 'owner_id', 'tenant_id', 'status'], 'cart_owner_lookup');
        });

        Schema::create($prefix.'cart_items', function (Blueprint $table) use ($prefix): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('cart_id')->constrained($prefix.'carts')->cascadeOnDelete();
            $table->string('purchasable_type');
            $table->string('purchasable_id');
            $table->string('name');
            $table->unsignedInteger('quantity');
            $table->bigInteger('unit_price_amount');
            $table->char('unit_price_currency', 3);
            $table->json('options')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['purchasable_type', 'purchasable_id']);
        });
    }

    public function down(): void
    {
        $prefix = $this->prefix();

        Schema::dropIfExists($prefix.'cart_items');
        Schema::dropIfExists($prefix.'carts');
    }
};
