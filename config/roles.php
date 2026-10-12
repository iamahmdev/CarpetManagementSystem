<?php

return [

    'owner' => [

        'users.manage',
        'locations.manage',
        'catalog.view',
        'catalog.manage',
        'stock.view',
        'containers.manage',
        'transfers.manage',
        'costs.view',
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
        'stock.view',
        'containers.manage',
        'transfers.manage',
        'sales.create',
        'sales.view',
        'sales.void',
        'reports.receivables',
    ],

    'sales' => [
        'catalog.view',
        'stock.view',
        'sales.create',
        'sales.view',
    ],

];
