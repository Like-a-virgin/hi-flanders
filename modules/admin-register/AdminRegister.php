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
        if (!$user->email) {
            Craft::error('User does not have an email address set.', __METHOD__);
            return;
        }

        $activationCode = Craft::$app->getUsers()->sendActivationEmail($user);

        if ($activationCode) {
            Craft::info("Activation email successfully sent to {$user->email}.", __METHOD__);
        } else {
            Craft::error("Failed to send activation email to {$user->email}.", __METHOD__);
        }
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
