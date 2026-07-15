<?php

return [
    'roles' => [
        'requester' => [
            'dashboard.requester.view',
            'ticket.own.view',
            'master_data.view',
        ],
        'supervisor' => [
            'dashboard.supervisor.view',
            'ticket.division.view',
            'master_data.view',
        ],
        'it_lead' => [
            'dashboard.it_lead.view',
            'ticket.all.view',
            'ticket.technical.view',
            'master_data.view',
        ],
        'pic' => [
            'dashboard.pic.view',
            'ticket.assigned.view',
            'ticket.technical.view',
            'master_data.view',
        ],
        'qa' => [
            'dashboard.qa.view',
            'ticket.assigned.view',
            'ticket.technical.view',
            'master_data.view',
        ],
        'manager' => [
            'dashboard.manager.view',
            'ticket.all.view',
            'ticket.technical.view',
            'master_data.view',
        ],
        'executive' => [
            'dashboard.executive.view',
            'executive.aggregate.view',
            'master_data.view',
        ],
        'admin' => [
            'dashboard.admin.view',
            'ticket.all.view',
            'ticket.technical.view',
            'admin.access',
            'master_data.view',
            'master_data.manage',
        ],
    ],
];
