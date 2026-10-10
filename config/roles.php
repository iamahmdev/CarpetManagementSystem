<?php

return [

    'owner' => [

        'users.manage',
        'locations.manage',
        'catalog.view',
        'catalog.manage',
        'sales.create',
        'sales.view',
        'sales.void',
        'reports.profit',
        'reports.receivables',
    ],

    'admin' => [

        'users.manage',
        'catalog.view',
        'catalog.manage',
        'sales.create',
        'sales.view',
        'sales.void',
        'reports.receivables',
    ],

    'sales' => [
        'catalog.view',
        'sales.create',
        'sales.view',
    ],

];
