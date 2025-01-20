<?php

namespace modules\rateextramember;

use Craft;
use craft\elements\Entry;
use craft\events\ElementEvent;
use craft\services\Elements;
use yii\base\Event;
use yii\base\Module as BaseModule;

/**
 * RateExtraMember module
 *
 * @method static RateExtraMember getInstance()
 */
class RateExtraMember extends BaseModule
{
    public function init(): void
    {
        parent::init();

        // Hook into the `EVENT_BEFORE_SAVE_ELEMENT` to handle "extra members"
        Event::on(
            Elements::class,
            Elements::EVENT_BEFORE_SAVE_ELEMENT,
            function (ElementEvent $event) {
                $element = $event->element;

                // Check if the element is an Entry and belongs to the "extraMembers" section
                if ($element instanceof Entry && $element->section->handle === 'extraMembers') {
                    $this->assignMemberRate($element);
                }
            }
        );
    }

    private function assignMemberRate(Entry $extraMember): void
    {
        // Get the birthday field value from the entry
        $birthday = $extraMember->getFieldValue('birthday');

        if (!$birthday) {
            Craft::error('No birthday provided for extra member.', __METHOD__);
            return; 
        }

        // Ensure the birthday is a valid DateTime object
        if (!($birthday instanceof \DateTime)) {
            try {
                $birthday = new \DateTime($birthday);
            } catch (\Exception $e) {
                Craft::error('Invalid birthday format: ' . $birthday, __METHOD__);
                return; 
            }
        }

        // Calculate age
        $currentDate = new \DateTime();
        $age = $currentDate->diff($birthday)->y;

        // Fetch all member rates
        $memberRates = Entry::find()
            ->section('rates') // The section handle for "rates"
            ->all();

        // Filter to find the appropriate rate
        $memberRateCurrent = array_filter($memberRates, function ($rate) use ($age) {
            $minAge = $rate->getFieldValue('minAge');
            $maxAge = $rate->getFieldValue('maxAge');
            $memberTypeField = $rate->getFieldValue('memberType');
            $memberType = $memberTypeField ? $memberTypeField->value : null;

            return $minAge <= $age && ($maxAge === null || $maxAge >= $age) && $memberType === 'individual';
        });

        // Assign the first matched rate to the extra member
        if (!empty($memberRateCurrent)) {
            $selectedRate = reset($memberRateCurrent);
            $extraMember->setFieldValue('memberRate', [$selectedRate->id]);

            // Check the rate price
            $ratePriceField = $selectedRate->getFieldValue('price');
            if ($ratePriceField instanceof \Money\Money) {
                $ratePrice = (float) $ratePriceField->getAmount() / 100;
            } elseif (is_numeric($ratePriceField)) {
                $ratePrice = (float) $ratePriceField;
            } else {
                $ratePrice = null;
            }

            if ($ratePrice === null || $ratePrice <= 0) {
                // Set payment and expiration dates if price is null or 0
                $paymentDate = $currentDate->format('Y-m-d');
                $expirationDate = $currentDate->modify('+1 year')->format('Y-m-d');

                $extraMember->setFieldValue('paymentDate', $paymentDate);
                $extraMember->setFieldValue('expPaymentDate', $expirationDate);

                Craft::info("Assigned rate with ID {$selectedRate->id} and set paymentDate to {$paymentDate} for extra member ID {$extraMember->id}", __METHOD__);
            } else {
                Craft::info("Assigned rate with ID {$selectedRate->id} without paymentDate for extra member ID {$extraMember->id}", __METHOD__);
            }
        } else {
            Craft::error('No appropriate member rate found for extra member.', __METHOD__);
        }
    }
}
