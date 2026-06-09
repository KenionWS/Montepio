<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name', 150);
            $table->string('slug', 180)->unique();
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->integer('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('seo_title', 180)->nullable();
            $table->string('seo_description', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('attribute_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 150)->unique();
            $table->integer('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('attributes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('group_id')->nullable()->constrained('attribute_groups')->nullOnDelete();
            $table->string('name', 120);
            $table->string('slug', 150)->unique();
            $table->enum('type', ['text', 'number', 'boolean', 'single_select', 'multi_select']);
            $table->boolean('is_filterable')->default(false);
            $table->boolean('is_visible_on_product')->default(true);
            $table->integer('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('attribute_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attribute_id')->constrained('attributes')->cascadeOnDelete();
            $table->string('value', 150);
            $table->string('slug', 180);
            $table->integer('position')->default(0);
            $table->timestamps();
            $table->unique(['attribute_id', 'slug']);
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('sku', 80)->unique();
            $table->string('title');
            $table->string('slug', 280)->unique();
            $table->string('short_description', 500)->nullable();
            $table->mediumText('description_html')->nullable();
            $table->boolean('is_for_sale')->default(false);
            $table->decimal('price', 12, 2)->nullable();
            $table->boolean('is_price_visible')->default(false);
            $table->boolean('is_for_rent')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_published')->default(true);
            $table->enum('stock_status', ['available', 'reserved', 'sold', 'hidden'])->default('available');
            $table->foreignId('main_category_id')->constrained('categories');
            $table->text('search_text')->nullable();
            $table->integer('sort_score')->default(0);
            $table->timestamps();
            $table->index(['main_category_id', 'is_published', 'is_for_sale', 'is_featured'], 'idx_products_listing');
        });

        Schema::create('product_category', function (Blueprint $table): void {
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->integer('position')->default(0);
            $table->primary(['product_id', 'category_id']);
        });

        Schema::create('product_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('path_original');
            $table->string('path_large')->nullable();
            $table->string('path_medium')->nullable();
            $table->string('path_thumb')->nullable();
            $table->string('alt_text')->nullable();
            $table->integer('position')->default(0);
            $table->boolean('is_cover')->default(false);
            $table->timestamps();
        });

        Schema::create('product_attribute_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained('attributes')->cascadeOnDelete();
            $table->foreignId('attribute_option_id')->nullable()->constrained('attribute_options')->nullOnDelete();
            $table->string('value_text', 255)->nullable();
            $table->decimal('value_number', 12, 2)->nullable();
            $table->boolean('value_boolean')->nullable();
            $table->timestamps();
            $table->index(['attribute_id', 'attribute_option_id', 'product_id'], 'idx_attribute_filters');
        });

        Schema::create('imports', function (Blueprint $table): void {
            $table->id();
            $table->string('file_name');
            $table->string('file_path');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('error_rows')->default(0);
            $table->json('summary')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imports');
        Schema::dropIfExists('product_attribute_values');
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('product_category');
        Schema::dropIfExists('products');
        Schema::dropIfExists('attribute_options');
        Schema::dropIfExists('attributes');
        Schema::dropIfExists('attribute_groups');
        Schema::dropIfExists('categories');
    }
};
