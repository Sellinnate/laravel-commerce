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

        Schema::create($prefix.'price_books', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('tenant_id')->nullable()->index();
            $table->string('name');
            $table->char('currency', 3);
            $table->string('segment')->nullable();
            $table->integer('priority')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'currency', 'segment', 'active'], 'price_book_lookup');
        });

        Schema::create($prefix.'prices', function (Blueprint $table) use ($prefix): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('price_book_id')->constrained($prefix.'price_books')->cascadeOnDelete();
            $table->string('purchasable_type');
            $table->string('purchasable_id');
            $table->bigInteger('amount');
            $table->char('currency', 3);
            $table->unsignedInteger('min_quantity')->default(1);
            $table->timestamps();

            $table->index(['purchasable_type', 'purchasable_id', 'min_quantity'], 'price_lookup');
        });

        Schema::create($prefix.'coupons', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('tenant_id')->nullable()->index();
            $table->string('code');
            $table->string('type'); // percentage | fixed
            $table->bigInteger('value'); // percent (0-100) or minor amount
            $table->char('currency', 3)->nullable(); // for fixed coupons
            $table->bigInteger('min_amount')->nullable();
            $table->char('min_amount_currency', 3)->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('per_customer_limit')->nullable();
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
        });

        Schema::create($prefix.'coupon_redemptions', function (Blueprint $table) use ($prefix): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('coupon_id')->constrained($prefix.'coupons')->cascadeOnDelete();
            $table->string('tenant_id')->nullable()->index();
            $table->nullableMorphs('customer');
            $table->ulid('order_id')->nullable()->index();
            $table->bigInteger('amount')->default(0);
            $table->char('currency', 3)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['coupon_id', 'customer_type', 'customer_id'], 'redemption_customer');
        });

        Schema::create($prefix.'promotions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('tenant_id')->nullable()->index();
            $table->string('name');
            $table->integer('priority')->default(0);
            $table->string('stacking')->default('cumulative'); // exclusive | cumulative | best_of
            $table->json('conditions')->nullable();
            $table->json('actions')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'active', 'priority'], 'promotion_lookup');
        });

        Schema::create($prefix.'gift_cards', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('tenant_id')->nullable()->index();
            $table->string('code');
            $table->bigInteger('initial_amount');
            $table->bigInteger('balance');
            $table->char('currency', 3);
            $table->boolean('active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
        });

        Schema::create($prefix.'gift_card_transactions', function (Blueprint $table) use ($prefix): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('gift_card_id')->constrained($prefix.'gift_cards')->cascadeOnDelete();
            $table->string('tenant_id')->nullable()->index();
            $table->string('type'); // issue | redeem | refund
            $table->bigInteger('amount');
            $table->char('currency', 3);
            $table->ulid('order_id')->nullable()->index();
            $table->timestamp('created_at')->nullable();

            $table->index(['gift_card_id', 'created_at']);
        });
    }

    public function down(): void
    {
        $prefix = $this->prefix();

        Schema::dropIfExists($prefix.'gift_card_transactions');
        Schema::dropIfExists($prefix.'gift_cards');
        Schema::dropIfExists($prefix.'promotions');
        Schema::dropIfExists($prefix.'coupon_redemptions');
        Schema::dropIfExists($prefix.'coupons');
        Schema::dropIfExists($prefix.'prices');
        Schema::dropIfExists($prefix.'price_books');
    }
};
