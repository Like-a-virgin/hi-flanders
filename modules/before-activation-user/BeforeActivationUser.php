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
            $currentDate = new DateTime('now', new DateTimeZone('CET'));

            $rateEntry = $user->getFieldValue('memberRate')->one();
            $ratePriceField = $rateEntry ? $rateEntry->getFieldValue('price') : null;
            $ratePrice = $ratePriceField ? (float) $ratePriceField->getAmount() / 100 : null;

            if ($status === "new") {
                if ($rateEntry && $ratePrice <= 0) {
                    $user->setFieldValue('customStatus', 'active');
                }

                $user->setFieldValue('statusChangeDate', $currentDate);

                if (!Craft::$app->elements->saveElement($user)) {
                    Craft::error('Failed to update accountStatus for user ID: ' . $user->id, __METHOD__);
                    Craft::error('Errors: ' . json_encode($user->getErrors()), __METHOD__);
                } else {
                    Craft::info('Successfully set accountStatus to "renew" for user ID: ' . $user->id, __METHOD__);
                }
            }

            if ($status === "deactivated") {
                $memberDueDate = $user->getFieldValue('memberDueDate');

                if ($rateEntry && $ratePrice <= 0) {
                    $user->setFieldValue('customStatus', 'active');
                } elseif ($rateEntry && $ratePrice > 0) {
                    if ($memberDueDate > $currentDate) {
                        $user->setFieldValue('customStatus', 'renew');
                    } elseif ($memberDueDate < $currentDate) {
                        $user->setFieldValue('customStatus', 'active');
                    } else {
                        $user->setFieldValue('customStatus', 'new');
                    }
                }

                $user->setFieldValue('statusChangeDate', $currentDate);

                if (!Craft::$app->elements->saveElement($user)) {
                    Craft::error('Failed to update accountStatus for user ID: ' . $user->id, __METHOD__);
                    Craft::error('Errors: ' . json_encode($user->getErrors()), __METHOD__);
                } else {
                    Craft::info('Successfully set accountStatus to "renew" for user ID: ' . $user->id, __METHOD__);
                }
            }

            if ($status === "old" || $status === "oldRenew") {
                if ($status === 'old') {
                    $user->setFieldValue('customStatus', 'active');
                } else {
                    $user->setFieldValue('customStatus', 'renew');
                }

                $today = new \DateTime('now', new \DateTimeZone('CET'));
                $user->setFieldValue('statusChangeDate', $today);

                $memberRate = $user->getFieldValue('memberRate')->one()->getFieldValue('price')->getAmount();
                $memberDueDate = $user->getFieldValue('memberDueDate');
                $dueDate = $memberDueDate instanceof DateTime ? $memberDueDate : new DateTime($memberDueDate);

                if ($memberRate == 0 and $dueDate < $today) {
                    $user->setFieldValue('renewedDate', $today);
                    $oneYearLater = clone $today;
                    $oneYearLater->modify('+1 year');
                    $user->setFieldValue('memberDueDate', $oneYearLater);
                    $user->setFieldValue('totalPayedMembers', 0);
                    $user->setFieldValue('paymentType', 'free');
                    $user->setFieldValue('paymentDate', $today);
                }

                if (!Craft::$app->elements->saveElement($user)) {
                    Craft::error("Failed to update user status to active for user {$user->email}", __METHOD__);
                } else {
                    Craft::info("Updated user status to active for user {$user->email}", __METHOD__);
                }
            }
        } else {
            Craft::error('Event did not provide a valid User object.', __METHOD__);
        }
    }
}
