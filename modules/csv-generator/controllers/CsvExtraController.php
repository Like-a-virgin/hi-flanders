<?php

namespace modules\csvgenerator\controllers;

use Craft;
use craft\web\Controller;
use yii\web\Response;
use craft\elements\Entry; 

class CsvExtraController extends Controller
{
    protected int|bool|array $allowAnonymous = false;

    public function actionGenerate(): Response
    {
        $request = Craft::$app->getRequest();
        $kidsId = $request->getQueryParam('memberId');

        if (!$kidsId) {
            throw new \yii\web\BadRequestHttpException('Missing memberId in query params');
        }

        $kid = Entry::find()->section('extraMembers')->id($kidsId)->one();

        if (!$kid) {
            throw new \yii\web\NotFoundHttpException('User not found');
        }

        $fields = $kid->getFieldValues();

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

        $kidsId = $kid->id ?? '';
        $street = '';
        $city = '';
        $name = $fields['altFirstName'] . ' ' . $fields['altLastName'] ?? '';
        $memberId = '008' . $kidsId;
        $birthday = $fields['birthday'] ? $fields['birthday']->format('d/m/Y') : '';
        $expire = $fields['birthday'] ? (clone $fields['birthday'])->modify('+18 years')->format('d/m/Y') : '';
        $category = 'Kid';

        $csvData = [
            $kidsId,
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
        $customMemberId = $fields['customMemberId'] ?? $kid->id;
        $response->headers->set('Content-Disposition', 'attachment; filename="kid_' . $customMemberId . '.csv"');
        $response->data = $csvOutput;

        return $response;
    }

}
