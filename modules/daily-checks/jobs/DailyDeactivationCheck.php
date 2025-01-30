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
        $currentDate = new DateTime('now', new DateTimeZone('CET'));
        $fourteenDaysAgo = $currentDate->modify('-14 days')->format('Y-m-d');

        $fourteenDaysAgoStart = (new \DateTime('-14 days'))->format('Y-m-d 00:00:00');
        $fourteenDaysAgoEnd = (new \DateTime('-13 days'))->format('Y-m-d 00:00:00');

        $usersNew = User::find()
            ->status('pending')
            ->customStatus('new') 
            ->dateCreated(['and', ">= $fourteenDaysAgoStart", "< $fourteenDaysAgoEnd"]) 
            ->group(['members', 'membersGroup'])
            ->all();
        
        $usersRenew = User::find()
            ->status('pending')
            ->customStatus('renew')
            ->statusChangeDate($fourteenDaysAgo) 
            ->group(['members', 'membersGroup'])
            ->all();

        $usersNotPayed = User::find()
            ->status('active')
            ->customStatus('active')
            ->paymentType(null)
            ->statusChangeDate($fourteenDaysAgo) 
            ->group(['members', 'membersGroup'])
            ->all();

        foreach ($usersNew as $user) {
            $this->deactivateUser($user, 'new');
        }

        foreach ($usersRenew as $user) {
            $this->deactivateUser($user, 'renew');
        }
 
        foreach ($usersNotPayed as $user) {
            $this->deactivateUser($user, 'notPayed');
        }
    }

    public function deactivateUser(User $user)
    {
        $currentDate = new DateTime('now', new DateTimeZone('CET'));
        $elementsService = Craft::$app->getElements();

        $user->setFieldValue('customStatus', 'deactivated');
        $user->setFieldValue('statusChangeDate', $currentDate);

        if (!$elementsService->saveElement($user)) {
            Craft::error('Failed to update customStatus to renew for user: ' . $user->id, __METHOD__);
            return;
        }
        
        $usersService = Craft::$app->getUsers();

        if (!$usersService->deactivateUser($user)) {
            Craft::error('Failed to deactivate user: ' . $user->id, __METHOD__);
        }
    }

    protected function defaultDescription(): string
    {
        return 'Processing deactivation users.';
    }
}