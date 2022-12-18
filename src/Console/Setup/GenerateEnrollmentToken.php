<?php

namespace Elastico\Console\Setup;

use Illuminate\Console\Command;
use Spatie\Ssh\Ssh;

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
        $token = TokenGenerator::generate(
            ip: $this->argument('ip'),
            user: $this->argument('user'),
            port: $this->argument('port'),
            privateKey: $this->argument('privateKey'),
        );
    }
}
