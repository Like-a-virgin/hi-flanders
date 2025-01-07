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
        parent::init();

        Event::on(
            Elements::class,
            Elements::EVENT_BEFORE_SAVE_ELEMENT,
            function (ElementEvent $event) {
                $element = $event->element;

                if ($element instanceof User) {
                    $this->assignMemberRate($element);
                }
            }
        );
    }

    private function assignMemberRate(User $user): void
    {
        $birthday = $user->getFieldValue('birthday');

        if (!$birthday) {
            return; 
        }

        if (!($birthday instanceof \DateTime)) {
            try {
                $birthday = new \DateTime($birthday);
            } catch (\Exception $e) {
                Craft::error('Invalid birthday format: ' . $birthday, __METHOD__);
                return; 
            }
        }

        $currentDate = new \DateTime();
        $age = $currentDate->diff($birthday)->y;

        $memberRates = Entry::find()
            ->section('rates') 
            ->all();
        
        $memberRateCurrent = array_filter($memberRates, function ($rate) use ($age) {
            $minAge = $rate->getFieldValue('minAge');
            $maxAge = $rate->getFieldValue('maxAge');
            $memberTypeField = $rate->getFieldValue('memberType');

            $memberType = $memberTypeField ? $memberTypeField->value : null;
        
            return $minAge <= $age && ($maxAge === null || $maxAge >= $age) && $memberType === 'individual';
        });
    
        $user->setFieldValue('memberRate', [reset($memberRateCurrent)->id]);
    }
}
