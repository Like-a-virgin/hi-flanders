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
    protected array|bool|int $allowAnonymous = ['login', 'deactivate', 'me', 'update-address'];
    public $enableCsrfValidation = false;

    public function beforeAction($action): bool
    {
        $request = Craft::$app->getRequest();
        $response = Craft::$app->getResponse();
        $origin = $request->getOrigin();
        $allowedOrigins = ['http://localhost:4200', 'https://app.hiflanders.be'];

        if (in_array($origin, $allowedOrigins, true)) {
            $headers = $response->getHeaders();
            $headers->set('Access-Control-Allow-Origin', $origin);
            $headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
            $headers->set('Access-Control-Allow-Headers', 'Authorization, Content-Type');
            $headers->set('Access-Control-Allow-Credentials', 'true');
        }

        if ($request->getMethod() === 'OPTIONS') {
            Craft::$app->end(); // Ends preflight request early
        }

        return parent::beforeAction($action);
    }

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

        // Check user status before
        Craft::info('Before deactivation: ' . json_encode([
            'id' => $user->id,
            'email' => $user->email,
            'active' => $user->active,
            'pending' => $user->pending,
            'suspended' => $user->suspended,
            'archived' => $user->archived,
        ]), __METHOD__);

        try {
            Craft::$app->getUsers()->deactivateUser($user);

            // Re-fetch user from DB to confirm new state
            $updatedUser = User::find()->id($user->id)->one();

            Craft::info('After deactivation: ' . json_encode([
                'id' => $updatedUser->id,
                'active' => $updatedUser->active,
                'pending' => $updatedUser->pending,
            ]), __METHOD__);

            return $this->asJson([
                'success' => true,
                'message' => 'Deactivation attempted. Check logs for actual effect.',
                'status' => [
                    'active' => $updatedUser->active,
                    'pending' => $updatedUser->pending,
                ],
            ]);

        } catch (\Throwable $e) {
            Craft::error('Deactivation exception: ' . $e->getMessage(), __METHOD__);
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

        $extraMembers = \craft\elements\Entry::find()
            ->section('extraMembers')
            ->relatedTo(['targetElement' => $user, 'field' => 'parentMember'])
            ->all();

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

            'extraMembers' => array_map(function($entry) {
                return [
                    'id' => $entry->id,
                    'firstName' => $entry->getFieldValue('altFirstName'),
                    'lastName' => $entry->getFieldValue('altLastName'),
                    'birthday' => $entry->getFieldValue('birthday'),
                    'memberRate' => $entry->getFieldValue('memberRate')->one()?->title,
                ];
            }, $extraMembers),
        ]);
    }

    public function actionUpdateAddress(): Response
    {
        $this->requirePostRequest();
        $user = $this->requireJwtAuth();

        $request = Craft::$app->getRequest();

        // Update only specific fields
        $user->setFieldValue('street', $request->getBodyParam('street'));
        $user->setFieldValue('streetNr', $request->getBodyParam('streetNr'));
        $user->setFieldValue('bus', $request->getBodyParam('bus'));
        $user->setFieldValue('city', $request->getBodyParam('city'));
        $user->setFieldValue('postalCode', $request->getBodyParam('postalCode'));

        if (!Craft::$app->elements->saveElement($user)) {
            return $this->asJson([
                'success' => false,
                'errors' => $user->getErrors(),
            ]);
        }

        return $this->asJson([
            'success' => true,
            'message' => 'Address updated successfully.',
            'address' => [
                'street' => $user->getFieldValue('street'),
                'streetNr' => $user->getFieldValue('streetNr'),
                'bus' => $user->getFieldValue('bus'),
                'city' => $user->getFieldValue('city'),
                'postalCode' => $user->getFieldValue('postalCode'),
            ],
        ]);
    }
}