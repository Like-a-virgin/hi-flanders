<?php

namespace modules\oldmembers\console\controllers;

use Craft;
use craft\console\Controller;
use craft\elements\User;
use yii\console\ExitCode;
use modules\oldmembers\jobs\SendReminderEmailJob;

class SendReminderController extends Controller
{
    public function actionQueueReminders(): int
    {
        $this->stdout("Queuing reminder emails...\n");

        $users = User::find()
            ->group(['members', 'membersGroup'])
            ->customStatus(['old', 'oldRenew'])
            ->all();

        if (empty($users)) {
            $this->stdout("No users found.\n");
            return ExitCode::OK;
        }

        foreach ($users as $user) {
            Craft::$app->queue->push(new SendReminderEmailJob([
                'userId' => $user->id,
            ]));
            $this->stdout("Queued reminder for user ID: {$user->id}\n");
        }

        $this->stdout("Queued " . count($users) . " reminder emails.\n");

        return ExitCode::OK;
    }
}
