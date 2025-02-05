<?php

namespace modules\afteractivation;

use Craft;

use yii\base\Event;
use craft\services\Users;
use craft\elements\User;
use craft\events\UserEvent;

use yii\base\Module as BaseModule;

/**
 * AfterActivation module
 *
 * @method static AfterActivation getInstance()
 */
class AfterActivation extends BaseModule
{
    public function init(): void
    {
        Craft::setAlias('@modules/afteractivation', __DIR__);

        // Set the controllerNamespace based on whether this is a console or web request
        if (Craft::$app->request->isConsoleRequest) {
            $this->controllerNamespace = 'modules\\afteractivation\\console\\controllers';
        } else {
            $this->controllerNamespace = 'modules\\afteractivation\\controllers';
        }

        parent::init();

        $this->attachEventHandlers();

    }

    private function attachEventHandlers(): void 
    {
        Event::on(
            Users::class,
            Users::EVENT_AFTER_ACTIVATE_USER,
            function (UserEvent $event) {
                $user = $event->user;

                if (!Craft::$app->getSession()->get("activation_mail_sent_{$user->id}")) {
                    $this->sendSuccesMail($user);
                    Craft::$app->getSession()->set("activation_mail_sent_{$user->id}", true);
                }
            }
        );
    }

    public function sendSuccesMail (User $user): void
    {
        $memberType = $user->getFieldValue('memberType')->value ?? null;
        $memberRateEntry = $user->getFieldValue('memberRate')->one();
        $memberPrice = $memberRateEntry ? $memberRateEntry->getFieldValue('price') : null;
        $paymentType = $user->getFieldValue('paymentType')->value;
        $registeredBy = $user->getFieldValue('registeredBy')->value;
        $lang = $user->getFieldValue('lang')->value;

        $baseTemplateUrl = 'email/verification/' . $lang;

        try {
            $mailer = Craft::$app->mailer;
            Craft::$app->getView()->setTemplatesPath(Craft::getAlias('@root/templates'));

            $htmlBody = null;
            $subject = null;

            if ($memberType === 'groupYouth') {
                $templatePath = $baseTemplateUrl . '/verification-youth';
                $htmlBody = Craft::$app->getView()->renderTemplate($templatePath, [
                    'name' => $user->getFieldValue('organisation'),
                ]);
    
                if ($lang === 'en') {
                    $subject = 'Successful: your group is now a member of Hi Flanders!';
                } elseif ($lang === 'fr') {
                    $subject = 'Succès : votre groupe est désormais membre de Hi Flanders !';
                } else {
                    $subject = 'Gelukt: jouw groep is nu lid van Hi Flanders!';
                }

            } elseif ($memberType === 'individual' && $paymentType != null || $memberType === 'employee' || $memberType === 'life'){
                $templatePath = $baseTemplateUrl . '/verification-ind-free';
                $htmlBody = Craft::$app->getView()->renderTemplate($templatePath , [
                    'name' => $user->getFieldValue('altFirstName'),
                ]);
    
                if ($lang === 'en') {
                    $subject = 'Yes! You are now officially a member of Hi Flanders 😁';
                } elseif ($lang === 'fr') {
                    $subject = 'Oui ! Vous êtes maintenant officiellement membre de Hi Flanders';
                } else {
                    $subject = 'Yes! Je bent nu officieel lid van Hi Flanders 😁';
                }

            } elseif ($memberType === 'individual' && $memberPrice != null && $registeredBy === 'self') {
                $templatePath = 'email/activation/' . $lang . '/activation-pay-request';
                $baseUrl = Craft::$app->getSites()->currentSite->getBaseUrl();

                $htmlBody = Craft::$app->getView()->renderTemplate($templatePath , [
                    'name' => $user->getFieldValue('altFirstName'),
                    'url' => $baseUrl
                ]);
    
                if ($lang === 'en') {
                    $subject = 'Mail address confirmed! Follow the payment link ...';
                } elseif ($lang === 'fr') {
                    $subject = 'Adresse postale confirmée ! Suivez le lien de paiement ...';
                } else {
                    $subject = 'Mailadres bevestigd! Volg de betalingslink …';
                }

            } else {
                return;
            }

            if ($htmlBody && $subject) {
                $message = $mailer->compose()
                    ->setTo($user->email)
                    ->setSubject($subject)
                    ->setHtmlBody($htmlBody);
                    
                if (!$mailer->send($message)) {
                    Craft::error('Failed to send renewal email to user: ' . $user->email, __METHOD__);
                } else {
                    Craft::info('Renewal email sent to user: ' . $user->email, __METHOD__);
                }
            }
        } catch (\Throwable $e) {
            Craft::error("Error sending custom activation email: " . $e->getMessage(), __METHOD__);
        }
    }
}
