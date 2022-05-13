<?php

namespace Elastico\Console;

use Elastico\Scripting\Painless;
use Illuminate\Console\Command;

class IndexPainless extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'elastico:painless';

    /**
     * The console command description.
     */
    protected $description = 'Index painless scripts';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Painless::indexScripts();
    }
}
