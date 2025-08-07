<?php

namespace modules\adminregister;

use Craft;
use craft\elements\User;
use craft\events\ElementEvent;
use craft\services\Elements;
use yii\base\Event;
use DateTime;
use DateTimeZone;
use yii\base\Module as BaseModule;

/**
 * AdminRegister module
 *
 * @method static AdminRegister getInstance()
 */
class AdminRegister extends BaseModule
{
    public function init(): void
    {
        Craft::setAlias('@modules/adminregister', __DIR__);

        if (Craft::$app->request->isConsoleRequest) {
            $this->controllerNamespace = 'modules\\adminregister\\console\\controllers';
        } else {
            $this->controllerNamespace = 'modules\\adminregister\\controllers';
        }

        parent::init();

        $this->attachEventHandlers();
    } 

    private function attachEventHandlers(): void
    {
        // Handle field values before saving the user
        Event::on(
            Elements::class,
            Elements::EVENT_BEFORE_SAVE_ELEMENT,
            function (ElementEvent $event) {
                $element = $event->element;

                if ($element instanceof User && $event->isNew) {
                    $this->prepareUserFields($element);
                }

                if ($element instanceof User) {
                    // Keep username in sync with email
                    $element->username = $element->email; 
                }
            },
        );

        // Assign groups after the user is saved
        Event::on(
            Elements::class,
            Elements::EVENT_AFTER_SAVE_ELEMENT,
            function (ElementEvent $event) {
                $element = $event->element;

                if ($element instanceof User && $event->isNew) {
                    $this->assignUserGroup($element);
                    $this->sendActivationCode($element); 

                    $totalPayedPrint = $element->getFieldValue('totalPayedPrint');
                    $requestPrintSend = $element->getFieldValue('requestPrintSend');
                    $printStatus = $element->getFieldValue('printStatus');

                    if (!empty($totalPayedPrint) && empty($requestPrintSend) && $printStatus == 'requested') {
                        $this->sendPrintDetailsOwner($element);
                    }
                }
            },
        );
    }

    private function prepareUserFields(User $user): void
    {
        $request = Craft::$app->getRequest();

        $fields = [
            'altFirstName' => 'firstName',
            'altLastName' => 'lastName',
            'birthday' => null,
            'street' => null,
            'streetNr' => null,
            'bus' => null,
            'postalCode' => null,
            'city' => null,
            'country' => null,
            'paymentType' => null,
            'memberType' => null,
            'groupType' => null,
            'organisation' => null,
            'tel' => null,
            'contactPerson' => null,
            'memberDueDate' => null,
            'privacyPolicy' => null,
            'newsletter' => null,
            'registeredBy' => null,
            'lang' => null,
            'totalPayedMembers' => null,
            'customMemberId' => null,
            'requestPrint' => null,
            'payedPrintDate' => null,
            'paymentDate' => null,
            'cardType' => null,
            'totalPayedPrint' => null,
            'dateSendPrint' => null,
            'statusChangeDate' => null,
            'requestPrintSend' => null,
            'printStatus' => null,
        ];

        foreach ($fields as $fieldHandle => $coreField) {
            $value = $request->getBodyParam("fields.{$fieldHandle}");

            // if ($fieldHandle === 'memberDueDate' && empty($value)) {
            //     // Automatically set memberDueDate to today + 1 year
            //     $currentDate = new \DateTime();
            //     $value = $currentDate->modify('+1 year')->format('Y-m-d');
            // }

            if ($fieldHandle === 'totalPayedMembers' || $fieldHandle === 'totalPayedPrint') {
                if ($value !== null && is_numeric($value)) {
                    $value = floatval($value); // Convert to a float
    
                    // Convert to cents (e.g., 49.99 -> 4999) if Craft CMS stores as cents
                    $value = (int) round($value * 100);
                } else {
                    $value = null; // Prevents invalid data
                }
            }

            if ($fieldHandle === 'statusChangeDate') {
                $value = new \DateTime('now', new \DateTimeZone('CET'));
            }
    
            if ($value !== null) {
                $user->setFieldValue($fieldHandle, $value);
    
                // Sync to core field if applicable
                if ($coreField) {
                    $user->{$coreField} = $value;
                }
            } 
        }

        $printStatus = $user->getFieldValue('printStatus')->value;
        $printTotalPayed = $user->getFieldValue('totalPayedPrint')->value;

        if (empty($printStatus)) {
            $user->setFieldValue('totalPayedPrint', null);
        }

        if (!empty($printStatus) && !empty($printTotalPayed)) {
            $user->setFieldValue('payedPrintDate', new DateTime('now', new DateTimeZone('Europe/Brussels')));
        }
    }

    private function assignUserGroup(User $user): void
    {
        $currentUser = Craft::$app->getUser()->getIdentity();

        if (!$currentUser || !$this->isMembersAdmin($currentUser)) {
            Craft::error('Current user is not authorized to register new users.', __METHOD__);
            return;
        }

        $groupHandle = Craft::$app->getRequest()->getBodyParam('groupHandle');
        $group = Craft::$app->userGroups->getGroupByHandle($groupHandle);

        

        if (!$group) {
            Craft::error('Invalid user group handle provided: ' . $groupHandle, __METHOD__);
            return;
        }

        if (Craft::$app->users->assignUserToGroups($user->id, [$group->id])) {
            Craft::info("User ID {$user->id} successfully assigned to group {$groupHandle}.", __METHOD__);
        } else {
            Craft::error("Failed to assign User ID {$user->id} to group {$groupHandle}.", __METHOD__);
        }
    }

    private function sendActivationCode(User $user): void
    {
        $registeredBy = $user->getFieldValue('registeredBy')->value;
        $memberType = $user->getFieldValue('memberType')->value;
        $memberRateEntry = $user->getFieldValue('memberRate')->one();
        $memberPrice = $memberRateEntry ? $memberRateEntry->getFieldValue('price') : null;
        $paymentType = $user->getFieldValue('paymentType')->value;
        $customStatus = $user->getFieldValue('customStatus')->value;
        $lang = $user->getFieldValue('lang')->value;

        $userGroups = $user->getGroups();
        $isMembersAdmin = false;

        foreach ($userGroups as $group) {
            if ($group->handle === 'membersAdmin') {
                $isMembersAdmin = true;
                break;
            }
        }

        $baseUrl = Craft::$app->getSites()->currentSite->getBaseUrl();
        $baseTemplateUrl = 'email/activation/' . $lang;

        try {
            $activationUrl = Craft::$app->users->getActivationUrl($user);
            $mailer = Craft::$app->mailer;

            // Set custom templates path
            Craft::$app->getView()->setTemplatesPath(Craft::getAlias('@root/templates'));

            if ($registeredBy === 'admin' && $memberType === 'group' && $customStatus === 'new') {
                $templatePath = $baseTemplateUrl . '/activation-group';
                $htmlBody = Craft::$app->getView()->renderTemplate($templatePath, [
                    'name' => $user->getFieldValue('organisation'),
                    'activationUrl' => $activationUrl,
                    'url' => $baseUrl,
                ]);

                if ($lang === 'en') {
                    $subject = 'Welcome! Payment request for your group';
                } elseif ($lang === 'fr') {
                    $subject = 'Bienvenue ! Demande de paiement pour votre groupe';
                } else {
                    $subject = 'Welkom! Betalingsverzoek voor je groep';
                }

            } elseif ($registeredBy === 'admin' && $memberType === 'groupYouth' && $customStatus === 'new') {
                $templatePath = $baseTemplateUrl. '/activation-youth';
                $htmlBody = Craft::$app->getView()->renderTemplate($templatePath, [
                    'name' => $user->getFieldValue('organisation'),
                    'activationUrl' => $activationUrl,
                ]);
    
                if ($lang === 'en') {
                    $subject = 'Welcome to Hi Flanders! Registration almost complete…';
                } elseif ($lang === 'fr') {
                    $subject = "Bienvenue chez Hi Flanders ! Enregistrement presque terminé…";
                } else {
                    $subject = 'Welkom bij Hi Flanders! Registratie bijna in orde …';
                }

            } elseif ($registeredBy === 'admin' && $memberType === 'individual' && $paymentType != '' && $customStatus === 'new' || $memberType === 'employee' || $memberType === 'life') {
                $templatePath = $baseTemplateUrl . '/activation-ind-ad-payed';
                $htmlBody = Craft::$app->getView()->renderTemplate($templatePath, [
                    'name' => $user->getFieldValue('altFirstName'),
                    'activationUrl' => $activationUrl,
                ]);
                
                if ($lang === 'en') {
                    $subject = 'Welcome to Hi Flanders! Activate your membership now';
                } elseif ($lang === 'fr') {
                    $subject = 'Bienvenue chez Hi Flanders ! Activez votre adhésion';
                } else {
                    $subject = 'Welkom bij Hi Flanders! Activeer meteen je lidmaatschap';
                }
                
            } elseif ($registeredBy === 'admin' && $memberType === 'individual' && $paymentType === '' && $customStatus === 'new' && $memberPrice != null) {
                $templatePath = $baseTemplateUrl . '/activation-ind-ad';
                $htmlBody = Craft::$app->getView()->renderTemplate($templatePath, [
                    'name' => $user->getFieldValue('altFirstName'),
                    'activationUrl' => $activationUrl,
                    'url' => $baseUrl,
                ]);

                if ($lang === 'en') {
                    $subject = 'Welcome to Hi Flanders! Activate your membership now';
                } elseif ($lang === 'fr') {
                    $subject = 'Bienvenue chez Hi Flanders ! Activez votre adhésion dès maintenant';
                } else {
                    $subject = 'Welkom bij Hi Flanders! Activeer meteen je lidmaatschap';
                }

            } elseif ($isMembersAdmin) {
                $templatePath = $baseTemplateUrl . '/activation-hostel';
                $htmlBody = Craft::$app->getView()->renderTemplate($templatePath, [
                    'name' => $user->username,
                    'activationUrl' => $activationUrl,
                ]);

                $subject = 'Hey hostel, stel je wachtwoord in.';

            } else {
                return;
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

    private function isMembersAdmin(User $user): bool
    {
        foreach ($user->getGroups() as $group) {
            if ($group->handle === 'membersAdmin' || $group->handle === 'membersAdminSuper') {
                return true;
            }
        }
        return false;
    }

    private function sendPrintDetailsOwner(User $user)
    {
        try {
            if ($user->getFieldValue('requestPrintSend')) {
                Craft::info("Skipping duplicate print request email for user: {$user->email}", __METHOD__);
                return;
            }

            $mailer = Craft::$app->mailer;
            Craft::$app->getView()->setTemplatesPath(Craft::getAlias('@root/templates'));

            $htmlBody = Craft::$app->getView()->renderTemplate('email/request/nl/request-print', [
                'name' => $user->getFieldValue('altFirstName') . ' ' . $user->getFieldValue('altLastName'),
                'id' => $user->getFieldValue('customMemberId'),
                'street' => $user->getFieldValue('street'),
                'number' => $user->getFieldValue('streetNr'),
                'postalcode' => $user->getFieldValue('postalCode'),
                'city' => $user->getFieldValue('city'),
                'country' => $user->getFieldValue('country'), 
                'memberType' => $user->getFieldValue('memberType')->label
            ]);

            $subject = 'Nieuwe print aanvraag.';

            // Prepare and send the email
            $message = $mailer->compose()
                ->setTo('claudine@likeavirgin.be')
                ->setSubject($subject)
                ->setHtmlBody($htmlBody);

                
            if (!$message->send()) {
                Craft::error('Failed to send payment confirmation email to: ' . $user->email, __METHOD__);
            } else {
                Craft::info('Payment confirmation email sent to: ' . $user->email, __METHOD__);

                $user->setFieldValue('requestPrint', new DateTime('now', new DateTimeZone('Europe/Brussels')));
                $user->setFieldValue('requestPrintSend', true);
                Craft::$app->elements->saveElement($user, false);
            }
        } catch (\Throwable $e) {
            Craft::error("Error sending payment confirmation email: " . $e->getMessage(), __METHOD__);
        } 
    }
}
