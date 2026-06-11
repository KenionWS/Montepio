<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_display_parents', function (Blueprint $table): void {
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->foreignId('parent_category_id')->constrained('categories')->cascadeOnDelete();
            $table->primary(['category_id', 'parent_category_id']);
            $table->index('parent_category_id', 'idx_category_display_parents_parent');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_display_parents');
    }
};
