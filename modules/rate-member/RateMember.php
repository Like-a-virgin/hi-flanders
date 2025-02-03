<?php

namespace modules\ratemember;

use Craft;
use craft\elements\User;
use craft\elements\Entry;
use craft\events\ElementEvent;
use craft\services\Elements;
use yii\base\Event;

use yii\base\Module as BaseModule;

/**
 * RateMember module
 *
 * @method static RateMember getInstance()
 */
class RateMember extends BaseModule
{
    public function init(): void 
    {
        Craft::setAlias('@modules/ratemember', __DIR__);

        parent::init();

        Event::on(
            Elements::class,
            Elements::EVENT_BEFORE_SAVE_ELEMENT,
            function (ElementEvent $event) {
                $element = $event->element;

                if ($element instanceof User) {
                    $customStatus = $element->getFieldValue('customStatus')->value;
                    $memberRate = $element->getFieldValue('memberRate')->one();
                    $status = $element->status;

                    if ($customStatus === 'new' && $status !== 'active' || $customStatus === 'renew' && $status === 'pending' || !$memberRate) {
                        $this->assignMemberRate($element);
                    }
                }
            }
        );
    }

    private function assignMemberRate(User $user): void
    {
        $request = Craft::$app->getRequest();
        
        $request = Craft::$app->getRequest();
        if (!$request->getIsConsoleRequest()) {
            $birthday = $request->getBodyParam("fields.birthday") ?? $user->getFieldValue('birthday');
            $memberType = $request->getBodyParam('fields.memberType') ?? $user->getFieldValue('memberType')->value;
        } else {
            // If in console mode (e.g., queue job), get values directly from the user
            $birthday = $user->getFieldValue('birthday');
            $memberType = $user->getFieldValue('memberType')->value ?? null;
        }


        if (!$memberType) {
            Craft::error('No memberType set for user ID ' . $user->id, __METHOD__);
            return;
        }
        
        $memberRates = Entry::find()
        ->section('rates')
        ->all();

        $filteredRates = array_filter($memberRates, function ($rate) use ($memberType) {
            $rateMemberType = $rate->getFieldValue('memberType')->value;
            return $rateMemberType === $memberType;
        });

        if (empty($filteredRates)) {
            Craft::error('No rates found for memberType: ' . $memberType . ' for user ID ' . $user->id, __METHOD__);
            return;
        }
    
        if ($memberType === 'individual') {
            $this->handleIndividualRate($user, $birthday, $filteredRates);
        } else {
            $this->assignRateWithOptionalPayment($user, reset($filteredRates)); // Assign the first rate for non-individual memberType
        }
    }

    private function handleIndividualRate(User $user, $birthday, array $filteredRates): void
    {
        if (!($birthday instanceof \DateTime)) {
            try {
                $birthday = new \DateTime($birthday);
            } catch (\Exception $e) {
                Craft::error('Invalid birthday format for user ID ' . $user->id, __METHOD__);
                return;
            }
        }

        $currentDate = new \DateTime();
        $age = $currentDate->diff($birthday)->y;

        // Filter rates by age range
        $ageFilteredRates = array_filter($filteredRates, function ($rate) use ($age) {
            $minAge = $rate->getFieldValue('minAge');
            $maxAge = $rate->getFieldValue('maxAge');
            return ($minAge === null || $minAge <= $age) && ($maxAge === null || $maxAge >= $age);
        });

        if (!empty($ageFilteredRates)) {
            $this->assignRateWithOptionalPayment($user, reset($ageFilteredRates));
        } else {
            Craft::info('No age-appropriate rate found for user ID ' . $user->id . ', assigning first rate.', __METHOD__);
            $this->assignRateWithOptionalPayment($user, reset($filteredRates)); // Assign the first rate if no age-specific match
        }
    }

    private function assignRateWithOptionalPayment(User $user, $rate): void
    {
        $request = Craft::$app->getRequest();
        $user->setFieldValue('memberRate', [$rate->id]);

        $ratePriceField = $rate->getFieldValue('price');

        if ($ratePriceField instanceof \Money\Money) {
            // Convert Money object to float
            $ratePrice = (float) $ratePriceField->getAmount() / 100; // Adjust divisor if amounts are stored as cents
        } elseif (is_numeric($ratePriceField)) {
            // Handle numeric values directly
            $ratePrice = (float) $ratePriceField;
        } else {
            $ratePrice = null; // Default fallback if price cannot be determined
        }

        $currentDate = new \DateTime();
        $paymentDate = $currentDate->format('Y-m-d');
        $expirationDate = $currentDate->modify('+1 year')->format('Y-m-d');

        $paymentType = null;
        if (!$request->getIsConsoleRequest()) {
            $paymentType = $request->getBodyParam('fields.paymentType');
        } else {
            // Use existing field value for console requests
            $paymentType = $user->getFieldValue('paymentType') ?? null;
        }

        if ($paymentType) {
            $user->setFieldValue('paymentDate', $paymentDate);
            $user->setFieldValue('paymentType', $paymentType);
    
            Craft::info("Assigned rate with ID {$rate->id}, paymentType {$paymentType}, paymentDate {$paymentDate}, and expirationDate {$expirationDate} for user ID {$user->id}", __METHOD__);
        } elseif ($ratePrice === null || (float) $ratePrice <= 0) {
            $user->setFieldValue('paymentDate', $paymentDate);
            $user->setFieldValue('paymentType', 'free');

        } else {
            $user->setFieldValue('paymentDate', null);
            $user->setFieldValue('paymentType', null);

            Craft::info("Assigned rate with ID {$rate->id} without paymentDate for user ID {$user->id}", __METHOD__);
        }
    }
}
