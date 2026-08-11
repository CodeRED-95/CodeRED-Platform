<?php

namespace App\Services\ApiTokens;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Encryption\Encrypter as ConcreteEncrypter;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class TokenVaultService
{
    private Encrypter $encrypter;

    private string $blindIndexKey;

    public function __construct()
    {
        $key = Config::get('encryption.token_request_data_key');
        $cipher = Config::get('app.cipher');

        if (empty($key)) {
            throw new \RuntimeException('Token request data encryption key is not configured in config/encryption.php or .env.');
        }

        if (Str::startsWith($key, 'base64:')) {
            $key = base64_decode(substr($key, 7));
        }

        // Validar la longitud de la clave para el cifrador configurado
        if (! ConcreteEncrypter::supported($key, $cipher)) {
            $length = mb_strlen($key, '8bit');
            throw new \LengthException(
                "The encryption key length of {$length} bytes is invalid for the {$cipher} cipher. ".
                "Please generate a valid key using 'php artisan key:generate --show' and ensure it is 32 bytes long for AES-256-CBC."
            );
        }

        $this->encrypter = new ConcreteEncrypter($key, $cipher);

        $indexKey = Config::get('encryption.token_request_blind_index_key');
        if (! is_string($indexKey) || empty($indexKey)) {
            throw new \RuntimeException('Token request blind index key is not configured in config/encryption.php or .env.');
        }
        $this->blindIndexKey = $indexKey;
    }

    public function encrypt(string $value): string
    {
        return $this->encrypter->encrypt($value);
    }

    public function decrypt(string $encryptedValue): string
    {
        return $this->encrypter->decrypt($encryptedValue);
    }

    /**
     * Descifra un token guardado en `token_ciphertext`.
     *
     * Antes de unificar la columna, algunos flujos (rotación desde el panel y la
     * integración n8n) cifraban con `Crypt`, es decir con APP_KEY, mientras que
     * el resto usaba la clave dedicada del vault. Ambos formatos son idénticos,
     * así que se intenta primero la clave del vault y se recurre a APP_KEY para
     * los registros heredados.
     */
    public function decryptToken(string $encryptedValue): string
    {
        try {
            return $this->encrypter->decrypt($encryptedValue);
        } catch (DecryptException $e) {
            return Crypt::decryptString($encryptedValue);
        }
    }

    public function generateBlindIndex(string $email): string
    {
        $normalizedEmail = strtolower(trim($email));

        return hash_hmac('sha256', $normalizedEmail, $this->blindIndexKey);
    }
}
