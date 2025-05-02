<?php 

namespace modules\dailychecks\jobs;

use Craft;
use craft\queue\BaseJob;
use craft\elements\User;
use DateTime;
use DateTimeZone;

class DailyDeactivationCheck extends BaseJob 
{
    public function execute($queue): void
    {
        $oneYearAgo = (new DateTime('now', new DateTimeZone('CET')))
            ->modify('-1 year')
            ->format('Y-m-d');

        $usersToDeactivate = User::find()
            ->status('pending')
            ->statusChangeDate("< $oneYearAgo")
            ->group(['members', 'membersGroup'])
            ->all();

        foreach ($usersToDeactivate as $user) {
            $this->deactivateUser($user);
        }
    }

    public function deactivateUser(User $user): void
    {
        $currentDate = new DateTime('now', new DateTimeZone('CET'));
        $elementsService = Craft::$app->getElements();

        $user->setFieldValue('customStatus', 'deactivated');
        $user->setFieldValue('statusChangeDate', $currentDate);

        if (!$elementsService->saveElement($user)) {
            Craft::error('Failed to update customStatus to deactivated for user: ' . $user->id, __METHOD__);
            return;
        }

        $usersService = Craft::$app->getUsers();

        if (!$usersService->deactivateUser($user)) {
            Craft::error('Failed to deactivate user: ' . $user->id, __METHOD__);
        }
    }

    protected function defaultDescription(): string
    {
        return 'Deactivating users who have been pending for over a year.';
    }
}
