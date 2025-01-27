<?php

namespace modules\deactivateuser;

use Craft;
use craft\elements\User;
use craft\elements\Entry;
use craft\events\ElementEvent;
use craft\services\Elements;
use yii\base\Event;
use DateTime;
use DateTimeZone;
use yii\base\Module as BaseModule;

/**
 * DeactivateUser module
 *
 * @method static DeactivateUser getInstance()
 */
class DeactivateUser extends BaseModule
{
    public function init(): void
    {
        Craft::setAlias('@modules/deactivateuser', __DIR__);

        // Set the controllerNamespace based on whether this is a console or web request
        if (Craft::$app->request->isConsoleRequest) {
            $this->controllerNamespace = 'modules\\deactivateuser\\console\\controllers';
        } else {
            $this->controllerNamespace = 'modules\\deactivateuser\\controllers';
        }

        parent::init();

        $this->attachEventHandlers();
    }

    private function attachEventHandlers(): void
    {
        // Register event handlers here ...
        // (see https://craftcms.com/docs/5.x/extend/events.html to get started)
        Event::on(
            Elements::class,
            Elements::EVENT_AFTER_SAVE_ELEMENT,
            function (ElementEvent $event) {
                $element = $event->element;

                // Check if it's a User element
                if ($element instanceof User) {
                    $this->handleUserDeactivation($element);
                }
            }
        );

    }

    private function handleUserDeactivation(User $user): void
    {
        $customStatus = $user->getFieldValue('customStatus')->value;

        if ($customStatus === 'deactivated') {
            if (!Craft::$app->getUsers()->deactivateUser($user)) {
                Craft::error("Failed to deactivate user ID {$user->id}.", __METHOD__);
                return;
            }
        }

        if ($customStatus === 'active') {
            if (!Craft::$app->getUsers()->activateUser($user)) {
                Craft::error("Failed to deactivate user ID {$user->id}.", __METHOD__);
                return;
            }
        }
    }
}
