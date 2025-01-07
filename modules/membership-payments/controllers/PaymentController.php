<?php 

namespace modules\membershippayments\controllers;

use Craft;     
use craft\web\Controller;
use modules\membershippayments\MembershipPayments;
use craft\elements\Entry;
use Money\Money;
use Money\Currency;
use craft\helpers\UrlHelper;

class PaymentController extends Controller
{
    public array|int|bool $allowAnonymous = ['webhook'];

    public function actionInitiatePayment()
    {
        $user = Craft::$app->user->identity;

        if (!$user) {
            return $this->asFailure('User not logged in');
        }

        $relatedUserRateEntry = $user->getFieldValue('memberRate')[0] ?? null;

        if ($relatedUserRateEntry) {
            $userRate = $relatedUserRateEntry->getFieldValue('price');
        } else {
            $userRate = new Money(0, new Currency('EUR')); 
        }

        $totalRate = $userRate;

        $extraMembers = Entry::find()
            ->section('extraMembers')
            ->relatedTo($user)
            ->all();

        $extraMembersIds = [];
        foreach ($extraMembers as $extraMember) {
            $extraMemberIds[] = $extraMember->id;
        }

        foreach ($extraMembers as $extraMember) {
            $relatedEntry = $extraMember->getFieldValue('memberRate')[0] ?? null;

            if ($relatedEntry) {
                $price = $relatedEntry->getFieldValue('price');

                $totalRate = $totalRate->add($price);
            }
        }

        $totalAmount = $totalRate->getAmount(); // Total as integer in cents
        $totalFormatted = number_format($totalAmount / 100, 2); // Convert to euros

        $mollie = MembershipPayments::getInstance()->getMollie();

        $thankYouPage = Entry::find()
            ->section('succesPayment')
            ->one();

        if ($thankYouPage) {
            $redirectUrl = $thankYouPage->url;
        } else {
            $redirectUrl = Craft::$app->getUrlManager()->createAbsoluteUrl('default-thank-you');
        }
        
        $payment = $mollie->payments->create([
            "amount" => [
                "currency" => "EUR",
                "value" => number_format($totalFormatted, 2, '.', ''), // Lowercase 'value'
            ],
            "description" => "Membership Payment for " . $user->email,
            "redirectUrl" => $redirectUrl,
            "metadata" => [
                "userId" => $user->id,
                "extraMemberIds" => $extraMemberIds,
            ],
        ]);

        return $this->redirect($payment->getCheckoutUrl());
    }
}
