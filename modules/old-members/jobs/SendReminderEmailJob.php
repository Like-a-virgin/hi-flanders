<?php

namespace modules\oldmembers\jobs;

use Craft;
use craft\queue\BaseJob;
use craft\elements\User;

class SendReminderEmailJob extends BaseJob
{
    public int $userId;

    public function execute($queue): void
    {
        $user = Craft::$app->users->getUserById($this->userId);
        if (!$user) {
            Craft::error("User with ID {$this->userId} not found.", __METHOD__);
            return;
        }

        try {
            $mailer = Craft::$app->mailer;
            Craft::$app->getView()->setTemplatesPath(Craft::getAlias('@root/templates'));

            $activationUrl = Craft::$app->users->getActivationUrl($user);
            $userCustomStatus = $user->getFieldValue('customStatus')->value;
            $memberType = $user->getFieldValue('memberType')->value;

            if ($memberType === 'group' || $memberType === 'groupYouth') {
                $userName = $user->getFieldValue('organisation');
            } elseif ($memberType === 'individual') {
                $userName = $user->getFieldValue('altFirstName');
            } else {
                $userName = $user->friendlyName;
            }

            if ($userCustomStatus === 'old') {
                $htmlBody = Craft::$app->getView()->renderTemplate('email/remind/nl/remind-old-active', [
                    'name' => $userName,
                    'activationUrl' => $activationUrl
                ]);
            } elseif ($userCustomStatus === 'oldRenew') {
                $htmlBody = Craft::$app->getView()->renderTemplate('email/remind/nl/remind-old-deactive', [
                    'name' => $userName,
                    'activationUrl' => $activationUrl
                ]);
            } else {
                Craft::warning("Skipping user {$user->id} — unsupported status: $userCustomStatus");
                return;
            }

            $subject = 'Vlaamse Jeugdherbergen werd Hi Flanders, en neemt jou mee op (digitale) weg!';

            $message = $mailer->compose()
                ->setTo($user->email)
                ->setSubject($subject)
                ->setHtmlBody($htmlBody)
                ->send();

            if (!$message) {
                Craft::error("Failed to send reminder email to {$user->email}", __METHOD__);
            } else {
                Craft::info("Reminder email sent to {$user->email}", __METHOD__);
            }
        } catch (\Throwable $e) {
            Craft::error("Error sending reminder email: " . $e->getMessage(), __METHOD__);
        }
    }

    protected function defaultDescription(): string
    {
        return "Sending reminder email to user ID {$this->userId}";
    }
}
