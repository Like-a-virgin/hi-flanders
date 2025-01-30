<?php 

namespace modules\dailychecks\jobs;

use Craft;
use craft\queue\BaseJob;
use craft\elements\User;
use DateTime;
use DateTimeZone;

class DailyActivationCheck extends BaseJob
{
    public function execute($queue): void
    {
        $currentDate = new DateTime('now', new DateTimeZone('CET'));
        $sevenDaysAgo = $currentDate->modify('-7 days')->format('Y-m-d');

        $sevenDaysAgoStart = (new \DateTime('-7 days'))->format('Y-m-d 00:00:00');
        $sevenDaysAgoEnd = (new \DateTime('-6 days'))->format('Y-m-d 00:00:00');

        $usersNew = User::find()
            ->status('pending')
            ->customStatus('new') // Assuming customStatus is a field handle
            ->dateCreated(['and', ">= $sevenDaysAgoStart", "< $sevenDaysAgoEnd"]) // Created 7 days ago
            ->group(['members', 'membersGroup'])
            ->all();
        
        $usersRenew = User::find()
            ->status('pending')
            ->customStatus('renew')
            ->statusChangeDate($sevenDaysAgo) // Status changed 7 days ago
            ->group(['members', 'membersGroup'])
            ->all();

        foreach ($usersNew as $user) {
            $this->sendReminderEmail($user, 'new');
        }

        foreach ($usersRenew as $user) {
            $this->sendReminderEmail($user, 'renew');
        }
    }

    private function sendReminderEmail(User $user, string $type): void
    {
        $memberType = $user->getFieldValue('memberType')->value;
        $registeredBy = $user->getFieldValue('registeredBy')->value;

        try {
            $mailer = Craft::$app->mailer;
            Craft::$app->getView()->setTemplatesPath(Craft::getAlias('@root/templates'));

            if ($type === 'renew') {
                $activationUrl = Craft::$app->users->getEmailVerifyUrl($user);

                $htmlBody = Craft::$app->getView()->renderTemplate('email/remind/remind-renew', [
                    'name' => $user->getFieldValue('altFirstName'),
                    'activationUrl' => $activationUrl,
                ]);
    
                $subject = 'Psst, niets vergeten? Activeer je lidmaatschap bij Hi Flanders';
            }

            if ($type === 'new' && $memberType === 'individual' && $registeredBy === 'admin') {
                $activationUrl = Craft::$app->users->getActivationUrl($user);

                $htmlBody = Craft::$app->getView()->renderTemplate('email/remind/remind-ind-ad', [
                    'name' => $user->getFieldValue('altFirstName'),
                    'activationUrl' => $activationUrl,
                ]);
    
                $subject = 'Psst, niets vergeten? Activeer je lidmaatschap bij Hi Flanders';
            }

            if ($type === 'new' && $memberType === 'individual' && $registeredBy === 'self') {
                $activationUrl = Craft::$app->users->getEmailVerifyUrl($user);

                $htmlBody = Craft::$app->getView()->renderTemplate('email/remind/remind-ind', [
                    'name' => $user->getFieldValue('altFirstName'),
                    'activationUrl' => $activationUrl,
                ]);
    
                $subject = 'Psst, niets vergeten? Activeer je lidmaatschap bij Hi Flanders';
            }

            if ($type === 'new' && $memberType === 'group') {
                $activationUrl = Craft::$app->users->getActivationUrl($user);

                $htmlBody = Craft::$app->getView()->renderTemplate('email/remind/remind-group', [
                    'name' => $user->getFieldValue('organisation'),
                    'activationUrl' => $activationUrl,
                ]);
    
                $subject = 'Psst, niets vergeten? Activeer je lidmaatschap bij Hi Flanders';
            }

            if ($type === 'new' && $memberType === 'groupYouth') {
                $activationUrl = Craft::$app->users->getActivationUrl($user);

                $htmlBody = Craft::$app->getView()->renderTemplate('email/remind/remind-youth', [
                    'name' => $user->getFieldValue('organisation'),
                    'activationUrl' => $activationUrl,
                ]);
    
                $subject = 'Psst, niets vergeten? Activeer je lidmaatschap bij Hi Flanders';
            }

            $message = $mailer->compose()
                ->setTo($user->email)
                ->setSubject($subject)
                ->setHtmlBody($htmlBody)
                ->send();

            if (!$mailer->send($message)) {
                Craft::error('Failed to send renewal email to user: ' . $user->email, __METHOD__);
            } else {
                Craft::info('Renewal email sent to user: ' . $user->email, __METHOD__);
            }
        } catch (\Throwable $e) {
            Craft::error("Error sending custom activation email: " . $e->getMessage(), __METHOD__);
        }
    }

    protected function defaultDescription(): string
    {
        return 'Processing daily membership reminders.';
    }
}