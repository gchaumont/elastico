<?php

namespace Elastico\Deployment;

use Elastico\Deployment\Resources\IntegrationServer;


/**
 *  Deployment
 */
class Deployment
{
    public readonly string $name;

    public readonly string $region;

    public Settings $settings;

    public Metadata $metadata;

    public IntegrationServer $intergration_server;

    public Elasticsearch $elasticsearch;

    public EnterpriseSearch $enterprise_search;

    public Kibana $kibana;
}
