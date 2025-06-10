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
            'Lidnummer',
            'Geldig tot',
            'Naam / organisatie',
            'Familienaam / contactpersoon',
            'Straat',
            'StraatNr',
            'Bus',
            'Gemeente',
            'Postcode',
            'E-mail',
        ];

            $memberNumber = $fields['customMemberId'] ?? '';
            $validityDate = $fields['memberDueDate'] ? $fields['memberDueDate']->format('d/m/Y') : '';
            $firstName = $fields['altFirstName'] ? $fields['altFirstName'] : $fields['organisation'];
            $lastName = $fields['altLastName'] ? $fields['altLastName']  : $fields['contactPerson'];
            $street = $fields['street'] ?? '';
            $streetNumber = $fields['streetNr'] ?? '';
            $bus = $fields['bus'] ?? '';
            $city = $fields['city'] ?? '';
            $zipCity = $fields['postalCode'] ?? '';
            $email = $user->email ?? '';

        $csvData = [
            $memberNumber,
            $validityDate,
            $firstName,
            $lastName,
            $street,
            $streetNumber,
            $bus,
            $city,
            $zipCity,
            $email,
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
