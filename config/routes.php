<?php
/**
 * Site URL Rules
 *
 * You can define custom site URL rules here, which Craft will check in addition
 * to routes defined in Settings → Routes.
 *
 * Read all about Craft’s routing behavior, here:
 * https://craftcms.com/docs/4.x/routing.html
 */

return [
    'actions/entries/delete-entry' => 'entries/delete-entry',
    'membership-payments/payment/webhook' => 'membership-payments/payment/webhook',
    'export-users' => 'excel-users/export/users',
    'export-kids' => 'excel-users/export-kids/kids',
    'send-activation-old' => 'old-members/send-activation/send-email',
    'send-reminder-old' => 'old-members/send-reminder/send-email',
    'deactivate-old' => 'old-members/deactivate/deactivate',
    'resend-activation' => 'admin-register/resend-activation/send-activation-email',
    'generate-eti' => 'eti-generator/eti/generate',
    'generate-csv' => 'csv-generator/csv/generate',
    'generate-csv-extra' => 'csv-generator/csv-extra/generate',
    'bulk-generate-eti' => 'eti-generator/bulk-eti/generate-all',
    'bulk-generate-csv' => 'csv-generator/bulk-csv/generate-all',
    'expanded-generate-csv' => 'csv-generator/expanded-csv/users',
    'api' => 'graphql/api',
];
