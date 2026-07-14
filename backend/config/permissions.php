<?php

return [
    'roles' => [
        'requester' => [
            'dashboard.requester.view',
            'ticket.own.view',
        ],
        'supervisor' => [
            'dashboard.supervisor.view',
            'ticket.division.view',
        ],
        'it_lead' => [
            'dashboard.it_lead.view',
            'ticket.all.view',
            'ticket.technical.view',
        ],
        'pic' => [
            'dashboard.pic.view',
            'ticket.assigned.view',
            'ticket.technical.view',
        ],
        'qa' => [
            'dashboard.qa.view',
            'ticket.assigned.view',
            'ticket.technical.view',
        ],
        'manager' => [
            'dashboard.manager.view',
            'ticket.all.view',
            'ticket.technical.view',
        ],
        'executive' => [
            'dashboard.executive.view',
            'executive.aggregate.view',
        ],
        'admin' => [
            'dashboard.admin.view',
            'ticket.all.view',
            'ticket.technical.view',
            'admin.access',
        ],
    ],
];
