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

        // Static label header (based on uploaded .eti sample)
        $etiHeader = <<<ETI
        US
        FK"vjh"
        FS"vjh"
        V00,14,N,"lidnummer"
        V01,30,N,"vereniging"
        V02,30,N,"naam"
        V03,46,N,"STRAAT"
        V04,30,N,"GEMEENTE"
        q711
        Q203,19+0
        S2
        D8
        ZT
        A400,10,0,2,1,1,N,V00
        A18,40,0,3,1,1,N,V01
        A18,70,0,3,1,1,N,V02
        A18,110,0,3,1,1,N,V03
        A18,150,0,3,1,1,N,V04
        FE
        ETI;

        // Dynamic content block
        $memberNumber = $fields['memberNumber'] ?? '';
        $validityDate = $fields['validityDate'] ?? '';
        $fullName = $user->fullName ?? '';
        $street = trim(($fields['street'] ?? '') . ' ' . ($fields['streetNr'] ?? ''));
        $zipCity = trim(($fields['postalCode'] ?? '') . ' ' . ($fields['city'] ?? ''));

        $etiContent = <<<ETI

        US
        FR"vjh"
        ?
        {$memberNumber}|{$validityDate}

        {$fullName}
        {$street}
        {$zipCity}
        P1,1
        ETI;

        $output = $etiHeader . $etiContent;

        $response = Craft::$app->response;
        $response->format = Response::FORMAT_RAW;
        $response->headers->set('Content-Type', 'text/plain');
        $customMemberId = $fields['customMemberId'] ?? $user->id;
        $response->headers->set('Content-Disposition', 'attachment; filename="member_' . $customMemberId . '.eti"');        $response->data = $output;

        return $response;
    }

    public function actionGenerateCsv(): Response
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
            'Naam',
            'Straat',
            'Gemeente',
            'E-mail',
            'Gebruikersnaam',
        ];

        $memberNumber = $fields['customMemberId'] ?? '';
        $validityDate = $fields['memberDueDate'] ? $fields['memberDueDate']->format('d/m/Y') : '';
        $fullName = $user->fullName ?? '';
        $street = trim(($fields['street'] ?? '') . ' ' . ($fields['streetNr'] ?? ''));
        $zipCity = trim(($fields['postalCode'] ?? '') . ' ' . ($fields['city'] ?? ''));
        $email = $user->email ?? '';
        $username = $user->username ?? '';

        $csvData = [
            $memberNumber,
            $validityDate,
            $fullName,
            $street,
            $zipCity,
            $email,
            $username,
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
