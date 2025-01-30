<?php

namespace modules\oldmembers\controllers;

use Craft;
use craft\web\Controller;
use craft\elements\User;
use yii\web\Response;

class SendReminderController extends Controller
{
    protected array|int|bool $allowAnonymous = false;
    public $enableCsrfValidation = false;

    public function actionSendEmail(): Response
    {
        $users = User::find()
            ->group(['members', 'membersGroup']) // Adjust if needed
            ->customStatus(['old', 'oldRenew']) // Filtering users by memberType
            ->all();

        if (empty($users)) {
            return $this->asJson(['success' => false, 'message' => 'No users found with memberType old']);
        }

        $sentCount = 0;
        foreach ($users as $user) {
            if ($this->sendEmail($user)) {
                $sentCount++;
            }
        }

        Craft::$app->getSession()->setNotice("Emails sent to $sentCount users.");
        return $this->redirect(Craft::$app->request->referrer);
    }

    private function sendEmail(User $user): bool
    {
        try {
            $mailer = Craft::$app->mailer;
            Craft::$app->getView()->setTemplatesPath(Craft::getAlias('@root/templates'));

            $activationUrl = Craft::$app->users->getActivationUrl($user);

            $userCustomStatus = $user->getFieldValue('customStatus')->value;
            $memberType = $user->getFieldValue('memberType')->value;

            if ($memberType === 'group' || $memberType === 'groupYouth' ) {
                $userName = $user->getFieldValue('organisation');
            }    
           
            if ($memberType === 'individual') {
                $userName = $user->getFieldValue('altFirstName');
            }    

            if ($userCustomStatus === 'old') {      
                $htmlBody = Craft::$app->getView()->renderTemplate('email/remind/remind-old-active', [
                    'name' => $userName,
                    'activationUrl' => $activationUrl
                ]);

                $subject = 'Jeugdherbergen werd Hi Flanders, en neemt jou mee op (digitale) weg!';
            }

            if ($userCustomStatus === 'oldRenew') {
                $htmlBody = Craft::$app->getView()->renderTemplate('email/remind/remind-old-deactive', [
                    'name' => $userName,
                    'activationUrl' => $activationUrl
                ]);
            
                $subject = 'Jeugdherbergen werd Hi Flanders, en neemt jou mee op (digitale) weg!';
            }

            $message = $mailer->compose()
                ->setTo($user->email)
                ->setSubject($subject)
                ->setHtmlBody($htmlBody)
                ->send();

            if (!$message) {
                Craft::error('Failed to send old member email to user: ' . $user->email, __METHOD__);
                return false;
            } else {
                Craft::info('Old member email sent to user: ' . $user->email, __METHOD__);
                return true;
            }
        } catch (\Throwable $e) {
            Craft::error("Error sending old member email: " . $e->getMessage(), __METHOD__);
            return false;
        }
    }
}
