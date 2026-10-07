<?php

return [

    'owner' => [

        'users.manage',

        'locations.view',
        'locations.create',
        'locations.update',
        'locations.delete',

        'pieces.view',
        'pieces.create',
        'pieces.update',
        'pieces.delete',

        'receiving.create',

        'transfers.create',
        'transfers.view',

        'sales.create',
        'sales.view',
        'sales.void',

        'invoices.view',

        'costs.view',
        'costs.manage',

        'reports.view',

        'profile.view',
        'profile.update',
    ],

    'admin' => [

        'users.manage',

        'locations.view',
        'locations.create',
        'locations.update',

        'pieces.view',
        'pieces.create',
        'pieces.update',
        'pieces.delete',

        'receiving.create',

        'transfers.create',
        'transfers.view',

        'sales.create',
        'sales.view',
        'sales.void',

        'invoices.view',

        'costs.view',

        'reports.view',

        'profile.view',
        'profile.update',
    ],

    'sales' => [

        'locations.view',

        'pieces.view',

        'sales.create',
        'sales.view',

        'invoices.view',

        'profile.view',
        'profile.update',
    ],

];
