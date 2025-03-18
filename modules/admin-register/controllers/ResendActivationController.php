<?php

namespace modules\adminregister\controllers;

use Craft;
use craft\elements\User;
use craft\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class ResendActivationController extends Controller
{
    protected array|bool|int $allowAnonymous = false; 

    public function actionSendActivationEmail(): Response
    {
        $this->requirePostRequest();

        $userId = Craft::$app->request->getParam('id');
        $user = Craft::$app->users->getUserById($userId);

        if (!$user) {
            Craft::$app->session->setError('User not found.');
            return $this->redirect(Craft::$app->request->referrer);
        }

        if (Craft::$app->users->sendNewEmailVerifyEmail($user)) {
            Craft::$app->session->setNotice('Activation email sent successfully.');
        } else {
            Craft::$app->session->setError('Failed to send activation email.');
        }

        return $this->redirect(Craft::$app->request->referrer);
    }
}
