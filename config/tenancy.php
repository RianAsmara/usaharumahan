<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tenant Root Domain
    |--------------------------------------------------------------------------
    |
    | The domain every tenant subdomain hangs off of, e.g. "usaharumahan.id"
    | so that "dapur-ibu.usaharumahan.id" resolves to the "dapur-ibu" tenant.
    | See docs/TENANCY.md for the full hostname resolution model.
    |
    */

    'root_domain' => env('TENANT_ROOT_DOMAIN', 'usaharumahan.localhost'),

    /*
    |--------------------------------------------------------------------------
    | Reserved Subdomains
    |--------------------------------------------------------------------------
    |
    | Subdomains a tenant may never claim, because they're already used by
    | the platform itself or would be confusing/spoofable if a tenant owned
    | them.
    |
    */

    'reserved_subdomains' => [
        'www', 'admin', 'api', 'app', 'dashboard', 'mail', 'support',
        'help', 'status', 'static', 'assets', 'cdn', 'platform',
        'localhost', 'ftp', 'smtp', 'imap', 'ns1', 'ns2', 'root',
    ],

    /*
    |--------------------------------------------------------------------------
    | Hostname → Tenant Cache
    |--------------------------------------------------------------------------
    |
    | How long a successful hostname-to-tenant resolution is cached for. The
    | cache is explicitly invalidated whenever a store_domains row changes,
    | so this TTL is only a safety net, not the primary invalidation path.
    |
    */

    'hostname_cache_ttl' => 3600,

];
