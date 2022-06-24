<?php

namespace Elastico\Controllers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;
use JsonSerializable;

/**
 *  Elasticsearch ServiceProvider.
 */
class ElasticoController
{
    public function emulateElastic()
    {
        try {
            if (request()->header('php-auth-user') != config('batzo.elasticsearch.username')
            || request()->header('php-auth-pw') != config('batzo.elasticsearch.password')) {
                abort(403);
            }

            $headers = request()->header();
            unset($headers['content-type'], $headers['content-length']);

            $http = Http::withHeaders($headers)
                ->withBody(
                    content: new class(request()->getContent()) implements JsonSerializable {
                        public function __construct(protected string $data)
                        {
                        }

                        public function __toString(): string
                        {
                            return $this->data;
                        }

                        public function jsonSerialize(): mixed
                        {
                            return $this->data;
                        }
                    },
                    contentType: request()->header('Content-Type', 'application/json')
                )
                ->{request()->method()}(config('batzo.elasticsearch.hosts')[0].request()->getRequestUri());

            return response($http->body(), $http->status())
                ->withHeaders($http->headers())
        ;
        } catch (\Throwable $e) {
            response([
                'headers' => request()->header(),
                'body' => request()->getContent(),
                'method' => request()->method(),
                'uri' => request()->getRequestUri(),
                'error' => $e->getMessage(),
            ], 500)->send();

            throw $e;
        }
    }
}
