<?php

namespace modules\csvgenerator\controllers;

use Craft;
use craft\elements\User;
use craft\elements\Entry;
use craft\web\Controller;
use yii\web\Response;
use yii\web\ForbiddenHttpException;

/**
 * Handles exporting user data to CSV.
 */
class ExpandedCsvController extends Controller
{
    protected array|int|bool $allowAnonymous = ['users'];  // Adjust if anonymous access is not needed

    public function actionUsers(): Response
    {
        $currentUser = Craft::$app->getUser()->getIdentity();
        if (!$currentUser) {
            throw new ForbiddenHttpException('You must be logged in to access this resource.');
        }

        $isMemberAdmin = $currentUser->admin;
        foreach ($currentUser->getGroups() as $group) {
            if ($group->handle === 'membersAdmin' || $group->handle === 'membersAdminSuper') {
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

        // Apply filters if present
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
            $usersQuery->andWhere(['between', 'dateCreated', $queryParams['regMin'], $queryParams['regMax']]);
        }
        if (!empty($queryParams['payMin']) && !empty($queryParams['payMax'])) {
            $usersQuery->andWhere(['between', 'fields.paymentDate', $queryParams['payMin'], $queryParams['payMax']]);
        }

        $users = $usersQuery->group(['members', 'membersGroup'])->all();
        $data = [[
            'ID', 
            'Group/individual',
            'Status', 
            'FirstName/organisation',
            'LastName/contactperson', 
            'Birthday', 
            'Email', 
            'Country', 
            'Street', 
            'Street number', 
            'Bus', 
            'City', 
            'Postalcode ', 
            'Membertype', 
            'Registration Date', 
            'Renewed on', 
            'Due date', 
            'Children', 
            'Total Payment', 
            'Pay date', 
            'Payment type', 
            'Card Type',
            'Print request', 
            'Print payed',
            'Total print'
        ]];

        foreach ($users as $user) {
            $memberRateEntry = $user->getFieldValue('memberRate')->one();
            $memberRateTitle = $memberRateEntry ? $memberRateEntry->title : '';

            $customMemberId = $user->customMemberId; 

            if (!empty($customMemberId)) {
                $customMemberId = '(008) ' . number_format($customMemberId, 0, '', '');
            } else {
                $customMemberId = '(008)'; 
            }

            $birthday = $user->birthday; 
            $formattedBirthday = $birthday ? $birthday->format('d/m/Y') : ''; 
            
            $payDate = $user->paymentDate; 
            $formattedPayDate = $payDate ? $payDate->format('d/m/Y') : '';
            
            $registerDate = $user->dateCreated; 
            $formattedRegisterDate = $registerDate ? $registerDate->format('d/m/Y') : '';
            
            $renewDate = $user->renewedDate; 
            $formattedRenewDate = $renewDate ? $renewDate->format('d/m/Y') : '';
            
            $dueDate = $user->memberDueDate; 
            $formattedDueDate = $dueDate ? $dueDate->format('d/m/Y') : '';

            $prinRequest = $user->requestPrint;
            $formattedPrintRequest = $prinRequest ? $prinRequest->format('d-m-Y') : '';
            
            $prinPayed = $user->payedPrintDate;
            $formattedPrintPayed = $prinPayed ? $prinPayed->format('d-m-Y') : '';

            // Get related entries
            $relatedEntries = Entry::find()
                ->section('extraMembers') // Adjust section handle as needed
                ->relatedTo($user)
                ->all();

            // Calculate related entries count and total payment
            $relatedEntriesCount = count($relatedEntries);

            $groupHandle = null;

            // Get the first group handle if the user belongs to the 'members' or 'membersGroup' group
            foreach ($user->getGroups() as $group) {
                if ($group->handle === 'members') {
                    $groupHandle = 'individual';
                    $firstName = $user->getFieldValue('altFirstName');
                    $lastName = $user->getFieldValue('altLastName');
                    break;
                }

                if ($group->handle === 'membersGroup') {
                    $groupHandle = 'group';
                    $firstName = $user->getFieldValue('organisation');
                    $lastName = $user->getFieldValue('contactPerson');
                    break;
                }
            }


            $data[] = [
                $customMemberId,
                $groupHandle,
                $user->customStatus,
                $firstName,
                $lastName,
                $formattedBirthday,
                $user->email,
                $user->country,
                $user->street,
                $user->streetNr,
                $user->bus,
                $user->city,
                $user->postalCode,
                $memberRateTitle, 
                $formattedRegisterDate,
                $formattedRenewDate,
                $formattedDueDate,
                $relatedEntriesCount,
                $user->totalPayedMembers ? $user->totalPayedMembers->getAmount()/100 : 0,
                $formattedPayDate,
                $user->paymentType ? $user->paymentType->label : '',
                $user->cardType,
                $formattedPrintRequest,
                $formattedPrintPayed,
                $user->totalPayedPrint ? $user->totalPayedPrint->getAmount()/100 : 0,
            ];
        }

        $handle = fopen('php://temp', 'r+');
        foreach ($data as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);
        $csvOutput = stream_get_contents($handle) ?: '';
        fclose($handle);

        $fileName = 'members-export-' . date('d-m-Y') . '.csv';

        $response = Craft::$app->response;
        $response->format = Response::FORMAT_RAW;
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $fileName . '"');
        $response->data = "\xEF\xBB\xBF" . $csvOutput;

        return $response;
    }
}
