<?php

namespace modules\oldmembers\jobs;

use Craft;
use craft\queue\BaseJob;
use craft\elements\User;
use DateTime;
use DateTimeZone;

class DeactivateUserJob extends BaseJob
{
    public int $userId;

    public function execute($queue): void
    {
        $user = Craft::$app->users->getUserById($this->userId);
        if (!$user) {
            Craft::error("User with ID {$this->userId} not found.", __METHOD__);
            return;
        }

        $currentDate = new DateTime('now', new DateTimeZone('CET'));
        $elementsService = Craft::$app->getElements();

        $user->setFieldValue('customStatus', 'deactivated');
        $user->setFieldValue('statusChangeDate', $currentDate);

        if (!$elementsService->saveElement($user)) {
            Craft::error("Failed to save customStatus for user ID {$user->id}", __METHOD__);
            return;
        }

        if (!Craft::$app->getUsers()->deactivateUser($user)) {
            Craft::error("Failed to deactivate user ID {$user->id}", __METHOD__);
            return;
        }

        Craft::info("Successfully deactivated user ID {$user->id}", __METHOD__);
    }

    protected function defaultDescription(): string
    {
        return "Deactivating user ID {$this->userId}";
    }
} 
