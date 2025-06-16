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
        $today = $currentDate->format('Y-m-d 00:00:00');
        $sevenDaysAgo = (clone $currentDate)->modify('-7 days')->format('Y-m-d');
        $oneYearAgo = (new DateTime('now', new DateTimeZone('CET')))->modify('-1 year')->format('Y-m-d');

        $users = User::find()
            ->status('active')
            ->customStatus(['new', 'renew'])
            ->statusChangeDate($sevenDaysAgo)
            ->group(['members', 'membersGroup'])
            ->all();

        $usersRemind = array_filter($users, function ($user) use ($today, $oneYearAgo) {
            $customStatus = $user->getFieldValue('customStatus')->value;
            $dueDate = $user->getFieldValue('memberDueDate');
            $paymentDate = $user->getFieldValue('paymentDate');

            if ($customStatus === 'new') {
                return true;
            }

            if ($customStatus === 'renew') {
                $dueDateValid = !$dueDate instanceof \DateTimeInterface || $dueDate->format('d-m-Y') <= $today;
                $paymentDateValid = !$paymentDate instanceof \DateTimeInterface || $paymentDate->format('d-m-Y') <= $oneYearAgo;

                return $dueDateValid && $paymentDateValid;
            }

            return false;
        });


        Craft::info('DailyPaymentCheck: found ' . count($usersRemind) . ' users to remind.', __METHOD__);

        foreach ($usersRemind as $user) {
            $this->sendReminder($user);
        }
    }

    private function sendReminder(User $user): void
    {
        $baseUrl = Craft::$app->getSites()->currentSite->getBaseUrl();
        $memberType = $user->getFieldValue('memberType')->value;
        $status = $user->getFieldValue('customStatus')->value;
        $lang = $user->getFieldValue('lang')->value ?? 'nl';
        $baseTemplateUrl = 'email/remind/' . $lang;

        try {
            $mailer = Craft::$app->mailer;
            Craft::$app->getView()->setTemplatesPath(Craft::getAlias('@root/templates'));

            $subject = '';
            $templatePath = '';
            $htmlBody = '';

            if ($status === 'renew') {
                $activationUrl = Craft::$app->users->getEmailVerifyUrl($user);
                $templatePath = $baseTemplateUrl . '/remind-renew';
                $htmlBody = Craft::$app->getView()->renderTemplate($templatePath, [
                    'name' => $user->getFieldValue('altFirstName'),
                    'activationUrl' => $activationUrl,
                ]);
                $subject = match ($lang) {
                    'en' => 'Psst, forgot something? Renew your Hi Flanders membership',
                    'fr' => 'Psst, rien oublié ? Renouvelez votre adhésion à Hi Flanders',
                    default => 'Psst, niets vergeten? Vernieuw je lidmaatschap bij Hi Flanders',
                };
            } elseif ($status === 'new')  {
                $templatePath = $baseTemplateUrl . '/remind-payment';
                $name = in_array($memberType, ['group', 'groupYouth'])
                    ? $user->getFieldValue('organisation')
                    : $user->getFieldValue('altFirstName');

                $htmlBody = Craft::$app->getView()->renderTemplate($templatePath, [
                    'name' => $name,
                    'url' => $baseUrl
                ]);
                $subject = match ($lang) {
                    'en' => 'Psst, forgot something? Complete Your Hi Flanders Membership Payment',
                    'fr' => 'Psst, rien oublié ? Payez votre adhésion à Hi Flanders',
                    default => 'Psst, niets vergeten? Betaal nu om je lidmaatschap te activeren',
                };
            }

            $message = $mailer->compose()
                ->setTo($user->email)
                ->setSubject($subject)
                ->setHtmlBody($htmlBody);

            if (!$message->send()) {
                Craft::error('Failed to send reminder email to user: ' . $user->email, __METHOD__);
            } else {
                Craft::info('Reminder email sent to user: ' . $user->email, __METHOD__);
            }
        } catch (\Throwable $e) {
            Craft::error("Error sending reminder email to user {$user->email}: " . $e->getMessage(), __METHOD__);
        }
    }

    protected function defaultDescription(): string
    {
        return 'Processing payment reminders.';
    }
}
