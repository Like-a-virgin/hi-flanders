<?php

namespace modules\beforeactivationuser;

use Craft;
use craft\elements\User;
use craft\services\Users;
use craft\events\UserEvent;
use craft\elements\Entry;
use yii\base\Event;
use DateTime;
use DateTimeZone;


use yii\base\Module as BaseModule;

/**
 * BeforeAtivationUser module
 *
 * @method static BeforeAtivationUser getInstance()
 */
class BeforeActivationUser extends BaseModule
{
    public function init(): void
    {
        Craft::setAlias('@modules/beforeactivationuser', __DIR__);

        // Set the controllerNamespace based on whether this is a console or web request
        if (Craft::$app->request->isConsoleRequest) {
            $this->controllerNamespace = 'modules\\beforeactivationuser\\console\\controllers';
        } else {
            $this->controllerNamespace = 'modules\\beforeactivationuser\\controllers';
        }

        parent::init();
        
        Event::on(
            Users::class,
            Users::EVENT_BEFORE_ACTIVATE_USER,
            function (UserEvent $event) {
                $this->handleStatus($event);
            }
        );
    }

    public function handleStatus($event)
    {
        $user = $event->user; 

        // Ensure you're working with a valid user
        if ($user instanceof User) {
            $status = $user->getFieldValue('customStatus')->value;
            $currentDate = new DateTime('now', new DateTimeZone('CET')); // Get the current date in CET timezone
            
            if ($status === 'renew') {
                $dateCreated = $user->dateCreated;
                $currentYear = (int) $currentDate->format('Y'); // Extract the current year
    
                $newYear = $currentYear + 1;
    
                $newDate = DateTime::createFromFormat(
                    'Y-m-d H:i:s',
                    sprintf('%d-%02d-%02d %02d:%02d:%02d',
                        $newYear,
                        $dateCreated->format('m'), // Original month
                        $dateCreated->format('d'), // Original day
                        $dateCreated->format('H'), // Original hour
                        $dateCreated->format('i'), // Original minute
                        $dateCreated->format('s')  // Original second
                    ),
                    new DateTimeZone('CET')
                );

                $user->setFieldValue('customStatus', 'active');
                $user->setFieldValue('paymentDate', null);
                $user->setFieldValue('paymentType', null);
                $user->setFieldValue('renewedDate', $currentDate);
                $user->setFieldValue('statusChangeDate', $currentDate);
                $user->setFieldValue('memberDueDate', $newDate);
                
                // Save the updated user to persist the changes
                if (!Craft::$app->elements->saveElement($user)) {
                    Craft::error('Failed to update accountStatus for user ID: ' . $user->id, __METHOD__);
                    Craft::error('Errors: ' . json_encode($user->getErrors()), __METHOD__);
                } else {
                    Craft::info('Successfully set accountStatus to "renew" for user ID: ' . $user->id, __METHOD__);
                } 

                $relatedEntries = Entry::find()
                    ->section('extraMembers')  // Adjust if needed
                    ->relatedTo($user) // Find entries related to this user
                    ->all();

                foreach ($relatedEntries as $entry) {
                    // ✅ Update memberDueDate for related entry
                    $entry->setFieldValue('memberDueDate', $newDate);
            
                    // ✅ Save the updated entry
                    if (!Craft::$app->elements->saveElement($entry)) {
                        Craft::error('Failed to update memberDueDate for related entry ID: ' . $entry->id, __METHOD__);
                    } else {
                        Craft::info('Successfully updated memberDueDate for related entry ID: ' . $entry->id, __METHOD__);
                    }
                }
            }

            if ($status === "new") {
                $user->setFieldValue('customStatus', 'active');
                $user->setFieldValue('statusChangeDate', $currentDate);

                if (!Craft::$app->elements->saveElement($user)) {
                    Craft::error('Failed to update accountStatus for user ID: ' . $user->id, __METHOD__);
                    Craft::error('Errors: ' . json_encode($user->getErrors()), __METHOD__);
                } else {
                    Craft::info('Successfully set accountStatus to "renew" for user ID: ' . $user->id, __METHOD__);
                } 
            }

            if ($status === "deactivated") {
                $user->setFieldValue('customStatus', 'active');
                $user->setFieldValue('statusChangeDate', $currentDate);

                if (!Craft::$app->elements->saveElement($user)) {
                    Craft::error('Failed to update accountStatus for user ID: ' . $user->id, __METHOD__);
                    Craft::error('Errors: ' . json_encode($user->getErrors()), __METHOD__);
                } else {
                    Craft::info('Successfully set accountStatus to "renew" for user ID: ' . $user->id, __METHOD__);
                } 

                $relatedEntries = Entry::find()
                ->section('extraMembers') 
                ->relatedTo($user)     
                ->all();

                foreach ($relatedEntries as $entry) {
                    $entry->enabled = false; // Disable the entry
                    if (!Craft::$app->elements->saveElement($entry)) {
                        Craft::error("Failed to deactivate entry ID {$entry->id}.", __METHOD__);
                    }
                }
            }

            if ($status === "old" && $status === "oldRenuw") {
                $user->setFieldValue('customStatus', 'active');
                $user->setFieldValue('statusChangeDate', $currentDate);

                if (!Craft::$app->elements->saveElement($user)) {
                    Craft::error('Failed to update accountStatus for user ID: ' . $user->id, __METHOD__);
                    Craft::error('Errors: ' . json_encode($user->getErrors()), __METHOD__);
                } else {
                    Craft::info('Successfully set accountStatus to "renew" for user ID: ' . $user->id, __METHOD__);
                } 

                $relatedEntries = Entry::find()
                ->section('extraMembers') 
                ->relatedTo($user)     
                ->all();

                foreach ($relatedEntries as $entry) {
                    $entry->enabled = false; // Disable the entry
                    if (!Craft::$app->elements->saveElement($entry)) {
                        Craft::error("Failed to deactivate entry ID {$entry->id}.", __METHOD__);
                    }
                }
            }

        } else {
            Craft::error('Event did not provide a valid User object.', __METHOD__);
        }
    }

}
