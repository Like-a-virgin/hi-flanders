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
        $daysAgo = $currentDate->modify('-3 days')->format('Y-m-d');

        $daysAgoStart = (new \DateTime('-3 days'))->format('Y-m-d 00:00:00');
        $daysAgoEnd = (new \DateTime('-2 days'))->format('Y-m-d 00:00:00');

        $usersNew = User::find()
            ->status('pending')
            ->customStatus('new') // Assuming customStatus is a field handle
            ->dateCreated(['and', ">= $daysAgoStart", "< $daysAgoEnd"]) // Created 7 days ago
            ->group(['members', 'membersGroup'])
            ->all();
        
        $usersRenew = User::find()
            ->status('pending')
            ->customStatus('renew')
            ->statusChangeDate($daysAgo) // Status changed 7 days ago
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
        $lang = $user->getFieldValue('lang')->value;

        $baseTemplateUrl = 'email/remind/' . $lang;

        try {
            $mailer = Craft::$app->mailer;
            Craft::$app->getView()->setTemplatesPath(Craft::getAlias('@root/templates'));

            if ($type === 'renew') {
                $activationUrl = Craft::$app->users->getEmailVerifyUrl($user);
                $templatePath = $baseTemplateUrl . '/remind-renew';

                $htmlBody = Craft::$app->getView()->renderTemplate($templatePath, [
                    'name' => $user->getFieldValue('altFirstName'),
                    'activationUrl' => $activationUrl,
                ]);
    
                $subject = 'Psst, niets vergeten? Activeer je lidmaatschap bij Hi Flanders';
            }

            if ($type === 'new' && $memberType === 'individual' && $registeredBy === 'admin') {
                $activationUrl = Craft::$app->users->getActivationUrl($user);
                $templatePath = $baseTemplateUrl . '/remind-ind-ad';

                $htmlBody = Craft::$app->getView()->renderTemplate($templatePath, [
                    'name' => $user->getFieldValue('altFirstName'),
                    'activationUrl' => $activationUrl,
                ]);
    
                $subject = 'Psst, niets vergeten? Activeer je lidmaatschap bij Hi Flanders';
            }

            if ($type === 'new' && $memberType === 'individual' && $registeredBy === 'self') {
                $activationUrl = Craft::$app->users->getEmailVerifyUrl($user);
                $templatePath = $baseTemplateUrl . '/remind-ind';

                $htmlBody = Craft::$app->getView()->renderTemplate($templatePath, [
                    'name' => $user->getFieldValue('altFirstName'),
                    'activationUrl' => $activationUrl,
                ]);
    
                $subject = 'Psst, niets vergeten? Activeer je lidmaatschap bij Hi Flanders';
            }

            if ($type === 'new' && $memberType === 'group') {
                $activationUrl = Craft::$app->users->getActivationUrl($user);
                $templatePath = $baseTemplateUrl . '/remind-group';

                $htmlBody = Craft::$app->getView()->renderTemplate($templatePath, [
                    'name' => $user->getFieldValue('organisation'),
                    'activationUrl' => $activationUrl,
                ]);
    
                $subject = 'Psst, niets vergeten? Activeer je lidmaatschap bij Hi Flanders';
            }

            if ($type === 'new' && $memberType === 'groupYouth') {
                $activationUrl = Craft::$app->users->getActivationUrl($user);
                $templatePath = $baseTemplateUrl . '/remind-youth';

                $htmlBody = Craft::$app->getView()->renderTemplate($templatePath, [
                    'name' => $user->getFieldValue('organisation'),
                    'activationUrl' => $activationUrl,
                ]);
    
                $subject = 'Psst, niets vergeten? Activeer je lidmaatschap bij Hi Flanders';
            }

            $message = $mailer->compose()
                ->setTo($user->email)
                ->setSubject($subject)
                ->setHtmlBody($htmlBody);
                
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