<?php

namespace modules\csvgenerator\controllers;

use Craft;
use craft\elements\User;
use craft\web\Controller;
use yii\web\Response;
use yii\web\ForbiddenHttpException;

class BulkCsvController extends Controller
{
    protected array|int|bool $allowAnonymous = ['generate-all'];

    public function actionGenerateAll(): Response
    {
        $currentUser = Craft::$app->getUser()->getIdentity();
        if (!$currentUser) {
            throw new ForbiddenHttpException('You must be logged in to access this resource.');
        }

        $isMemberAdmin = $currentUser->admin;
        foreach ($currentUser->getGroups() as $group) {
            if (in_array($group->handle, ['membersAdmin', 'membersAdminSuper'])) {
                $isMemberAdmin = true;
                break;
            }
        }

        if (!$isMemberAdmin) {
            throw new ForbiddenHttpException('You do not have permission to access this resource.');
        }

        $queryParams = Craft::$app->getRequest()->getQueryParams();
        unset($queryParams['page']);

        $usersQuery = User::find();

        if (!empty($queryParams['search'])) {
            $usersQuery->search($queryParams['search']);
        }
        if (!empty($queryParams['status'])) {
            $usersQuery->customStatus($queryParams['status']);
        }
        if (!empty($queryParams['payMethod'])) {
            $usersQuery->paymentType($queryParams['payMethod']);
        }
        if (!empty($queryParams['cardType'])) {
            $usersQuery->cardType($queryParams['cardType']);
        }
        if (!empty($queryParams['ageGroup'])) {
            $usersQuery->memberRate($queryParams['ageGroup']);
        }
        if (!empty($queryParams['memberType'])) {
            $usersQuery->memberType($queryParams['memberType']);
        }
        if (!empty($queryParams['printStatus'])) {
            $usersQuery->printStatus($queryParams['printStatus']);
        }
        if (!empty($queryParams['regMin']) && !empty($queryParams['regMax'])) {
            $usersQuery->dateCreated([
                'and',
                ">={$queryParams['regMin']}",
                "<={$queryParams['regMax']}"
            ]);
        }
        if (!empty($queryParams['payMin']) && !empty($queryParams['payMax'])) {
            $usersQuery->paymentDate([
                'and',
                ">={$queryParams['payMin']}",
                "<={$queryParams['payMax']}"
            ]);
        }

        $usersQuery->group(['members', 'membersGroup']);
        $users = $usersQuery->all();

        // Create CSV
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

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $csvHeaders);

        foreach ($users as $user) {
            $fields = $user->getFieldValues();

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

            $row = [
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

            fputcsv($handle, $row);
        }

        rewind($handle);
        $csvOutput = stream_get_contents($handle);
        fclose($handle);

        $fileName = 'members_bulk_' . date('d-m-Y_H-i-s') . '.csv';

        $response = Craft::$app->response;
        $response->format = Response::FORMAT_RAW;
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $fileName . '"');
        $response->data = $csvOutput;

        return $response;
    }

}
