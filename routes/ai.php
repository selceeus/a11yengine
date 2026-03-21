<?php

use App\Mcp\Servers\PropertyAccessibilityServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp/property-accessibility', PropertyAccessibilityServer::class)
    ->middleware('mcp.auth');
