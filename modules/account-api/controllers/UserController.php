<?php
namespace modules\accountapi\controllers;

use Craft;
use craft\elements\User;
use craft\web\Controller;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

class UserController extends Controller
{
    protected array|bool|int $allowAnonymous = ['deactivate'];

    public $enableCsrfValidation = false;

    public function actionDeactivate(): Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $authHeader = $request->getHeaders()->get('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            throw new ForbiddenHttpException('Missing or invalid Authorization header');
        }

        $token = substr($authHeader, 7);
        $secret = \modules\loginapi\LoginApi::$jwtSecret;

        try {
            $decoded = JWT::decode($token, new Key($secret, 'HS256'));
            $userId = $decoded->sub ?? null;

            if (!$userId) {
                throw new ForbiddenHttpException('Invalid token payload');
            }

            $user = User::find()->id($userId)->one();

            if (!$user) {
                throw new ForbiddenHttpException('User not found');
            }

            $user->suspended = true;

            if (!Craft::$app->elements->saveElement($user)) {
                return $this->asJson([
                    'success' => false,
                    'errors' => $user->getErrors(),
                ]);
            }

            return $this->asJson(['success' => true, 'message' => 'Account deactivated']);

        } catch (\Exception $e) {
            throw new ForbiddenHttpException('Invalid or expired token');
        }
    }
}
