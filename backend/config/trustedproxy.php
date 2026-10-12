<?php

return [

    /*
    | Proxies (load balancer, Cloudflare, nginx) whose X-Forwarded-For header
    | is trusted. Without this, the IP recorded on leads and cookie consents
    | is the proxy's address, not the visitor's. Use a comma-separated list
    | of IPs/CIDRs, or "*" when the app is only reachable through the proxy.
    */

    'proxies' => env('TRUSTED_PROXIES') ?: null,

];
