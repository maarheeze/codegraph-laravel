<?php

declare(strict_types=1);

use Laravel\Mcp\Facades\Mcp;
use Maarheeze\CodeGraph\Laravel\Mcp\CodegraphServer;

Mcp::local('codegraph', CodegraphServer::class);
