<?php

namespace modules\custommemberid;

use Craft;
use craft\elements\User;
use craft\events\ElementEvent;
use craft\services\Elements;
use yii\base\Event;
use yii\base\Module as BaseModule;

/**
 * CustomMemberId module
 *
 * @method static CustomMemberId getInstance()
 */
class CustomMemberId extends BaseModule
{
    public function init(): void
    {
        Craft::setAlias('@modules/custommemberid', __DIR__);

        // Set the controllerNamespace based on whether this is a console or web request
        if (Craft::$app->request->isConsoleRequest) {
            $this->controllerNamespace = 'modules\\custommemberid\\console\\controllers';
        } else {
            $this->controllerNamespace = 'modules\\custommemberid\\controllers';
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

                
                if ($element instanceof User && !$element->id) {
                    if ($element->hasErrors()) {
                        return;
                    } else {
                        $existingCustomMemberId = $element->getFieldValue('customMemberId');
    
                        if (empty($existingCustomMemberId)) {
                            $this->assignCustomMemberId($element);
                        } else {
                            Craft::info("Skipping customMemberId generation for User {$element->email}: already set.", __METHOD__);
                        }
                    }
                }
            }
        );
    }

    private function assignCustomMemberId(User $user): void
    {
        $currentYear = (int)(new \DateTime())->format('y'); // Last two digits of the year
        $uniqueId = $this->generateUniqueId($currentYear);

        if ($uniqueId) {
            $user->setFieldValue('customMemberId', $uniqueId);
        } else {
            Craft::error('Failed to generate a unique custom member ID.', __METHOD__);
        }
    }

    private function generateUniqueId(int $year): ?string
    {
        $attempts = 0;
        $maxAttempts = 100;

        do {
            // Generate a random 10-digit number
            $randomNumber = str_pad((string)random_int(0, 9999999999), 10, '0', STR_PAD_LEFT);

            // Construct the custom ID
            $customId = "{$year}{$randomNumber}";

            $users = User::find()->all();
            $isUnique = true;

            foreach ($users as $existingUser) {
                $existingCustomId = $existingUser->getFieldValue('customMemberId');
                if ($existingCustomId === $customId) {
                    $isUnique = false;
                    break;
                }
            }

            if ($isUnique) {
                return $customId;
            }

            $attempts++;
        } while ($attempts < $maxAttempts);

        return null;
    }
}
