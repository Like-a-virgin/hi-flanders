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
use DateInterval;
use DateTimeZone;
use yii\web\Response;
use craft\helpers\UrlHelper;
use Exception;

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

        $totalMembershipRate = new Money(0, new Currency('EUR'));
        $totalPrintRate = new Money(0, new Currency('EUR'));

        $relatedUserRateEntry = $user->getFieldValue('memberRate')[0] ?? null;;

        if ($relatedUserRateEntry) {
            $priceUser = $relatedUserRateEntry->getFieldValue('price');
            if ($priceUser) {
                $userRate = $relatedUserRateEntry->getFieldValue('price');
    
            } else {
                $userRate = new Money(0, new Currency('EUR')); 
            }
        } else {
            $userRate = new Money(0, new Currency('EUR')); 
        }

        $paymentDate = $user->getFieldValue('paymentDate');
        $memberDueDate = $user->getFieldValue('memberDueDate');

        $today = new DateTime();

        if ($memberDueDate <= $today) {
            $totalMembershipRate = $totalMembershipRate->add($userRate);
        }

        // $extraMemberIds = [];
        // $extraMembers = Entry::find()
        //     ->section('extraMembers')
        //     ->relatedTo($user)
        //     ->all();

        // foreach ($extraMembers as $extraMember) {
        //     $relatedEntry = $extraMember->getFieldValue('memberRate')[0] ?? null;
    
        //     if ($relatedEntry) {
        //         $priceRelatedEntry = $relatedEntry->getFieldValue('price');
    

        //         if ($priceRelatedEntry) {
        //             $price = $relatedEntry->getFieldValue('price');
        //         } else {
        //             $price = new Money(0, new Currency('EUR')); 
        //         }
    
        //         // Check if extra member is within payment period
        //         if (!$this->isWithinPaymentPeriod($extraMember)) {
        //             $totalMembershipRate = $totalMembershipRate->add($price);
        //             $extraMemberIds[] = $extraMember->id;
        //         }
        //     }
        // }

        $printRequest = $user->getFieldValue('requestPrint');
        $printPaydate = $user->getFieldValue('payedPrintDate');

        $printRateEntry = Entry::find()
        ->section('rates')
        ->memberType('card')
        ->one();

        $printRate = $printRateEntry->getFieldValue('price');
        $print = false;

        if ($printRequest and !$printPaydate) {
            $totalPrintRate = $totalPrintRate->add($printRate);
        }

        $totalRate = $totalMembershipRate->add($totalPrintRate);
        $totalAmount = $totalRate->getAmount(); // Convert to cents
        $totalFormatted = number_format($totalAmount / 100, 2); 


        if ($totalAmount === 0) {
            return $this->asFailure('No payment required. All members are already paid.');
        }

        $mollie = MembershipPayments::getInstance()->getMollie();

        $thankYouPage = Entry::find()
            ->section('succesPayment')
            ->one();

        $redirectUrl = $thankYouPage ? $thankYouPage->url : Craft::$app->getUrlManager()->createAbsoluteUrl('/');

        $membershipPayments = $totalMembershipRate->getAmount() > 0 ? true : false;
        $printPayment = $totalPrintRate->getAmount() > 0 ? true : false;
        
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
                "membershipId" => $user->getFieldValue('customMemberId'),
                // "extraMemberIds" => $extraMemberIds,
                "print" => $printPayment,
                "memberships" => $membershipPayments,
                "total" => number_format($totalFormatted, 2, '.', ''),
                "membershipTotal" => $totalMembershipRate->getAmount(), 
                "printTotal" => $totalPrintRate->getAmount(), 
            ],
        ]);

        return $this->redirect($payment->getCheckoutUrl());
    }

    private function isWithinPaymentPeriod($element): bool
    {
        $paymentDate = $element->getFieldValue('paymentDate');
        $memberDueDate = $element->getFieldValue('memberDueDate');

        if (!$paymentDate || !$memberDueDate) {
            return false; // No payment date or expiry, assume not within the payment period
        }

        $today = new DateTime('today');

        return ($paymentDate <= $today && $memberDueDate >= $today);
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
            // $extraMemberIds = $metadata->extraMemberIds ?? [];
            $totalAmount = $metadata->total;
            $print = $metadata->print ?? false;
            $memberships = $metadata->memberships ?? false;

            $paymentDate = new DateTime();
            $dueDate = new DateTime();
            $nextYearDate = $dueDate->add(new DateInterval('P1Y'));

            if ($userId) {
                $user = Craft::$app->users->getUserById($userId);
                if ($user) {
                    
                    if ($memberships) {
                        $user->setFieldValue('renewedDate', $paymentDate);
                        $user->setFieldValue('memberDueDate', $nextYearDate);
                        $user->setFieldValue('paymentDate', $paymentDate);
                        $user->setFieldValue('paymentType', 'online');
                        $user->setFieldValue('customStatus', 'active');
                        $user->setFieldValue('totalPayedMembers', $metadata->membershipTotal);                       
                    }
                    
                    if ($print) {
                        $user->setFieldValue('totalPayedPrint', $metadata->printTotal);
                        $user->setFieldValue('payedPrintDate', $paymentDate);
                        $user->setFieldValue('printStatus', 'requested');
                    }
                        
                    if (Craft::$app->elements->saveElement($user, false)) {
                        Craft::info("User payment updated successfully: {$userId}", __METHOD__);
    
                        if ($memberships) {
                            $this->sendAccountConfirmationEmail($user);
                        }
    
                        if ($print) {
                            $this->sendPrintDetailsOwner($user);
                        }
    
                        $this->sendPaymentConfirmationEmail($user, $totalAmount);

                        return $this->asJson(['success' => true]);
                    } else {
                        Craft::error("Failed to update user payment for user ID: {$userId}", __METHOD__);
                    }
                }
            }

            // foreach ($extraMemberIds as $extraMemberId) {
            //     $extraMember = Entry::find()->id($extraMemberId)->one();
            //     if ($extraMember) {
            //         $extraMember->setFieldValue('paymentDate', $paymentDate);
            //         if (!Craft::$app->elements->saveElement($extraMember, false)) {
            //             Craft::error('Failed to update extra member payment date for entry ID ' . $extraMemberId, __METHOD__);
            //         }
            //     }
            // }
        }

        return $this->asJson(['success' => true]);
    }

    private function sendPaymentConfirmationEmail(User $user, $total)
    {
        $memberType = $user->getFieldValue('memberType')->value;
        
        $lang = $user->getFieldValue('lang')->value;
        $baseTemplateUrl = 'email/verification/' . $lang;
        $templatePath = $baseTemplateUrl . '/verification-payment';
        
        $paymentDate = new DateTime();
        $formattedDate = $paymentDate->format('d-m-Y');

        if ($memberType === 'group' || $memberType === 'groupYouth') {
            $name = $user->getFieldValue('organisation');
        } else {
            $name = $user->getFieldValue('altFirstName');
        }

        try {
            $mailer = Craft::$app->mailer;
            Craft::$app->getView()->setTemplatesPath(Craft::getAlias('@root/templates'));

            $htmlBody = Craft::$app->getView()->renderTemplate($templatePath, [
                'name' => $name,
                'total' => $total,
                'date' => $formattedDate
            ]);

            if ($lang === 'en') {
                $subject = 'Your payment receipt from Hi Flanders';
            } elseif ($lang === 'fr') {
                $subject = 'Votre reçu de paiement Hi Flanders';
            } else {
                $subject = 'Je betalingsbewijs van Hi Flanders';
            }

            // Prepare and send the email
            $message = $mailer->compose()
                ->setTo($user->email)
                ->setSubject($subject)
                ->setHtmlBody($htmlBody);

            if (!$message->send()) {
                Craft::error('Failed to send payment confirmation email to: ' . $user->email, __METHOD__);
            } else {
                Craft::info('Payment confirmation email sent to: ' . $user->email, __METHOD__);
            }
        } catch (\Throwable $e) {
            Craft::error("Error sending payment confirmation email: " . $e->getMessage(), __METHOD__);
        }
    }

    private function sendAccountConfirmationEmail(User $user)
    {
        $memberType = $user->getFieldValue('memberType')->value;
        $paymentType = $user->getFieldValue('paymentType')->value;

        $lang = $user->getFieldValue('lang')->value;
        $baseTemplateUrl = 'email/verification/' . $lang;
        

        try {
            $mailer = Craft::$app->mailer;
            Craft::$app->getView()->setTemplatesPath(Craft::getAlias('@root/templates'));

            if ($memberType === 'group' && $paymentType === 'online') {
                $templatePath = $baseTemplateUrl . '/verification-group';

                $htmlBody = Craft::$app->getView()->renderTemplate($templatePath, [
                    'name' => $user->getFieldValue('organisation'),
                ]);
    
                if ($lang === 'en') {
                    $subject = 'Activation successful! Your group is now a Hi Flanders member!';
                } elseif ($lang === 'fr') {
                    $subject = 'Activation réussie. Votre groupe est maintenant membre de Hi Flanders !';
                } else {
                    $subject = 'Activering geslaagd. Jouw groep is nu lid van Hi Flanders!';
                }
            }

            if ($memberType === 'individual' && $paymentType === 'online') {
                $templatePath = $baseTemplateUrl . '/verification-ind-payed';
                $htmlBody = Craft::$app->getView()->renderTemplate($templatePath, [
                    'name' => $user->getFieldValue('altFirstName'),
                ]);
    
                if ($lang === 'en') {
                    $subject = 'Payment successful! You are now an official Hi Flanders member';
                } elseif ($lang === 'fr') {
                    $subject = 'Paiement réussi ! Vous êtes officiellement membre de Hi Flanders';
                } else {
                    $subject = 'Betaling geslaagd! Je bent nu officieel lid van Hi Flanders';
                }
            }

            // Prepare and send the email
            $message = $mailer->compose()
                ->setTo($user->email)
                ->setSubject($subject)
                ->setHtmlBody($htmlBody);

            if (!$message->send()) {
                Craft::error('Failed to send payment confirmation email to: ' . $user->email, __METHOD__);
            } else {
                Craft::info('Payment confirmation email sent to: ' . $user->email, __METHOD__);
            }
        } catch (\Throwable $e) {
            Craft::error("Error sending payment confirmation email: " . $e->getMessage(), __METHOD__);
        }
    }

    private function sendPrintDetailsOwner(User $user)
    {
        try {
            if ($user->getFieldValue('requestPrintSend')) {
                Craft::info("Skipping duplicate print request email for user: {$user->email}", __METHOD__);
                return;
            }

            $mailer = Craft::$app->mailer;
            Craft::$app->getView()->setTemplatesPath(Craft::getAlias('@root/templates'));

            $htmlBody = Craft::$app->getView()->renderTemplate('email/request/nl/request-print', [
                'id' => $user->getFieldValue('customMemberId'),
                'street' => $user->getFieldValue('street'),
                'number' => $user->getFieldValue('streetNr'),
                'postalcode' => $user->getFieldValue('postalCode'),
                'city' => $user->getFieldValue('city'),
                'country' => $user->getFieldValue('country'), 
                'memberType' => $user->getFieldValue('memberType')->label
            ]);

            $subject = 'Nieuwe print aanvraag.';

            // Prepare and send the email
            $message = $mailer->compose()
                ->setTo('premium@hiflanders.be')
                ->setSubject($subject)
                ->setHtmlBody($htmlBody);

            if (!$message->send()) {
                Craft::error('Failed to send payment confirmation email to: ' . $user->email, __METHOD__);
            } else {
                Craft::info('Payment confirmation email sent to: ' . $user->email, __METHOD__);

                $user->setFieldValue('requestPrintSend', true);
                Craft::$app->elements->saveElement($user, false);
            }
        } catch (\Throwable $e) {
            Craft::error("Error sending payment confirmation email: " . $e->getMessage(), __METHOD__);
        }
    }
}
