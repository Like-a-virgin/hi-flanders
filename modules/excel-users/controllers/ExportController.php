<?php

namespace modules\excelusers\controllers;

use Craft;
use craft\elements\User;
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

        $users = User::find()->all();
        $data = [['ID', 'Name', 'Birthday', 'Email', 'Country', 'Street', 'Street number', 'Bus', 'City', 'Postalcode ', 'Membertype', 'Payment type', 'Pay date']];

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

            $data[] = [
                $customMemberId,
                $user->fullName,
                $formattedBirthday,
                $user->email,
                $user->country,
                $user->street,
                $user->streetNr,
                $user->bus,
                $user->city,
                $user->postalCode,
                $memberRateTitle, 
                $user->paymentType,
                $formattedPayDate
            ];
        }

        // Create spreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($data as $rowIndex => $row) {
            foreach ($row as $colIndex => $value) {
                $sheet->setCellValueByColumnAndRow($colIndex + 1, $rowIndex + 1, $value);
            }
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
