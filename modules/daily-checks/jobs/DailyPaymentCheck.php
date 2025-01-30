<?php 

namespace modules\dailychecks\jobs;

use Craft;
use craft\queue\BaseJob;
use craft\elements\User;
use DateTime;
use DateTimeZone;

class DailyPaymentCheck extends BaseJob
{
    public function execute($queue): void
    {
        $currentDate = new DateTime('now', new DateTimeZone('CET'));
        $sevenDaysAgo = $currentDate->modify('-7 days')->format('Y-m-d');

        $usersRemind = User::find()
            ->status('active')
            ->customStatus('active')
            ->paymentType(null)
            ->statusChangeDate($sevenDaysAgo) 
            ->group(['members', 'membersGroup'])
            ->all();

        foreach ($usersRemind as $user) {
            $this->sendReminder($user);
        }
    }

    private function sendReminder(User $user): void
    {
        try {
            $mailer = Craft::$app->mailer;
            Craft::$app->getView()->setTemplatesPath(Craft::getAlias('@root/templates'));

            $activationUrl = Craft::$app->users->getEmailVerifyUrl($user);

            $htmlBody = Craft::$app->getView()->renderTemplate('email/remind/remind-payment', [
                'activationUrl' => $activationUrl,
            ]);

            $subject = 'Psst, niets vergeten? Betaal nu om je lidmaatschap te activeren';

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
        return 'Processing payments reminders.';
    }
}