<?php

namespace modules\adminregister;

use Craft;
use craft\elements\User;
use craft\events\ElementEvent;
use craft\services\Elements;
use yii\base\Event;
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
            }
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
                }
            }
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
            'registeredBy' => null,
        ];

        foreach ($fields as $fieldHandle => $coreField) {
            $value = $request->getBodyParam("fields.{$fieldHandle}");

            if ($fieldHandle === 'memberDueDate') {
                // Automatically set memberDueDate to today + 1 year
                $currentDate = new \DateTime();
                $value = $currentDate->modify('+1 year')->format('Y-m-d');
            }
    
            if ($value !== null) {
                $user->setFieldValue($fieldHandle, $value);
    
                // Sync to core field if applicable
                if ($coreField) {
                    $user->{$coreField} = $value;
                }
            }
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
        $paymentDate = $user->getFieldValue('paymentDate');

        try {
            $activationUrl = Craft::$app->users->getActivationUrl($user);
            $mailer = Craft::$app->mailer;

            // Set custom templates path
            Craft::$app->getView()->setTemplatesPath(Craft::getAlias('@root/templates'));

            if ($registeredBy === 'admin' && $memberType === 'group') {
                $htmlBody = Craft::$app->getView()->renderTemplate('email/activation/activation-group', [
                    'name' => $user->getFieldValue('organisation'),
                    'activationUrl' => $activationUrl,
                ]);

                $subject = 'Welkom! Betalingsverzoek voor je groep';
            }

            if ($registeredBy === 'admin' && $memberType === 'groupYouth') {
                $htmlBody = Craft::$app->getView()->renderTemplate('email/activation/activation-youth', [
                    'name' => $user->getFieldValue('organisation'),
                    'activationUrl' => $activationUrl,
                ]);

                $subject = 'Welkom bij Hi Flanders! Registratie bijna in orde …';
            }

            if ($registeredBy === 'admin' && $memberType === 'individual' && $paymentDate) {
                $htmlBody = Craft::$app->getView()->renderTemplate('email/activation/activation-ind-ad-payed', [
                    'name' => $user->getFieldValue('organisation'),
                    'activationUrl' => $activationUrl,
                ]);

                $subject = 'Welkom bij Hi Flanders! Activeer meteen je lidmaatschap';
            }
            if ($registeredBy === 'admin' && $memberType === 'individual' && !$paymentDate) {
                $htmlBody = Craft::$app->getView()->renderTemplate('email/activation/activation-ind-ad', [
                    'name' => $user->getFieldValue('organisation'),
                    'activationUrl' => $activationUrl,
                ]);

                $subject = 'Welkom bij Hi Flanders! Activeer meteen je lidmaatschap en betaal';
            }
            
            // Prepare and send the email
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
        // if (!$user->email) {
        //     Craft::error('User does not have an email address set.', __METHOD__);
        //     return;
        // }

        // $activationCode = Craft::$app->getUsers()->sendActivationEmail($user);

        // if ($activationCode) {
        //     Craft::info("Activation email successfully sent to {$user->email}.", __METHOD__);
        // } else {
        //     Craft::error("Failed to send activation email to {$user->email}.", __METHOD__);
        // }
    }

    private function isMembersAdmin(User $user): bool
    {
        foreach ($user->getGroups() as $group) {
            if ($group->handle === 'membersAdmin') {
                return true;
            }
        }
        return false;
    }
}
