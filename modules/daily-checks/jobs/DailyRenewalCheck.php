<?php 

namespace modules\dailychecks\jobs;

use Craft;
use craft\queue\BaseJob;
use craft\elements\User;
use DateTime;
use DateTimeZone;
use modules\flexmail\Flexmail;

class DailyRenewalCheck extends BaseJob
{
    public function execute($queue): void
    {
        $flexmail = Flexmail::getInstance();
        $suspendedHere = false;

        if ($flexmail && !$flexmail->isSyncSuspended()) {
            $flexmail->setSyncSuspended(true);
            $suspendedHere = true;
        }

        $currentDate = new DateTime('now', new DateTimeZone('CET')); // Get today's date
        $today = $currentDate->format('Y-m-d'); // Format as YYYY-MM-DD

        // Query users whose dueDate matches today
        $users = User::find()
            ->status('active')
            ->customStatus(['active'])
            ->group(['members', 'membersGroup'])
            ->memberType(['individual', 'group', 'groupYouth'])
            ->memberDueDate($today)
            ->all();

        $elementsService = Craft::$app->getElements();

        try {
            foreach ($users as $user) {
                $user->setFieldValue('customStatus', 'renew');
                $user->setFieldValue('statusChangeDate', $currentDate);

                $user->setFieldValue('requestPrint', null);
                $user->setFieldValue('printStatus', null);
                $user->setFieldValue('requestPrintSend', null);

                if (!$elementsService->saveElement($user)) {
                    Craft::error('Failed to update customStatus to renew for user: ' . $user->id, __METHOD__);
                    continue;
                }
            }
        } finally {
            if ($flexmail && $suspendedHere) {
                $flexmail->setSyncSuspended(false);
            }
        }
    }

    protected function defaultDescription(): string
    {
        return 'Processing daily membership renewals.';
    }
}
