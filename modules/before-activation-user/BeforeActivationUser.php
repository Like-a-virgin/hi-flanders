<?php

namespace modules\beforeactivationuser;

use Craft;
use craft\elements\User;
use craft\services\Users;
use craft\events\UserEvent;
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
            
            if ($status === 'renew') {
                $dateCreated = $user->dateCreated;
    
                $currentDate = new DateTime('now', new DateTimeZone('CET')); // Get the current date in CET timezone
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
                $user->setFieldValue('memberDueDate', $newDate);
                
                // Save the updated user to persist the changes
                if (!Craft::$app->elements->saveElement($user)) {
                    Craft::error('Failed to update accountStatus for user ID: ' . $user->id, __METHOD__);
                    Craft::error('Errors: ' . json_encode($user->getErrors()), __METHOD__);
                } else {
                    Craft::info('Successfully set accountStatus to "renew" for user ID: ' . $user->id, __METHOD__);
                } 
            }

            if ($status === "new") {
                $user->setFieldValue('customStatus', 'active');
            }

        } else {
            Craft::error('Event did not provide a valid User object.', __METHOD__);
        }
    }

}
