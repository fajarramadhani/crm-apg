<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Dynamic Workflow Engine Feature Flag
    |--------------------------------------------------------------------------
    |
    | When false (default), new tickets use the legacy compatibility flow.
    | When true, new tickets use the active published workflow.
    |
    | IMPORTANT: Only activate after running `php artisan crm:workflow-check`
    | and ensuring an active published workflow exists.
    | Do NOT activate this flag in production without explicit review.
    |
    */
    'dynamic_workflow_enabled' => (bool) env('CRM_DYNAMIC_WORKFLOW_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Workflow Snapshot Size Limit
    |--------------------------------------------------------------------------
    |
    | Maximum size (bytes) for a workflow snapshot stored per ticket.
    | Default: 65536 (64 KB)
    |
    */
    'workflow_snapshot_max_bytes' => (int) env('CRM_WORKFLOW_SNAPSHOT_MAX_BYTES', 65536),
];
