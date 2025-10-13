<?php

namespace modules\dailychecks\jobs;

use Craft;
use craft\queue\BaseJob;
use craft\elements\User;
use modules\flexmail\Flexmail;

class DailyResaveUsers extends BaseJob
{
    public function execute($queue): void
    {
        $flexmail = Flexmail::getInstance();
        $suspendedHere = false;

        if ($flexmail && !$flexmail->isSyncSuspended()) {
            $flexmail->setSyncSuspended(true);
            $suspendedHere = true;
        }

        $this->setProgress($queue, 0, 'Fetching users...');

        $users = User::find()
            ->customStatus(['new', 'renew', 'deactive'])
            ->group(['members', 'membersGroup'])
            ->status(null)
            ->all();

        $total = count($users);

        try {
            foreach ($users as $i => $user) {
                if (!Craft::$app->elements->saveElement($user)) {
                    Craft::error("Failed to resave user ID {$user->id}", __METHOD__);
                } else {
                    Craft::info("Resaved user ID {$user->id} with customStatus '{$user->getFieldValue('customStatus')->value}'", __METHOD__);
                }

                $this->setProgress($queue, $i / max(1, $total), "Processing user {$user->id}");
            }
        } finally {
            if ($flexmail && $suspendedHere) {
                $flexmail->setSyncSuspended(false);
            }
        }

        $this->setProgress($queue, 1, 'Completed resaving users.');
    }

    protected function defaultDescription(): string
    {
        return 'Daily resave of users with customStatus new, renew, or deactive';
    }
}
