<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pivot many-to-many: kategori_items <-> master_items.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('kategori_master_item', function (Blueprint $table) {
            $table->id();
            // Nama kolom mengikuti konvensi belongsToMany: snake_case nama class + _id.
            $table->foreignId('kategori_item_id')->constrained('kategori_items')->cascadeOnDelete();
            $table->foreignId('master_item_id')->constrained('master_items')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['kategori_item_id', 'master_item_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('kategori_master_item');
    }
};
