<?php
namespace modules\accountapi\controllers;

use Craft;
use craft\elements\User;
use craft\web\Controller;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use yii\web\ForbiddenHttpException;
use yii\web\Response;
use modules\accountapi\AccountApi;

class UserController extends Controller
{
    protected array|bool|int $allowAnonymous = ['login', 'deactivate', 'me', 'membership'];
    public $enableCsrfValidation = false;

    // 🔐 Helper to extract and validate JWT
    private function requireJwtAuth(): User
    {
        $authHeader = Craft::$app->getRequest()->getHeaders()->get('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            throw new ForbiddenHttpException('Missing or invalid Authorization header');
        }

        $token = substr($authHeader, 7);
        $secret = AccountApi::$jwtSecret;

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

            return $user;
        } catch (\Exception $e) {
            throw new ForbiddenHttpException('Invalid or expired token: ' . $e->getMessage());
        }
    }

    // 🔐 POST /actions/accountapi/user/deactivate
    public function actionDeactivate(): Response
    {
        $this->requirePostRequest();
        $user = $this->requireJwtAuth();

        try {
            if (!Craft::$app->getUsers()->deactivateUser($user)) {
                return $this->asJson([
                    'success' => false,
                    'message' => 'Failed to deactivate user — method returned false.' . $user,
                ]);
            }
        
            return $this->asJson([
                'success' => true,
                'message' => 'Account deactivated',
            ]);
        
        } catch (\Throwable $e) {
            Craft::error('Deactivation error: ' . $e->getMessage(), __METHOD__);
            return $this->asJson([
                'success' => false,
                'message' => 'Exception: ' . $e->getMessage(),
            ]);
        }
    }

    // 🔐 GET /actions/accountapi/user/me
    public function actionMe(): Response
    {
        $user = $this->requireJwtAuth();

        return $this->asJson([
            'id' => $user->id,
            'email' => $user->email,
            'fullName' => $user->fullName,
            'firstName' => $user->getFieldValue('altFirstName'),
            'lastName' => $user->getFieldValue('altLastName'),
            'birthday' => $user->getFieldValue('birthday'),
            'street' => $user->getFieldValue('street'),
            'streetNr' => $user->getFieldValue('streetNr'),
            'bus' => $user->getFieldValue('bus'),
            'city' => $user->getFieldValue('city'),
            'postalCode' => $user->getFieldValue('postalCode'),
            'customMemberId' => $user->getFieldValue('customMemberId'),
            'memberDueDate' => $user->getFieldValue('memberDueDate'),
            'paymentDate' => $user->getFieldValue('paymentDate'),
            'memberRate' => $user->getFieldValue('memberRate')->one()?->title,
        ]);
    }
}