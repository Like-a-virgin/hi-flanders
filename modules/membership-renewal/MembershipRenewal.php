<?php

namespace modules\membershiprenewal;

use Craft;
use craft\elements\User;
use craft\queue\BaseJob;
use craft\services\Elements;
use craft\services\Users;
use craft\events\UserEvent;
use yii\base\Event;
use craft\mail\Message;
use yii\base\Module as BaseModule;
use DateTime;
use DateTimeZone;

class MembershipRenewal extends BaseModule
{
    public function init(): void
    {
        Craft::setAlias('@modules/membershiprenewal', __DIR__);

        parent::init();

        // Schedule the daily check
        $this->scheduleDailyCheck();

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
            Craft::$app->cache->set('membershipRenewalLastRun', time(), 86400);
        }
    }

    private function handleAfterDeactivateUser(UserEvent $event): void
    {
        $user = $event->user;
        $usersService = Craft::$app->getUsers();

        // Check if the user's customStatus is `renew`
        if ($user->getFieldValue('customStatus')->value === 'renew') {
            $activationUrl = $usersService->getEmailVerifyUrl($user);

        // Use the mailer to compose and send the email
        try {
            $mailer = Craft::$app->mailer;
            $result = $mailer->compose()
                ->setTo($user->email)
                ->setSubject('Membership Renewal Required')
                ->setHtmlBody("<p>Your membership has expired. Please renew your membership by clicking the link below:</p><p><a href=\"$activationUrl\">Renew Membership</a></p>")
                ->setTextBody("Your membership has expired. Please renew your membership using the link: $activationUrl")
                ->send();

            if (!$result) {
                Craft::error('Failed to send renewal email to user: ' . $user->email, __METHOD__);
            } else {
                Craft::info('Renewal email sent to user: ' . $user->email, __METHOD__);
            }
        } catch (\Throwable $e) {
            Craft::error('Error sending renewal email: ' . $e->getMessage(), __METHOD__);
        }
    }
    }
}

class DailyMembershipRenewalJob extends BaseJob
{
    public function execute($queue): void
    {
        $currentDate = new DateTime('now', new DateTimeZone('CET')); // Get today's date
        $today = $currentDate->format('Y-m-d'); // Format as YYYY-MM-DD

        // Query users whose dueDate matches today
        $users = User::find()
            ->group(['members', 'membersGroup'])
            ->memberDueDate($today) // Assumes `dueDate` is a custom date field
            ->all();

        $elementsService = Craft::$app->getElements();

        foreach ($users as $user) {
            $user->setFieldValue('customStatus', 'renew');

            if (!$elementsService->saveElement($user)) {
                Craft::error('Failed to update customStatus to renew for user: ' . $user->id, __METHOD__);
                continue;
            }

            // Deactivate the user
            $usersService = Craft::$app->getUsers();
            if (!$usersService->deactivateUser($user)) {
                Craft::error('Failed to deactivate user: ' . $user->id, __METHOD__);
            }
        }
    }

    protected function defaultDescription(): string
    {
        return 'Processing daily membership renewals.';
    }
}
