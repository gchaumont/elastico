<?php

namespace Elastico\Console\Setup;

use Closure;
use Illuminate\Console\Command;
use Spatie\Ssh\Ssh;

class RemoveElasticsearch extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elastic:elasticsearch:remove 
                                {ip : The SSH IP Adress} 
                                {--user=root : The SSH User} 
                                {--root-password= : The password for the RootUser} 
                                {--private-key= : The path to the SSH Private Key } 
                                {--port=22 : The SSH Port }
                                ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install Elasticsearch on a server';

    protected Ssh $ssh;

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->createSSH(
            ip: $this->argument('ip'),
            user : $this->option('user'),
            port : $this->option('port'),
            privateKey : $this->option('private-key') // '/Users/gchaumont/.ssh/id_ed25519'
        );

        foreach ([
            'systemctl stop elasticsearch.service',
            'systemctl disable elasticsearch.service',
            'systemctl daemon-reload',
            // 'yes | sudo apt-get remove elasticsearch',
            'yes | sudo apt-get --purge autoremove elasticsearch',
            // 'sudo dpkg --remove elasticsearch',
            // 'sudo dpkg --purge elasticsearch',
            'sudo dpkg --purge --force-all elasticsearch',
            'sudo rm -rf  /etc/elasticsearch',
            'sudo rm -rf  /var/log/elasticsearch',
            'sudo rm -rf  /var/lib/elasticsearch',
            'sudo rm -rf  /var/run/elasticsearch',
            'sudo rm -rf  /usr/share/elasticsearch',
            'sudo rm -rf  /etc/sysconfig/elasticsearch',
            'sudo rm -rf  /usr/lib/systemd/system/elasticsearch.service',
        ] as $command) {
            $this->info($command);
            $this->ssh->execute($command);
        }
    }

    public function createSSH(
        string $user,
        string $ip,
        int $port,
        null|string $privateKey = null
    ): void {
        $this->ssh = Ssh::create(
            user: $user,
            host: $ip,
            port: $port
        )
            ->disableStrictHostKeyChecking()
            ->onOutput($this->handleOutput())
        ;

        if ($privateKey) {
            $this->ssh->usePrivateKey($privateKey);
        }

        if ($pw = $this->option('root-password')) {
            $this->ssh->execute("printf \"{$pw}\n\" | sudo -S su -");
        }
    }

    public function handleOutput(): Closure
    {
        return function ($type, $line) {
            $match = preg_match('#The generated password for the elastic built-in superuser is : (\w*)#', $line, $matches);
            if (1 === $match) {
                $this->elastic_password = $matches[1];
            }
            match ($type) {
                'err' => $this->warn($line),
                'out' => $this->line($line),
            };
        };
    }
}
