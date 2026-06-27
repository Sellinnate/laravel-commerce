<?php

declare(strict_types=1);

namespace Selli\Commerce\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Selli\Commerce\CommerceServiceProvider;
use Selli\Commerce\Tests\Fixtures\Customer;
use Selli\Commerce\Tests\Fixtures\Product;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName): string => 'Selli\\Commerce\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );

        Relation::enforceMorphMap([
            'product' => Product::class,
            'customer' => Customer::class,
        ]);

        $this->setUpFixtureTables();
    }

    /**
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            CommerceServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    public function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    private function setUpFixtureTables(): void
    {
        if (! Schema::hasTable('products')) {
            Schema::create('products', function (Blueprint $table): void {
                $table->ulid('id')->primary();
                $table->string('name');
                $table->string('sku')->nullable();
                $table->bigInteger('price_cents')->default(0);
                $table->char('currency', 3)->default('EUR');
                $table->boolean('available')->default(true);
                $table->integer('stock')->nullable();
                $table->string('tax_category')->nullable();
                $table->json('data')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('customers')) {
            Schema::create('customers', function (Blueprint $table): void {
                $table->ulid('id')->primary();
                $table->string('name')->nullable();
                $table->timestamps();
            });
        }
    }
}
