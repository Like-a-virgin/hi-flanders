<?php

namespace modules\excelusers\controllers;

use Craft;
use craft\elements\Entry;
use craft\elements\User;
use craft\web\Controller;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use yii\web\Response;
use yii\web\ForbiddenHttpException;

/**
 * Handles exporting member data from the extraMembers section to Excel
 */
class ExportKidsController extends Controller
{
    protected array|int|bool $allowAnonymous = ['users'];  // Adjust if anonymous access is not needed

    public function actionKids(): Response
    {
        $currentUser = Craft::$app->getUser()->getIdentity();
        if (!$currentUser) {
            throw new ForbiddenHttpException('You must be logged in to access this resource.');
        }

        $isMemberAdmin = false;
        foreach ($currentUser->getGroups() as $group) {
            if ($group->handle === 'membersAdmin' || $group->handle === 'membersAdminSuper') {
                $isMemberAdmin = true;
                break;
            }
        }

        if (!$isMemberAdmin) {
            throw new ForbiddenHttpException('You do not have permission to access this resource.');
        }

        // Query to get all entries from the 'extraMembers' section
        $entriesQuery = Entry::find()
            ->section('extraMembers'); // Adjust section handle if needed

        $entries = $entriesQuery->all();

        $data = [[
            'First Name',
            'Last Name',
            'Birthday',
            'Age',
            'Member Rate',
            'Linked parents',
            'Parent Name',
            'Parent Email',
            'Parent Custom Member ID', // Added column for parent's customMemberId
        ]]; 

        foreach ($entries as $entry) {
            // Get first name, last name, and birthday
            $firstName = $entry->altFirstName;
            $lastName = $entry->altLastName;
            $birthDate = $entry->birthday;
            $birthday = $birthDate ? $birthDate->format('d/m/Y') : '';
            $age = '';

            if ($birthDate) {
                $now = new \DateTime('now', $birthDate->getTimezone());
                $age = $birthDate->diff($now)->y;
            }

            // Get the memberRate title
            $memberRateEntry = $entry->memberRate->one();
            $memberRateTitle = $memberRateEntry ? $memberRateEntry->title : '';

            // Get linked parent(s)
            $parentUsers = $entry->parentMember; // This is a "multiple" relation field, so it could contain multiple users
            $parentNames = [];
            $parentEmails = [];
            $parentCustomMemberIds = [];

            foreach ($parentUsers as $parentUser) {
                // Get the parent's name, email, and customMemberId
                $parentNames[] = $parentUser->getFullName();
                $parentEmails[] = $parentUser->email;
                $parentCustomMemberIds[] = $parentUser->customMemberId ? '(008) ' . number_format($parentUser->customMemberId, 0, '', '') : ''; // Ensure customMemberId is formatted
            }

            // Prepare the row for export (join multiple parents if needed)
            $data[] = [
                $firstName,
                $lastName,
                $birthday,
                $age,
                $memberRateTitle,
                count($parentUsers->all()),
                implode(', ', $parentNames),  // Combine multiple parent names into a single string
                implode(', ', $parentEmails), // Combine multiple parent emails into a single string
                implode(', ', $parentCustomMemberIds), // Combine multiple parent's customMemberIds into a single string
            ];
        }

        // Create spreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Populate spreadsheet with data
        foreach ($data as $rowIndex => $row) {
            foreach ($row as $colIndex => $value) {
                $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
                $cellCoordinate = $columnLetter . ($rowIndex + 1);
                $sheet->setCellValue($cellCoordinate, $value);
            }
        }

        // Auto resize columns
        $highestColumn = $sheet->getHighestColumn();
        $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

        for ($colIndex = 1; $colIndex <= $highestColumnIndex; $colIndex++) {
            $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
            $sheet->getColumnDimension($columnLetter)->setAutoSize(true);
        }

        // Set file name and path
        $fileName = 'kids-export-' . date('d-m-Y') . '.xlsx';
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
