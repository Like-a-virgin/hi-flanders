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
        $daysAgo = $currentDate->modify('-7 days')->format('Y-m-d');

        $daysAgoStart = (new \DateTime('-7 days'))->format('Y-m-d 00:00:00');
        $daysAgoEnd = (new \DateTime('-6 days'))->format('Y-m-d 00:00:00');

        $usersNew = User::find()
            ->status('pending')
            ->customStatus('new') // Assuming customStatus is a field handle
            ->dateCreated(['and', ">= $daysAgoStart", "< $daysAgoEnd"]) // Created 3 days ago
            ->group(['members', 'membersGroup'])
            ->all();
        
        $usersRenew = User::find()
            ->status('pending')
            ->customStatus('renew')
            ->statusChangeDate($daysAgo) // Status changed 3 days ago
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
    
                if ($lang === 'en') {
                    $subject = 'Psst, forgot something? Renew your Hi Flanders membership';
                } elseif ($lang === 'fr') {
                    $subject = 'Psst, rien oublié ? Renouvelez votre adhésion à Hi Flanders';
                } else {
                    $subject = 'Psst, niets vergeten? Vernieuw je lidmaatschap bij Hi Flanders';
                }
            }

            if ($type === 'new' && $memberType === 'individual' && $registeredBy === 'admin') {
                $activationUrl = Craft::$app->users->getActivationUrl($user);
                $templatePath = $baseTemplateUrl . '/remind-ind-ad';

                $htmlBody = Craft::$app->getView()->renderTemplate($templatePath, [
                    'name' => $user->getFieldValue('altFirstName'),
                    'activationUrl' => $activationUrl,
                ]);
    
                if ($lang === 'en') {
                    $subject = 'Psst, forgot something? Activate your Hi Flanders membership';
                } elseif ($lang === 'fr') {
                    $subject = 'Psst, rien oublié ? Activez votre adhésion à Hi Flanders';
                } else {
                    $subject = 'Psst, rien oublié ? Activez votre adhésion à Hi Flanders';
                }
            }

            if ($type === 'new' && $memberType === 'individual' && $registeredBy === 'self') {
                $activationUrl = Craft::$app->users->getEmailVerifyUrl($user);
                $templatePath = $baseTemplateUrl . '/remind-ind';

                $htmlBody = Craft::$app->getView()->renderTemplate($templatePath, [
                    'name' => $user->getFieldValue('altFirstName'),
                    'activationUrl' => $activationUrl,
                ]);
    
                if ($lang === 'en') {
                    $subject = 'Psst, forgot something? Confirm your email address';
                } elseif ($lang === 'fr') {
                    $subject = 'Psst, rien oublié ? Confirmez votre adresse e-mail';
                } else {
                    $subject = 'Psst, niets vergeten? Activeer je lidmaatschap bij Hi Flanders';
                }
            }

            if ($type === 'new' && $memberType === 'group') {
                $activationUrl = Craft::$app->users->getActivationUrl($user);
                $templatePath = $baseTemplateUrl . '/remind-group';

                $htmlBody = Craft::$app->getView()->renderTemplate($templatePath, [
                    'name' => $user->getFieldValue('organisation'),
                    'activationUrl' => $activationUrl,
                ]);
    
                if ($lang === 'en') { 
                    $subject = 'Psst, forgot something? Complete your group membership payment at Hi Flanders';
                } elseif ($lang === 'fr') {
                    $subject = 'Psst, rien oublié ? Payez votre adhésion de groupe à Hi Flanders';
                } else {
                    $subject = 'Psst, niets vergeten? Betaal je groepslidmaatschap bij Hi Flanders';
                }
            }

            if ($type === 'new' && $memberType === 'groupYouth') {
                $activationUrl = Craft::$app->users->getActivationUrl($user);
                $templatePath = $baseTemplateUrl . '/remind-youth';

                $htmlBody = Craft::$app->getView()->renderTemplate($templatePath, [
                    'name' => $user->getFieldValue('organisation'),
                    'activationUrl' => $activationUrl,
                ]);
    
                if ($lang === 'en') {
                    $subject = 'Psst, forgot something? Activate your group membership at Hi Flanders';
                } elseif ($lang === 'fr') {
                    $subject = 'Welkom bij Hi Flanders! Registratie bijna in orde …';
                } else {
                    $subject = 'Psst, niets vergeten? Activeer je groepslidmaatschap bij Hi Flanders';
                }
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