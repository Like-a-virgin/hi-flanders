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
    'send-activation-old' => 'old-members/send-activation/send-email',
    'send-reminder-old' => 'old-members/send-reminder/send-email',
    'deactivate-old' => 'old-members/deactivate/deactivate',
    'resend-activation' => 'admin-register/resend-activation/send-activation-email',
];
