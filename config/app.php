<?php
/**
 * Yii Application Config
 *
 * Edit this file at your own risk!
 *
 * The array returned by this file will get merged with
 * vendor/craftcms/cms/src/config/app.php and app.[web|console].php, when
 * Craft's bootstrap script is defining the configuration for the entire
 * application.
 *
 * You can define custom modules and system components, and even override the
 * built-in system components.
 *
 * If you want to modify the application config for *only* web requests or
 * *only* console requests, create an app.web.php or app.console.php file in
 * your config/ folder, alongside this one.
 * 
 * Read more about application configuration:
 * https://craftcms.com/docs/4.x/config/app.html
 */

use craft\helpers\App;
use modules\adminregister\AdminRegister;
use modules\confirmemail\ConfirmEmail;
use modules\custommemberid\CustomMemberId;
use modules\excelusers\ExcelUsers;
use modules\membershippayments\MembershipPayments;
use modules\rateextramember\RateExtraMember;
use modules\ratemember\RateMember;
use modules\userfullname\UserFullName;

return [
    'id' => App::env('CRAFT_APP_ID') ?: 'CraftCMS', 
    'modules' => [
        'rate-member' => RateMember::class, 
        'rate-extra-member' => RateExtraMember::class, 
        'membership-payments' => MembershipPayments::class,
        'confirm-email' => ConfirmEmail::class,
        'user-full-name' => UserFullName::class,
        'custom-member-id' => CustomMemberId::class,
        'admin-register' => AdminRegister::class,
        'excel-users' => ExcelUsers::class,
    ], 
    'bootstrap' => [
        'rate-member', 
        'rate-extra-member', 
        'membership-payments',
        'confirm-email',
        'user-full-name',
        'custom-member-id',
        'admin-register',
        'excel-users',
    ],
];
