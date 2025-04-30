<?php

namespace modules\oldmembers\console\controllers;

use Craft;
use craft\console\Controller;
use craft\elements\User;
use yii\console\ExitCode;
use modules\oldmembers\jobs\SendActivationEmailJob;

class SendActivationController extends Controller
{
    public function actionQueueEmails(): int
    {
        $this->stdout("Finding users...\n");

        $users = User::find()
            ->group(['members', 'membersGroup'])
            ->customStatus(['old', 'oldRenew'])
            ->all();

        if (empty($users)) {
            $this->stdout("No users found.\n");
            return ExitCode::OK;
        }

        foreach ($users as $user) {
            Craft::$app->queue->push(new SendActivationEmailJob([
                'userId' => $user->id,
            ]));
            $this->stdout("Queued email for user ID: {$user->id}\n");
        }

        $this->stdout("Queued " . count($users) . " emails successfully.\n");

        return ExitCode::OK;
    }
}
