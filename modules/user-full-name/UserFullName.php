<?php

namespace modules\userfullname;

use Craft;
use craft\elements\User;
use craft\events\ElementEvent;
use craft\services\Elements;
use yii\base\Event;
use yii\base\Module as BaseModule;

/**
 * UserFullName module
 *
 * @method static UserFullName getInstance()
 */
class UserFullName extends BaseModule
{
    public function init(): void
    {
        Craft::setAlias('@modules/userfullname', __DIR__);

        // Set the controllerNamespace based on whether this is a console or web request
        if (Craft::$app->request->isConsoleRequest) {
            $this->controllerNamespace = 'modules\\userfullname\\console\\controllers';
        } else {
            $this->controllerNamespace = 'modules\\userfullname\\controllers';
        }

        parent::init();

        $this->attachEventHandlers();

    }

    private function attachEventHandlers(): void
    {
        Event::on(
            Elements::class,
            Elements::EVENT_BEFORE_SAVE_ELEMENT,
            function (ElementEvent $event) {
                $element = $event->element;

                // Check if the element is a User
                if ($element instanceof User) {
                    $this->copyCustomFieldsToDefault($element);
                }
            }
        );
    }

    private function copyCustomFieldsToDefault(User $user): void
    {
        // Retrieve custom field values
        $altFirstName = $user->getFieldValue('altFirstName');
        $altLastName = $user->getFieldValue('altLastName');
        $organisation = $user->getFieldValue('organisation');

        // Set the default fields to match the custom field values
        if (!empty($altFirstName)) {
            $user->firstName = $altFirstName;
        }

        if (!empty($altLastName)) {
            $user->lastName = $altLastName;
        }

        if (!empty($organisation)) {
            $user->fullName = $organisation;
        }

        // Log the operation for debugging
        Craft::info("Copied altFirstName and altLastName to firstName and lastName for user ID {$user->id}", __METHOD__);
    }
}
