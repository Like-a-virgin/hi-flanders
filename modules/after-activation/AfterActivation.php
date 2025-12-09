<?php

namespace modules\afteractivation;

use Craft;

use yii\base\Event;
use craft\services\Users;
use craft\elements\User;
use craft\events\UserEvent;
use craft\fields\data\SingleOptionFieldData;
use DateTime;
use DateTimeZone;

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

                $this->ensureCustomStatusForPaidAdmin($user);

                if (Craft::$app->request->getIsPost() && Craft::$app->request->getPathInfo() === 'membership-payments/payment/webhook') {
                    Craft::info("Skipping activation email for user {$user->email} (triggered by payment webhook)", __METHOD__);
                    return;
                }

                if ($user->getFieldValue('paymentType') === 'online') {
                    Craft::info("Skipping activation email for user {$user->email} (activated via Mollie)", __METHOD__);
                    return;
                }    

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
        $requestPrint = $user->getFieldValue('requestPrint');
        $registeredBy = $user->getFieldValue('registeredBy')->value;
        $lang = $user->getFieldValue('lang')->value;

        $baseTemplateUrl = 'email/verification/' . $lang;

        try {
            $mailer = Craft::$app->mailer;
            Craft::$app->getView()->setTemplatesPath(Craft::getAlias('@root/templates'));

            $htmlBody = null;
            $subject = null;

            if ($memberType === 'groupYouth' && $paymentType != null) {
                $templatePath = $baseTemplateUrl . '/verification-youth';
                $htmlBody = Craft::$app->getView()->renderTemplate($templatePath, [
                    'name' => $user->getFieldValue('organisation'),
                ]);
    
                if ($lang === 'en') {
                    $subject = 'Success! Your group is now a Hi Flanders member!';
                } elseif ($lang === 'fr') {
                    $subject = 'Succès : votre groupe est désormais membre de Hi Flanders !';
                } else {
                    $subject = 'Gelukt: jouw groep is nu lid van Hi Flanders!';
                }

            } elseif (($memberType === 'individual' || $memberType === 'life') && $paymentType === 'free' && $requestPrint === null ){
                $templatePath = $baseTemplateUrl . '/verification-ind-free';
                $htmlBody = Craft::$app->getView()->renderTemplate($templatePath , [
                    'name' => $user->getFieldValue('altFirstName'),
                ]);
    
                if ($lang === 'en') {
                    $subject = 'You are now an official Hi Flanders member';
                } elseif ($lang === 'fr') {
                    $subject = 'Oui ! Vous êtes maintenant officiellement membre de Hi Flanders';
                } else {
                    $subject = 'Yes! Je bent nu officieel lid van Hi Flanders 😁';
                }

            } elseif ($memberType === 'individual' && $memberPrice != null && $registeredBy === 'self' && $paymentType !== 'online') { 
                $templatePath = 'email/activation/' . $lang . '/activation-pay-request';
                $baseUrl = Craft::$app->getSites()->currentSite->getBaseUrl();

                $htmlBody = Craft::$app->getView()->renderTemplate($templatePath , [
                    'name' => $user->getFieldValue('altFirstName'),
                    'url' => $baseUrl
                ]);
    
                if ($lang === 'en') {
                    $subject = 'Email address confirmed! Follow the payment link…';
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

    private function ensureCustomStatusForPaidAdmin(User $user): void
    {
        $registeredBy = $this->getDropdownValue($user->getFieldValue('registeredBy'));

        if ($registeredBy !== 'admin') {
            return;
        }

        $paymentType = $this->getDropdownValue($user->getFieldValue('paymentType'));
        $paymentDate = $user->getFieldValue('paymentDate');
        $memberDueDate = $user->getFieldValue('memberDueDate');

        if (
            !$paymentType ||
            !($paymentDate instanceof \DateTimeInterface) ||
            !($memberDueDate instanceof \DateTimeInterface)
        ) {
            return;
        }

        $now = new DateTime('now', new DateTimeZone('CET'));

        if ($memberDueDate < $now) {
            return;
        }

        $currentStatus = $this->getDropdownValue($user->getFieldValue('customStatus'));

        if ($currentStatus === 'active') {
            return;
        }

        $user->setFieldValue('customStatus', 'active');
        $user->setFieldValue('statusChangeDate', $now);

        if (!Craft::$app->elements->saveElement($user, false)) {
            Craft::error("Failed to auto-set customStatus to active for user {$user->email}. Errors: " . json_encode($user->getErrors()), __METHOD__);
        } else {
            Craft::info("customStatus set to active after activation for user {$user->email}.", __METHOD__);
        }
    }

    private function getDropdownValue(mixed $fieldValue): ?string
    {
        if ($fieldValue instanceof SingleOptionFieldData) {
            return $fieldValue->value ?: null;
        }

        return is_string($fieldValue) ? ($fieldValue !== '' ? $fieldValue : null) : null;
    }
} 
