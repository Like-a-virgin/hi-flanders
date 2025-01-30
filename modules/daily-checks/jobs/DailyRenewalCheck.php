<?php 

namespace modules\dailychecks\jobs;

use Craft;
use craft\queue\BaseJob;
use craft\elements\User;
use DateTime;
use DateTimeZone;

class DailyRenewalCheck extends BaseJob
{
    public function execute($queue): void
    {
        $currentDate = new DateTime('now', new DateTimeZone('CET')); // Get today's date
        $today = $currentDate->format('Y-m-d'); // Format as YYYY-MM-DD

        // Query users whose dueDate matches today
        $users = User::find()
            ->group(['members', 'membersGroup'])
            ->memberDueDate($today) // Assumes `dueDate` is a custom date field
            ->all();

        $elementsService = Craft::$app->getElements();

        foreach ($users as $user) {
            $user->setFieldValue('customStatus', 'renew');

            if (!$elementsService->saveElement($user)) {
                Craft::error('Failed to update customStatus to renew for user: ' . $user->id, __METHOD__);
                continue;
            }

            // Deactivate the user
            $usersService = Craft::$app->getUsers();
            if (!$usersService->deactivateUser($user)) {
                Craft::error('Failed to deactivate user: ' . $user->id, __METHOD__);
            }
        }
    }

    protected function defaultDescription(): string
    {
        return 'Processing daily membership renewals.';
    }
}