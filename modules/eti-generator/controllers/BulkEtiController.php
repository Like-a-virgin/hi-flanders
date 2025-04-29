<?php

namespace modules\etigenirator\controllers;

use Craft;
use craft\elements\User;
use craft\web\Controller;
use yii\web\Response;
use yii\web\ForbiddenHttpException;

class BulkEtiController extends Controller
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
            $usersQuery->dateCreated(
                [
                    'and',
                    ">={$queryParams['regMin']}",
                    "<={$queryParams['regMax']}"
                ]
            );
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

        $allContent = $etiHeader;

        foreach ($users as $user) {
            $fields = $user->getFieldValues();

            $memberNumber = $fields['memberNumber'] ?? '';
            $validityDate = $fields['validityDate'] ?? '';
            $customMemberId = $fields['customMemberId'] ?? 'unknown';
            $fullName = $user->fullName ?? '';
            $street = trim(($fields['street'] ?? '') . ' ' . ($fields['streetNr'] ?? ''));
            $zipCity = trim(($fields['postalCode'] ?? '') . ' ' . ($fields['city'] ?? ''));

            $etiBlock = <<<ETI

            US
            FR"vjh"
            ?
            {$memberNumber}|{$validityDate}

            {$fullName}
            {$street}
            {$zipCity}
            P1,1
            ETI;

            $allContent .= $etiBlock;
        }

        $fileName = 'members_bulk_' . date('Y-m-d_H-i-s') . '.eti';

        $response = Craft::$app->response;
        $response->format = Response::FORMAT_RAW;
        $response->headers->set('Content-Type', 'text/plain');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $fileName . '"');
        $response->data = $allContent;

        return $response;
    }
}
