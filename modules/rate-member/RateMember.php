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
        $memberType = $user->getFieldValue('memberType');

        $memberRates = Entry::find()
            ->section('rates') 
            ->all();

        if ($memberType === 'group') {
            // Assign the first rate with the memberType 'group'
            $groupRate = array_filter($memberRates, function ($rate) {
                $rateMemberType = $rate->getFieldValue('memberType');
                return $rateMemberType === 'group';
            });
    
            if (!empty($groupRate)) {
                $user->setFieldValue('memberRate', [reset($groupRate)->id]);
                return; // Exit once we've assigned the group rate
            }
        }

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


        
        $individualRate = array_filter($memberRates, function ($rate) use ($age) {
            $minAge = $rate->getFieldValue('minAge');
            $maxAge = $rate->getFieldValue('maxAge');
            $rateMemberType = $rate->getFieldValue('memberType');
    
            return $rateMemberType === 'individual' && $minAge <= $age && ($maxAge === null || $maxAge >= $age);
        });
    
        if (!empty($individualRate)) {
            $user->setFieldValue('memberRate', [reset($individualRate)->id]);
        }
    }
}
