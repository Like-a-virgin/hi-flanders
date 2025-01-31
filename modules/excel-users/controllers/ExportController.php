<?php

namespace modules\excelusers\controllers;

use Craft;
use craft\elements\User;
use craft\elements\Entry;
use craft\web\Controller;
use PhpOffice\PhpSpreadsheet\Spreadsheet; 
use PhpOffice\PhpSpreadsheet\Writer\Xlsx; 
use yii\web\Response;
use yii\web\ForbiddenHttpException;

/**
 * Handles exporting user data to Excel
 */
class ExportController extends Controller
{
    protected array|int|bool $allowAnonymous = ['users'];  // Adjust if anonymous access is not needed

    public function actionUsers(): Response
    {
        $currentUser = Craft::$app->getUser()->getIdentity();
        if (!$currentUser) {
            throw new ForbiddenHttpException('You must be logged in to access this resource.');
        }

        $isMemberAdmin = false;
        foreach ($currentUser->getGroups() as $group) {
            if ($group->handle === 'membersAdmin') {
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

            $memberRatePrice = 0;

            if ($memberRateEntry && $memberRateEntry->price) { // Check if memberRateEntry and price are not null
                $price = $memberRateEntry->price;
                $memberRatePrice = method_exists($price, 'getAmount') ? (float) $price->getAmount() / 100 : 0; // Adjust divisor for cents if needed
            }

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
                $user->paymentType->label,
                $user->cardType,
                $formattedPrintRequest,
                $user->payedPrintDate,
                $user->totalPayedPrint ? $user->totalPayedPrint->getAmount()/100 : 0,
            ];
        }

        // Create spreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($data as $rowIndex => $row) {
            foreach ($row as $colIndex => $value) {
                // Convert column index to letter (e.g., 0 => A, 1 => B)
                $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
                $cellCoordinate = $columnLetter . ($rowIndex + 1);
                
                $sheet->setCellValue($cellCoordinate, $value);
            }
        }
        

        $highestColumn = $sheet->getHighestColumn();
        $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

        for ($colIndex = 1; $colIndex <= $highestColumnIndex; $colIndex++) {
            $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
            $sheet->getColumnDimension($columnLetter)->setAutoSize(true);
        }
        
        // Set file name and path
        $fileName = 'members-export-' . date('Y-m-d') . '.xlsx';
        $tempFilePath = Craft::$app->path->getTempPath() . DIRECTORY_SEPARATOR . $fileName;

        // Save spreadsheet to temporary file
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFilePath);

        // Send the file as response
        return Craft::$app->response->sendFile($tempFilePath, $fileName, [
            'mimeType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
