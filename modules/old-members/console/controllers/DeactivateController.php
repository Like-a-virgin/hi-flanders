<?php

namespace modules\oldmembers\console\controllers;

use Craft;
use craft\console\Controller;
use craft\elements\User;
use yii\console\ExitCode;
use modules\oldmembers\jobs\DeactivateUserJob;

class DeactivateController extends Controller
{
    public function actionQueueDeactivations(): int
    {
        $this->stdout("Fetching users to deactivate...\n");

        $users = User::find()
            ->group(['members', 'membersGroup'])
            ->customStatus(['old', 'oldRenew'])
            ->all();

        if (empty($users)) {
            $this->stdout("No users found.\n");
            return ExitCode::OK;
        }

        foreach ($users as $user) {
            Craft::$app->queue->push(new DeactivateUserJob([
                'userId' => $user->id,
            ]));
            $this->stdout("Queued deactivation for user ID: {$user->id}\n");
        }

        $this->stdout("Queued deactivation jobs for " . count($users) . " users.\n");

        return ExitCode::OK;
    }
} 
