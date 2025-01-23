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
            $templatePath = 'email/my-email-template';

            // Prepare the email content
            $emailSubject = Craft::t('app', 'Reactivate Your Membership');
            $emailBody = Craft::$app->view->renderTemplate('templates/email/reactivate-membership', [
                'username' => $user->fullName,
                'activationLink' => $activationUrl,
            ]);

            // Send the email
            try {
                $message = new Message();
                $message->setTo($user->email)
                    ->setSubject($emailSubject)
                    ->setHtmlBody($emailBody)
                    ->setTextBody(strip_tags($emailBody)); 

                if (!Craft::$app->getMailer()->send($message)) {
                    Craft::error('Failed to send activation email to user: ' . $user->email, __METHOD__);
                } else {
                    Craft::info('Activation email sent to user: ' . $user->email, __METHOD__);
                }
            } catch (\Throwable $e) {
                Craft::error('Error sending activation email: ' . $e->getMessage(), __METHOD__);
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
