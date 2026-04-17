<?php

/**
 * NixiEpp Registrar Module Manifest
 * 
 * FOSSBilling module definition file
 */

return [
    'id' => 'NixiEpp',
    'name' => 'NixiEpp Registrar',
    'description' => 'Production-ready EPP registrar module with TLS support for domain registration management',
    'version' => '1.2.0',
    'author' => 'NixiEpp',
    'author_url' => 'https://github.com/nixiepp',
    'license' => 'MIT',
    
    'type' => 'registrar',
    
    'icon' => 'globe',
    
    'requirements' => [
        'php' => '8.0',
        'php_extensions' => [
            'openssl',
            'xml',
            'simplexml',
        ],
    ],
    
    'features' => [
        'domain_registration' => true,
        'domain_transfer' => true,
        'domain_renewal' => true,
        'domain_info' => true,
        'domain_delete' => true,
        'nameserver_management' => true,
        'transfer_code' => true,
        'domain_lock' => true,
        'privacy_protection' => true,
        'contact_management' => true,
        'epp_tls' => true,
    ],
    
    'epp_versions' => [
        '1.0',
    ],
    
    'supported_objects' => [
        'domain',
        'contact',
        'host',
    ],
    
    'changelog' => [
        '1.0.0' => [
            'date' => '2026-04-17',
            'changes' => [
                'Initial release',
                'EPP over TLS support',
                'Domain registration, transfer, renewal',
                'Contact management',
                'Nameserver management',
                'Domain lock/unlock',
                'Privacy protection support',
            ],
        ],
    ],
];
