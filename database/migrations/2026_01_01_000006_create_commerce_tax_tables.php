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
        Schema::create($this->prefix().'tax_rates', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('tenant_id')->nullable()->index();
            $table->string('category'); // standard | reduced | exempt | ...
            $table->char('country', 2);
            $table->string('region')->nullable();
            $table->string('name');
            // Rate in basis points: 2200 = 22.00%.
            $table->integer('rate');
            $table->integer('priority')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'category', 'country', 'active'], 'tax_rate_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->prefix().'tax_rates');
    }
};
