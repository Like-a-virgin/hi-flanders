<?php 

namespace modules\dailychecks\jobs;

use Craft;
use craft\queue\BaseJob;
use craft\elements\Entry;
use DateTime;
use DateTimeZone;

class DailyExtrasCheck extends BaseJob
{
    public function execute($queue): void
    {
        $currentDate = new DateTime('now', new DateTimeZone('CET'));
        $today = $currentDate->format('Y-m-d');

        $extraMembers = Entry::find()
            ->section('extraMembers') // Adjust if needed
            ->all();

        foreach ($extraMembers as $extraMember) {
            $birthday = $extraMember->getFieldValue('birthday');
            $memberDueDate = $extraMember->getFieldValue('memberDueDate');

            // ✅ Ensure both values exist
            if (!$birthday || !$memberDueDate) {
                continue;
            }

            // ✅ Convert birthday & due date to DateTime
            $birthDate = $birthday instanceof DateTime ? $birthday : new DateTime($birthday);
            $dueDate = $memberDueDate instanceof DateTime ? $memberDueDate : new DateTime($memberDueDate);

            // ✅ Calculate age
            $age = $birthDate->diff($currentDate)->y;

            // ✅ Check if the user is 18+ and their memberDueDate is today
            if ($age >= 18 && $dueDate->format('Y-m-d') === $today) {
                $this->removeExtraMember($extraMember);
            }
        }
    }

    private function removeExtraMember(Entry $extraMember): void
    {
        // ✅ Delete the extra member
        if (!Craft::$app->elements->deleteElement($extraMember)) {
            Craft::error('Failed to delete extra member: ' . $extraMember->id, __METHOD__);
        } else {
            Craft::info('Successfully deleted extra member: ' . $extraMember->id, __METHOD__);
        }
    }

    protected function defaultDescription(): string
    {
        return 'Processing +18 extra members and removing them if their membership expires today.';
    }
}