<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $table = 'application_settings';

    public $timestamps = true;

    protected $fillable = ['key', 'value', 'group', 'is_public', 'is_encrypted'];

    protected function casts(): array
    {
        return ['is_public' => 'boolean', 'is_encrypted' => 'boolean'];
    }
}
