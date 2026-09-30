<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('master_items', function (Blueprint $table) {
            // Path relatif terhadap disk 'public' (storage/app/public), bukan URL penuh.
            $table->string('foto')->nullable()->after('jenis');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('master_items', function (Blueprint $table) {
            $table->dropColumn('foto');
        });
    }
};
