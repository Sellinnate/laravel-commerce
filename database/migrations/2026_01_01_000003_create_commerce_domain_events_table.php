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
        Schema::create($this->prefix().'domain_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('tenant_id')->nullable()->index();
            $table->string('name')->index();
            $table->nullableMorphs('subject');
            $table->json('payload')->nullable();
            $table->nullableMorphs('actor');
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->prefix().'domain_events');
    }
};
