<?php

namespace modules\afterdeactivation;

use Craft;
use craft\services\Users;
use craft\events\UserEvent;
use yii\base\Event;

use yii\base\Module as BaseModule;


class AfterDeactivation extends BaseModule
{
    public function init(): void
    {
        Craft::setAlias('@modules/afterdeactivation', __DIR__);

        parent::init();

        Event::on(
            Users::class,
            Users::EVENT_AFTER_DEACTIVATE_USER,
            function (UserEvent $event) {
                $this->handleAfterDeactivateUser($event);
            }
        );
    }

    private function handleAfterDeactivateUser(UserEvent $event): void
    {
        $user = $event->user;
        $usersService = Craft::$app->getUsers();
        $lang = $user->getFieldValue('lang')->value;
        $baseTemplateUrl = 'email/remind/' . $lang;

        // Check if the user's customStatus is `renew`
        if ($user->getFieldValue('customStatus')->value === 'renew') {
            $activationUrl = $usersService->getEmailVerifyUrl($user);

        // Use the mailer to compose and send the email
        try {
            $mailer = Craft::$app->mailer;
        
            // Render the HTML and Text templates
            Craft::$app->getView()->setTemplatesPath(Craft::getAlias('@root/templates'));

            $templatePath = $baseTemplateUrl . '/remind-renew';

            if ($user->getFieldValue('memberType')->value === 'individual') {
                $htmlBody = Craft::$app->getView()->renderTemplate($templatePath, [
                    'name' => $user->getFieldValue('altFirstName'),
                    'activationUrl' => $activationUrl,
                ]);
            } else {
                $htmlBody = Craft::$app->getView()->renderTemplate($templatePath, [
                    'name' => $user->getFieldValue('organisation'),
                    'activationUrl' => $activationUrl,
                ]);
            }

        
            // Send the email
            $message = $mailer->compose()
                ->setTo($user->email)
                ->setSubject('Hi Flanders - Blijf genieten van onze voordelen!')
                ->setHtmlBody($htmlBody);
                                            
            if (!$mailer->send($message)) {
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
