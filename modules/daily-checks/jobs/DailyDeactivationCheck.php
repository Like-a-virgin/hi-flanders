<?php 

namespace modules\dailychecks\jobs;

use Craft;
use craft\queue\BaseJob;
use craft\elements\User;
use DateTime;
use DateTimeZone;

class DailyDeactivationCheck extends BaseJob 
{
    public function execute($queue): void
    {
        $oneYearAgo = (new DateTime('now', new DateTimeZone('CET')))
            ->modify('-1 year')
            ->format('Y-m-d');

        $usersToDeactivate = User::find()
            ->status('pending')
            ->statusChangeDate("< $oneYearAgo")
            ->group(['members', 'membersGroup'])
            ->all();

        foreach ($usersToDeactivate as $user) {
            $this->deactivateUser($user);
        }

        $start = (new DateTime('now', new DateTimeZone('CET')))->modify('-358 days')->format('Y-m-d 00:00:00');
        $end = (new DateTime('now', new DateTimeZone('CET')))->modify('-357 days')->format('Y-m-d 00:00:00');

        $usersToRemind = User::find()
            ->status('pending')
            ->statusChangeDate(['and', ">= $start", "< $end"])
            ->group(['members', 'membersGroup'])
            ->all();

        foreach ($usersToRemind as $user) {
            $this->sendReminderEmail($user);
        }
    }

    public function deactivateUser(User $user): void
    {
        $currentDate = new DateTime('now', new DateTimeZone('CET'));
        $elementsService = Craft::$app->getElements();

        $user->setFieldValue('customStatus', 'deactivated');
        $user->setFieldValue('statusChangeDate', $currentDate);

        if (!$elementsService->saveElement($user)) {
            Craft::error('Failed to update customStatus to deactivated for user: ' . $user->id, __METHOD__);
            return;
        }

        $usersService = Craft::$app->getUsers();

        if (!$usersService->deactivateUser($user)) {
            Craft::error('Failed to deactivate user: ' . $user->id, __METHOD__);
        }
    }

    public function sendReminderEmail(User $user): void
    {
        $email = $user->email;
        $lang = $user->getFieldValue('lang')->value ?? 'nl';
        $memberType = $user->getFieldValue('memberType')->value ?? 'individual';

        $name = $memberType === 'individual'
            ? ($user->getFieldValue('altFirstName') ?? 'lid')
            : ($user->getFieldValue('organisation') ?? 'organisatie');

        $templatePath = 'email/remind/' . $lang . '/remind-before-deactivation';

        try {
            Craft::$app->getView()->setTemplatesPath(Craft::getAlias('@root/templates'));
            $htmlBody = Craft::$app->getView()->renderTemplate($templatePath, [
                'name' => $name,
            ]);

            $subject = match ($lang) {
                'en' => 'Reminder: Your account will be deactivated soon',
                'fr' => 'Rappel : Votre compte sera bientôt désactivé',
                default => 'Herinnering: Je account wordt binnenkort gedeactiveerd',
            };

            $success = Craft::$app->mailer->compose()
                ->setTo($email)
                ->setSubject($subject)
                ->setHtmlBody($htmlBody)
                ->send();

            if ($success) {
                Craft::info("Reminder email sent to user: $email", __METHOD__);
            } else {
                Craft::error("Failed to send reminder email to user: $email", __METHOD__);
            }

        } catch (\Throwable $e) {
            Craft::error("Error sending reminder to $email: " . $e->getMessage(), __METHOD__);
        }
    }

    protected function defaultDescription(): string
    {
        return 'Deactivating users who have been pending for over a year.';
    }
}
