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
            'nid',
            'name',
            'street',
            'city',
            'id',
            'birthday',
            'expire',
            'category',
        ];

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $csvHeaders);

        foreach ($users as $user) {
            $fields = $user->getFieldValues();

            $userId = $user->id ?? '';
            $name = $fields['altFirstName'] . ' ' . $fields['altLastName'] ?? '';
            $street = $fields['street'] . $fields['streetNr'] . $fields['bus'];
            $city = $fields['city'] ?? '';
            $memberId = '008-' . $fields['customMemberId'];
            $birthday = $fields['birthday'] ? $fields['birthday']->format('d/m/Y') : '';
            $expire = $fields['memberDueDate'] ? $fields['memberDueDate']->format('d/m/Y') : '';
            $category = $fields['memberType']->value ?? '';

            $row = [
                $userId,
                $name,
                $street,
                $city,
                $memberId,
                $birthday,
                $expire,
                $category,
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
