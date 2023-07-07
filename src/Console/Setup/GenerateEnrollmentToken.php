<?php

namespace Elastico\Console\Setup;

use Spatie\Ssh\Ssh;
use Illuminate\Console\Command;
use Elastico\Actions\Setup\TokenGenerator;

class GenerateEnrollmentToken extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elastic:elasticsearch:token  
                                {ip : The SSH IP Adress} 
                                {--user=root : The SSH User} 
                                {--root-password= : The password for the RootUser} 
                                {--private-key= : The path to the SSH Private Key } 
                                {--port=22 : The SSH Port }
                                {--token-type=node : Generate Token for Node or Kibana }
                                ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate an enrollment Token';

    protected Ssh $ssh;

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $token = TokenGenerator::handle(
            ip: $this->argument('ip'),
            user: $this->option('user'),
            port: $this->option('port'),
            privateKey: $this->option('private-key'),
        );

        $this->info($token);
    }
}
