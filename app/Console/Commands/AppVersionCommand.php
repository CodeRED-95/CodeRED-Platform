<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class AppVersionCommand extends Command
{
    protected $signature = 'app:version';

    protected $description = 'Muestra la version actual de CodeRED Platform.';

    public function handle(): int
    {
        $this->line((string) config('version.current'));

        return self::SUCCESS;
    }
}
