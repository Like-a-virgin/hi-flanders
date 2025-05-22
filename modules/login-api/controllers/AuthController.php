<?php

namespace modules\loginapi\controllers;

use Craft;
use craft\elements\User;
use craft\web\Controller;
use yii\web\Response;
use yii\web\UnauthorizedHttpException;
use Firebase\JWT\JWT;
use modules\loginapi\LoginApi;

class AuthController extends Controller
{
    public $enableCsrfValidation = false;

    protected array|bool|int $allowAnonymous = ['login'];

    public function actionLogin(): Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $email = $request->getRequiredBodyParam('email');
        $password = $request->getRequiredBodyParam('password');

        $user = Craft::$app->getUsers()->getUserByUsernameOrEmail($email);

        if (!$user || !Craft::$app->getSecurity()->validatePassword($password, $user->password)) {
            throw new UnauthorizedHttpException('Invalid email or password');
        }

        $payload = [
            'sub' => $user->id,
            'email' => $user->email,
            'iat' => time(),
            'exp' => time() + 3600, // Token valid for 1 hour
        ];

        $token = \Firebase\JWT\JWT::encode($payload, \modules\loginapi\LoginApi::$jwtSecret, 'HS256');

        return $this->asJson([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
            ]
        ]);
    }
}
