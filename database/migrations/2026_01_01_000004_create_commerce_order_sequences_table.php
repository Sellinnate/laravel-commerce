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
        Schema::create($this->prefix().'order_sequences', function (Blueprint $table): void {
            // Per-tenant counter keyed by a never-null discriminator so that
            // sequential order numbers are unique even in single-tenant mode
            // (where tenant_id is null). Incremented under a row lock.
            $table->string('tenant_key')->primary();
            $table->string('tenant_id')->nullable();
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->prefix().'order_sequences');
    }
};
