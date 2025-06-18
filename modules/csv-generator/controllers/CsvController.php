<?php

namespace modules\csvgenerator\controllers;

use Craft;
use craft\web\Controller;
use yii\web\Response;
use craft\elements\User;

class CsvController extends Controller
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

        // Prepare CSV data
        $csvHeaders = [
            'nid',
            'name',
            'street',
            'city',
            'id',
            'birthday',
            'expire',
            'category',
        ];

        $userId = $user->id ?? '';
        $name = $fields['altFirstName'] . ' ' . $fields['altLastName'] ?? '';
        $street = $fields['street'] . $fields['streetNr'] . $fields['bus'];
        $city = $fields['city'] ?? '';
        $memberId = '008-' . $fields['customMemberId'];
        $birthday = $fields['birthday'] ? $fields['birthday']->format('d/m/Y') : '';
        $expire = $fields['memberDueDate'] ? $fields['memberDueDate']->format('d/m/Y') : '';
        $category = $fields['memberType']->value ?? '';

        $csvData = [
            $userId,
            $name,
            $street,
            $city,
            $memberId,
            $birthday,
            $expire,
            $category,
        ];

        // Create CSV string
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $csvHeaders);
        fputcsv($handle, $csvData);
        rewind($handle);
        $csvOutput = stream_get_contents($handle);
        fclose($handle);

        $response = Craft::$app->response;
        $response->format = Response::FORMAT_RAW;
        $response->headers->set('Content-Type', 'text/csv');
        $customMemberId = $fields['customMemberId'] ?? $user->id;
        $response->headers->set('Content-Disposition', 'attachment; filename="member_' . $customMemberId . '.csv"');
        $response->data = $csvOutput;

        return $response;
    }

}
