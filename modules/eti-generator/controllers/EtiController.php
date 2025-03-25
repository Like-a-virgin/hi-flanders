<?php

namespace modules\etigenirator\controllers;

use Craft;
use craft\web\Controller;
use yii\web\Response;
use craft\elements\User;

class EtiController extends Controller
{
    protected int|bool|array $allowAnonymous = false;

    public function actionGenerate(): Response
    {
        $request = Craft::$app->getRequest();
        $userId = $request->getQueryParam('memberId');

        if (!$userId) {
            throw new \yii\web\BadRequestHttpException('Missing memberId in query params');
        }

        $user = Craft::$app->users->getUserById((int)$userId);

        if (!$user) {
            throw new \yii\web\NotFoundHttpException('User not found');
        }

        $fields = $user->getFieldValues();

        $etiContent = <<<ETI
        [Member]
        lastName={$fields['altFirstName']}
        firstName={$fields['altLastName']}
        Street={$fields['street']} {$fields['streetNr']} 
        Bus {$fields['bus']}
        City={$fields['postalCode']} {$fields['city']}
        Country={$fields['country']}
        ETI;

        $response = Craft::$app->response;
        $response->format = Response::FORMAT_RAW;
        $response->headers->set('Content-Type', 'text/plain');
        $response->headers->set('Content-Disposition', 'attachment; filename="member_' . $user->id . '.eti"');
        $response->data = $etiContent;

        return $response;
    }

}
