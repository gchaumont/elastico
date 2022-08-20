<?php

namespace Elastico\Console\Setup;

use Closure;
use Elastico\ConnectionResolverInterface;
use Illuminate\Console\Command;
use Spatie\Ssh\Ssh;

class SetupS3Repository extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elastico:s3:setup 
                                {ip : The SSH IP Adress} 
                                {--user=root : The SSH User} 
                                {--root-password= : The password for the RootUser} 
                                {--private-key= : The path to the SSH Private Key } 
                                {--port=22 : The SSH Port }
                                {--client= : The Client name}
                                {--endpoint= : The S3 Service endpoint}
                                {--bucket= : The S3 Bucket}
                                {--path= : The S3 Bucket path}
                                {--access-key= : The S3 Access Key}
                                {--secret-key= : The S3 Secret Key}
                                {--connection= : The Elastic Connection to reload setttings}
                                ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Setup S3 Repository';

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

        $client_name = $this->option('client') ?? 'default';

        // $this->upsertSetting(
        //     key: "s3.client.{$client_name}.endpoint",
        //     value: $this->option('endpoint'),
        // );

        $this->addToKeystore(
            key: $key = "s3.client.{$client_name}.access_key",
            value: $this->option('access-key'),
        );

        $this->addToKeystore(
            key: $key = "s3.client.{$client_name}.secret_key",
            value: $this->option('secret-key')
        );
        $connection = $this->option('connection') ?? 'default';

        $this->reloadSecure(connection: $connection);

        resolve(ConnectionResolverInterface::class)->connection($connection)
            ->getClient()
            ->snapshot()
            ->createRepository([
                'repository' => 'spaces',
                'body' => [
                    'type' => 's3',
                    'settings' => [
                        'client' => $client_name,
                        'bucket' => $this->option('bucket'),
                        'base_path' => $this->option('path'),
                        'endpoint' => $this->option('endpoint'),
                    ],
                ],
            ])
        ;

        // $this->ssh->execute('systemctl restart elasticsearch.service');
    }

    public function reloadSecure(string $connection)
    {
        $response = resolve(ConnectionResolverInterface::class)->connection($connection)
            ->getClient()
            ->nodes()
            ->reloadSecureSettings()
        ;

        $response = json_decode((string) $response->getBody());

        if ($response->_nodes->successful != $response->_nodes->total) {
            $this->error('Error reloading secure settings');
        } else {
            $this->info('Settings reloaded successfully on '.$response->_nodes->successful.' nodes.');
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
            match ($type) {
                'err' => $this->warn($line),
                'out' => $this->line($line),
            };
        };
    }

    public function addToKeystore(string $key, string $value): void
    {
        foreach ([
            "/usr/share/elasticsearch/bin/elasticsearch-keystore remove {$key}",
            "echo {$value} | /usr/share/elasticsearch/bin/elasticsearch-keystore add {$key} --stdin ",
        ] as $command) {
            $this->ssh->execute($command);
        }

        $this->info('Added to the Keystore: '.$key);
    }

    public function upsertSetting(string $key, string $value): void
    {
        $temp_file = tmpfile();

        $temp_file_name = stream_get_meta_data($temp_file)['uri'];

        $this->ssh->download('/etc/elasticsearch/elasticsearch.yml', $temp_file_name);

        $contents = file_get_contents($temp_file_name);

        fclose($temp_file);

        // $contents = "s3.client.spaces.endpoint: amazooonasdas.asdas\n".$contents;

        $input = "{$key}: {$value}"."\n";
        if (str_contains($contents, $key)) {
            $contents = preg_replace('/'.preg_quote($key).".*\n/", $input, $contents);
        } else {
            $contents = $input.$contents;
        }

        $temp_file = tmpfile();

        $temp_file_name = stream_get_meta_data($temp_file)['uri'];

        fwrite($temp_file, $contents);

        $this->ssh->upload($temp_file_name, '/etc/elasticsearch/elasticsearch.yml');

        fclose($temp_file);
    }
}
