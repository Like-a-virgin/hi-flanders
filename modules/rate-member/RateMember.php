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
                    $currentUser = (Craft::$app->user->identity && Craft::$app->user->identity->isInGroup('membersAdminSuper'));
                    
                    if ($customStatus === 'new' || $customStatus === 'renew' || !$memberRate || $currentUser) {
                        $this->assignMemberRate($element);
                    }
                }
            },
        );
        
        Event::on(
            Elements::class,
            Elements::EVENT_AFTER_SAVE_ELEMENT,
            function (ElementEvent $event) {
                $element = $event->element;
        
                if ($element instanceof User) {
                    $customStatus = $element->getFieldValue('customStatus')->value;
                    if ($customStatus === 'renew') {
                        $this->handleRenewalNotification($element);
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
                if (is_array($birthday) && isset($birthday['date'])) {
                    $birthday = new \DateTime($birthday['date'], new \DateTimeZone($birthday['timezone'] ?? 'UTC'));
                } else {
                    $birthday = new \DateTime($birthday);
                }
            } catch (\Throwable $e) {
                Craft::error('Invalid birthday value for user ID ' . $user->id . ': ' . $e->getMessage(), __METHOD__);
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

        $paymentType = $user->getFieldValue('paymentType')->value ?? $request->getBodyParam('fields.paymentType');
        
        if ($paymentType and $user->getFieldValue('customStatus') != 'renew') {
            $user->setFieldValue('paymentDate', $paymentDate);
            $user->setFieldValue('paymentType', $paymentType);
        } elseif ($paymentType and $user->getFieldValue('customStatus') == 'renew'){ 
            $user->setFieldValue('paymentDate', null);
            $user->setFieldValue('paymentType', null);
            $user->setFieldValue('totalPayedMembers', 0);
        } elseif ($ratePrice === null || (float) $ratePrice <= 0) {
            $user->setFieldValue('paymentDate', $paymentDate);
            $user->setFieldValue('paymentType', 'free');
            $user->setFieldValue('totalPayedMembers', 0);
        } else {
            $user->setFieldValue('paymentDate', null);
            $user->setFieldValue('paymentType', null);
            $user->setFieldValue('totalPayedMembers', 0);
        }

        $user->setDirtyFields(['paymentDate', 'paymentType']);
    }

    private function handleRenewalNotification(User $user): void
    {   
        $customStatus = $user->getFieldValue('customStatus')->value;
        if ($customStatus !== 'renew') {
            return;
        }
        
        $lang = $user->getFieldValue('lang')->value ?? 'nl';
        $memberType = $user->getFieldValue('memberType')->value ?? 'individual';
        $email = $user->email;
        
        $templateBase = 'email/renew/';
        $templatePath = $templateBase . $lang . '/renew';
        
        $name = $memberType === 'individual'
        ? ($user->getFieldValue('altFirstName') ?? 'lid')
        : ($user->getFieldValue('organisation') ?? 'organisatie');
        
        $url = Craft::$app->getSites()->getCurrentSite()->getBaseUrl();
        
        $rate = $user->getFieldValue('memberRate')->one();
        if (!$rate) return;
        
        $price = $rate->getFieldValue('price');
        if ($price instanceof \Money\Money) {
            $price = (float) $price->getAmount() / 100;
        } elseif (is_numeric($price)) {
            $price = (float) $price;
        } else {
            $price = null;
        }
        
        if ($price === null || $price <= 0) return;
        
        $minAge = $rate->getFieldValue('minAge');
        $birthday = $user->getFieldValue('birthday');
        
        try {
            if (!($birthday instanceof \DateTime)) {
                if (is_array($birthday) && isset($birthday['date'])) {
                    $birthday = new \DateTime($birthday['date'], new \DateTimeZone($birthday['timezone'] ?? 'UTC'));
                } else {
                    $birthday = new \DateTime($birthday);
                }
            }
        
            if ($birthday instanceof \DateTime) {
                $age = (new \DateTime())->diff($birthday)->y;
                if ($minAge !== null && $age === $minAge) {
                    $templatePath = 'email/renew/' . $lang . '/renew-at-age';
                }
            }
        } catch (\Throwable $e) {
            Craft::error('Error calculating age for user ID ' . $user->id . ': ' . $e->getMessage(), __METHOD__);
        }
        
        try {
            Craft::$app->getView()->setTemplatesPath(Craft::getAlias('@root/templates'));
            $htmlBody = Craft::$app->getView()->renderTemplate($templatePath, [
                'name' => $name,
                'url' => $url,
            ]);

            $subject = match ($lang) {
                'en' => 'Hi Flanders – Keep enjoying your benefits!',
                'fr' => 'Hi Flanders - Continuez à profiter de nos avantages !',
                default => 'Hi Flanders - Blijf genieten van onze voordelen!',
            };
            
            $success = Craft::$app->mailer->compose()
                ->setTo($email)
                ->setSubject($subject)
                ->setHtmlBody($htmlBody)
                ->send();

            if ($success) {
                Craft::info("Renewal email sent to user: $email", __METHOD__);
            } else {
                Craft::error("Failed to send renewal email to user: $email", __METHOD__);
            }
        } catch (\Throwable $e) {
            Craft::error("Failed to render/send renewal email to user: $email - " . $e->getMessage(), __METHOD__);
        }
    }
}
