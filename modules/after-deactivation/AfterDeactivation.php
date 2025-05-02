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
        $userId = $user->id;
        $email = $user->email;
        
        // Prevent duplicate sends
        $cacheKey = 'after-deactivation-email-sent-' . $userId;
        if (Craft::$app->cache->get($cacheKey)) {
            Craft::warning("Skipping duplicate email for user ID $userId", __METHOD__);
            return;
        }
        Craft::$app->cache->set($cacheKey, true, 300); // 5 min lock

        $lang = $user->getFieldValue('lang')->value ?? 'nl';
        $memberType = $user->getFieldValue('memberType')->value ?? 'individual';
        
        $templateBase = 'email/deactivate/';
        $templatePath = $templateBase . $lang . '/account-deactivated';
        
        $name = $memberType === 'individual'
            ? ($user->getFieldValue('altFirstName') ?? 'lid')
            : ($user->getFieldValue('organisation') ?? 'organisatie');

        try {
            Craft::$app->getView()->setTemplatesPath(Craft::getAlias('@root/templates'));
            $htmlBody = Craft::$app->getView()->renderTemplate($templatePath, [
                'name' => $name,
            ]);

            $subject = match ($lang) {
                'en' => 'Your account has been deactivated',
                'fr' => 'Votre compte a été désactivé',
                default => 'Je account is gedeactiveerd',
            };

            $success = Craft::$app->mailer->compose()
                ->setTo($email)
                ->setSubject($subject)
                ->setHtmlBody($htmlBody)
                ->send();

            if ($success) {
                $logType = 'Deactivation';
                Craft::info("$logType email sent to user: $email", __METHOD__);
            } else {
                Craft::error("Failed to send email to user: $email", __METHOD__);
            }

        } catch (\Throwable $e) {
            Craft::error("Error rendering or sending email to $email: " . $e->getMessage(), __METHOD__);
        }
    }
}
