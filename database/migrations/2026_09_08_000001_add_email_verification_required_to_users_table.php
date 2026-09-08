<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // False conserva el acceso de cuentas creadas antes del OTP. Las
            // cuentas nuevas lo activan explícitamente durante el registro.
            $table->boolean('email_verification_required')->default(false)->after('email_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('email_verification_required');
        });
    }
};
