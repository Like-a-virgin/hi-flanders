<?php

namespace modules\oldmembers\controllers;

use Craft;
use craft\web\Controller;
use craft\elements\User;
use yii\web\Response;
use DateTime;
use DateTimeZone;
use yii\web\ForbiddenHttpException;

class DeactivateController extends Controller
{
    protected array|int|bool $allowAnonymous = false;
    public $enableCsrfValidation = false;

    public function actionDeactivate(): Response
    {
        // $currentUser = Craft::$app->getUser()->getIdentity();
        // if (!$currentUser) {
        //     throw new ForbiddenHttpException('You must be logged in to access this page.');
        // }

        // // ✅ Check if the user is an admin OR in the `membersAdmin` group
        // if (!$currentUser->admin && !$currentUser->isInGroup('membersAdmin')) {
        //     throw new ForbiddenHttpException('You do not have permission to perform this action.');
        // }

        $users = User::find()
            ->group(['members', 'membersGroup']) // Adjust if needed
            ->customStatus(['old', 'oldRenew']) // Filtering users by memberType
            ->all();

        if (empty($users)) {
            Craft::$app->getSession()->setError('No users found with memberType old.');
            return $this->redirect(Craft::$app->request->referrer);
        }

        $sentCount = 0;
        foreach ($users as $user) {
            if ($this->deactivate($user)) {
                $sentCount++;
            }
        }

        Craft::$app->getSession()->setNotice("Deactivated $sentCount users.");
        return $this->redirect(Craft::$app->request->referrer);
    }

    private function deactivate(User $user): void
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
}
