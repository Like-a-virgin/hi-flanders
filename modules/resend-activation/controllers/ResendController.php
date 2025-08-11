<?php
namespace modules\resendactivation\controllers;

use Craft;
use craft\web\Controller;
use craft\elements\User as UserElement;
use yii\web\Response;

class ResendController extends Controller
{
    // Allow guests to use this endpoint
    protected array|int|bool $allowAnonymous = ['send'];

    public function actionSend(): ?Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $users   = Craft::$app->getUsers();

        $loginName = (string)$request->getRequiredBodyParam('loginName');

        $user = $users->getUserByUsernameOrEmail($loginName);

        if ($user) {
            // If the user is pending activation, resend activation
            if ($user->status === UserElement::STATUS_PENDING) {
                // Option A: call the service method directly
                $users->sendActivationEmail($user);
            } else {
                // Otherwise, fall back to password reset
                $users->sendPasswordResetEmail($user);
            }
        }

        // Honor redirectInput or return JSON
        if ($request->getAcceptsJson()) {
            return $this->asSuccess('OK');
        }

        return $this->redirectToPostedUrl();
    }
}
