<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_token_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('api_token_requests', 'delivery_email')) {
                $table->text('delivery_email')->nullable()->after('delivered_to');
            }
            if (! Schema::hasColumn('api_token_requests', 'delivery_telegram_username')) {
                $table->text('delivery_telegram_username')->nullable()->after('delivery_email');
            }
            if (! Schema::hasColumn('api_token_requests', 'delivery_whatsapp_number')) {
                $table->text('delivery_whatsapp_number')->nullable()->after('delivery_telegram_username');
            }
            if (! Schema::hasColumn('api_token_requests', 'delivery_email_masked')) {
                $table->string('delivery_email_masked')->nullable()->after('delivery_whatsapp_number');
            }
            if (! Schema::hasColumn('api_token_requests', 'delivery_telegram_username_masked')) {
                $table->string('delivery_telegram_username_masked')->nullable()->after('delivery_email_masked');
            }
            if (! Schema::hasColumn('api_token_requests', 'delivery_whatsapp_number_masked')) {
                $table->string('delivery_whatsapp_number_masked')->nullable()->after('delivery_telegram_username_masked');
            }
            if (! Schema::hasColumn('api_token_requests', 'delivered_by')) {
                $table->foreignId('delivered_by')->nullable()->after('delivered_at')->constrained('users')->nullOnDelete();
            }
        });

        DB::table('api_token_requests')->orderBy('id')->select(['id', 'requester_email', 'requester_phone', 'telegram_username'])->chunkById(100, function ($rows): void {
            foreach ($rows as $row) {
                $email = is_string($row->requester_email) && $row->requester_email !== '' ? $row->requester_email : null;
                $telegram = is_string($row->telegram_username) && $row->telegram_username !== '' ? $row->telegram_username : null;
                $whatsapp = is_string($row->requester_phone) && $row->requester_phone !== '' ? $row->requester_phone : null;

                DB::table('api_token_requests')->where('id', $row->id)->update([
                    'delivery_email' => $email === null ? null : Crypt::encryptString($email),
                    'delivery_telegram_username' => $telegram === null ? null : Crypt::encryptString($telegram),
                    'delivery_whatsapp_number' => $whatsapp === null ? null : Crypt::encryptString($whatsapp),
                    'delivery_email_masked' => $email === null ? null : $this->maskEmail($email),
                    'delivery_telegram_username_masked' => $telegram === null ? null : $this->maskTelegram($telegram),
                    'delivery_whatsapp_number_masked' => $whatsapp === null ? null : $this->maskPhone($whatsapp),
                    'requester_email' => $email === null ? null : $this->maskEmail($email),
                    'requester_phone' => $whatsapp === null ? null : $this->maskPhone($whatsapp),
                    'telegram_username' => $telegram === null ? null : $this->maskTelegram($telegram),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('api_token_requests', function (Blueprint $table): void {
            if (Schema::hasColumn('api_token_requests', 'delivered_by')) {
                $table->dropConstrainedForeignId('delivered_by');
            }
            $table->dropColumn([
                'delivery_email',
                'delivery_telegram_username',
                'delivery_whatsapp_number',
                'delivery_email_masked',
                'delivery_telegram_username_masked',
                'delivery_whatsapp_number_masked',
            ]);
        });
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', trim($email), 2), 2, '');
        if ($domain === '') {
            return '***';
        }

        return mb_substr($local, 0, 1).'***@'.$domain;
    }

    private function maskTelegram(string $username): string
    {
        $value = '@'.ltrim(trim($username), '@');

        return mb_substr($value, 0, 2).str_repeat('*', max(3, mb_strlen($value) - 3)).mb_substr($value, -1);
    }

    private function maskPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: '';
        if ($digits === '') {
            return '***';
        }
        $prefix = str_starts_with($digits, '51') ? '+51 ' : '+';

        return $prefix.'******'.substr($digits, -3);
    }
};
