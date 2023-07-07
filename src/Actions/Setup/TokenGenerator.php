<?php

namespace Elastico\Actions\Setup;

use Spatie\Ssh\Ssh;

class TokenGenerator
{
    public static function handle(
        string $ip,
        string $user = 'root',
        int $port = 22,
        string $privateKey = null,
        string $tokenType = 'node'
    ): string {
        $ssh = static::createSSH(
            ip: $ip,
            user: $user,
            port: $port,
            privateKey: $privateKey,
        );

        $ssh->onOutput(function ($type, $line) use (&$token) {
            if (strlen($line) > 100 && !str_contains($line, ' ')) {
                $token = trim($line);
            }
        });

        $ssh->execute('/usr/share/elasticsearch/bin/elasticsearch-create-enrollment-token -s ' . escapeshellarg($tokenType));

        if (empty($token)) {
            throw new \Exception('Could not generate Token');
        }

        return $token;
    }

    public static function createSSH(
        string $user,
        string $ip,
        int $port,
        null|string $privateKey = null
    ): Ssh {
        $ssh = Ssh::create(
            user: $user,
            host: $ip,
            port: $port
        )
            ->disableStrictHostKeyChecking();

        if ($privateKey) {
            $ssh->usePrivateKey($privateKey);
        }

        return $ssh;
    }
}
