<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dni_records', fn (Blueprint $t) => $t->string('ubigeo', 6)->nullable()->index());
    }

    public function down(): void
    {
        Schema::table('dni_records', fn (Blueprint $t) => $t->dropColumn('ubigeo'));
    }
};
