<?php

namespace modules\membershiprenewal;

use Craft;
use craft\elements\User;
use craft\queue\BaseJob;
use craft\services\Users;
use craft\events\UserEvent;
use yii\base\Event;
use DateTime;
use DateTimeZone;

use yii\base\Module as BaseModule;

/**
 * MembershipRenewal module
 *
 * @method static MembershipRenewal getInstance()
 */
class MembershipRenewal extends BaseModule
{
    public function init(): void
    {
        Craft::setAlias('@modules/membershiprenewal', __DIR__);

        // Set the controllerNamespace based on whether this is a console or web request
        if (Craft::$app->request->isConsoleRequest) {
            $this->controllerNamespace = 'modules\\membershiprenewal\\console\\controllers';
        } else {
            $this->controllerNamespace = 'modules\\membershiprenewal\\controllers';
        }

        parent::init();

        $this->scheduleDailyCheck();

        // Attach event handlers for user deactivation
        Event::on(
            Users::class,
            Users::EVENT_AFTER_DEACTIVATE_USER,
            function (UserEvent $event) {
                $this->handleAfterDeactivateUser($event);
            }
        );
    }

    private function scheduleDailyCheck(): void
    {
        $lastRun = Craft::$app->cache->get('membershipRenewalLastRun');

        if (!$lastRun || (time() - $lastRun) >= 86400) { // 24 hours = 86400 seconds
            Craft::$app->getQueue()->push(new DailyMembershipRenewalJob());
            Craft::$app->cache->set('membershipRenewalLastRun', time(), 86400); // Cache the last run time for 24 hours
        }
    }

    public function processMembershipRenewals(): void
    {
        $usersService = Craft::$app->getUsers();
        $currentDate = new DateTime('now', new DateTimeZone('UTC'));

        // Fetch all users in the specified groups
        $users = User::find()->group(['members', 'membersGroup'])->all();

        foreach ($users as $user) {
            // Parse the user's creation date
            $createdDate = DateTime::createFromFormat('Y-m-d H:i:s', $user->dateCreated->format('Y-m-d H:i:s'), new DateTimeZone('UTC'));
            if ($createdDate === false) {
                Craft::error('Invalid date format for user ID: ' . $user->id, __METHOD__);
                continue;
            }

            // Check if the user is eligible for renewal
            $diff = $createdDate->diff($currentDate);
            if ($diff->y >= 1) {
                // Deactivate the user
                if (!$usersService->deactivateUser($user)) {
                    Craft::error('Failed to deactivate user: ' . $user->id, __METHOD__);
                    continue;
                }
                
            }
        }
    }

    private function handleAfterDeactivateUser(UserEvent $event): void
    {
        $user = $event->user;
        $usersService = Craft::$app->getUsers();

        $user->setFieldValue('paymentDate', null);
        $user->setFieldValue('memberDueDate', (new DateTime('now', new DateTimeZone('UTC')))->modify('+1 year')->format('Y-m-d'));
        $user->setFieldValue('paymentType', null);
        $user->setFieldValue('memberRate', []);

        if (!Craft::$app->elements->saveElement($user)) {
            Craft::error('Failed to save updated fields for user ID: ' . $user->id, __METHOD__);
            Craft::error('Errors: ' . json_encode($user->getErrors()), __METHOD__);
        } else {
            Craft::info('Successfully updated user ID: ' . $user->id, __METHOD__);
        }

        try {
            if ($usersService->sendNewEmailVerifyEmail($user)) {
                Craft::info('Activation email sent to user: ' . $user->email, __METHOD__);
            } else {
                Craft::error('Failed to send activation email to user: ' . $user->email, __METHOD__);
            }
        } catch (\Throwable $e) {
            Craft::error('Error sending activation email: ' . $e->getMessage(), __METHOD__);
        }
    }
}

// Job Definition
class DailyMembershipRenewalJob extends BaseJob
{
    public function execute($queue): void
    {
        MembershipRenewal::getInstance()->processMembershipRenewals();
    }

    protected function defaultDescription(): string
    {
        return 'Processing daily membership renewals.';
    }
}

