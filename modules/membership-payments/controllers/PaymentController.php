<?php 

namespace modules\membershippayments\controllers;

use Craft;     
use craft\web\Controller;
use craft\elements\Entry;
use craft\elements\User;
use modules\membershippayments\MembershipPayments;
use Money\Money;
use Money\Currency;
use DateTime;
use yii\web\Response;
use craft\helpers\UrlHelper;

class PaymentController extends Controller
{
    public array|int|bool $allowAnonymous = ['webhook'];
    public $enableCsrfValidation = false;

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
            $redirectUrl = Craft::$app->getUrlManager()->createAbsoluteUrl('/');
        }
        
        $payment = $mollie->payments->create([
            "amount" => [
                "currency" => "EUR",
                "value" => number_format($totalFormatted, 2, '.', ''), // Lowercase 'value'
            ],
            "description" => "Membership Payment for " . $user->email,
            "redirectUrl" => $redirectUrl,
            "webhookUrl" => UrlHelper::actionUrl('membership-payments/payment/webhook'),
            "metadata" => [
                "userId" => $user->id,
                "extraMemberIds" => $extraMemberIds,
            ],
        ]);

        return $this->redirect($payment->getCheckoutUrl());
    }

    public function actionWebhook(): Response
    {
        $mollie = MembershipPayments::getInstance()->getMollie();
        $paymentId = Craft::$app->getRequest()->getBodyParam('id');

        if (!$paymentId) {
            Craft::error('Payment ID not provided in webhook.', __METHOD__);
            return $this->asJson(['success' => false]);
        }

        $payment = $mollie->payments->get($paymentId);

        if ($payment->isPaid()) {
            $metadata = $payment->metadata;

            $userId = $metadata->userId ?? null;
            $extraMemberIds = $metadata->extraMemberIds ?? [];

            $paymentDate = new DateTime();
            $expirationDate = (clone $paymentDate)->modify('+1 year');

            if ($userId) {
                $user = Craft::$app->users->getUserById($userId);
                if ($user) {
                    $user->setFieldValue('paymentDate', $paymentDate);
                    $user->setFieldValue('expPaymentDate', $expirationDate);
                    if (!Craft::$app->elements->saveElement($user)) {
                        Craft::error('Failed to update user payment date.', __METHOD__);
                    }
                }
            }

            // Update extra member entries
            foreach ($extraMemberIds as $extraMemberId) {
                $extraMember = Entry::find()->id($extraMemberId)->one();
                if ($extraMember) {
                    $extraMember->setFieldValue('paymentDate', $paymentDate);
                    $extraMember->setFieldValue('expPaymentDate', $expirationDate);
                    if (!Craft::$app->elements->saveElement($extraMember)) {
                        Craft::error('Failed to update extra member payment date for entry ID ' . $extraMemberId, __METHOD__);
                    }
                }
            }
        }

        return $this->asJson(['success' => true]);
    }
}
