<?php

namespace Elastico\Console\Setup;

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
    protected $signature = 'elastic:s3:setup 
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
}
