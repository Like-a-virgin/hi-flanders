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

            if (!$birthday) {
                continue;
            }

            // Safely convert birthday to DateTime
            try {
                if (!($birthday instanceof \DateTime)) {
                    $birthday = new \DateTime(is_array($birthday) ? $birthday['date'] : $birthday);
                }
            } catch (\Throwable $e) {
                Craft::error("Invalid birthday for extra member ID {$extraMember->id}: " . $e->getMessage(), __METHOD__);
                continue;
            }

            // Calculate 18th birthday
            $eighteenthBirthday = (clone $birthday)->modify('+19 years');

            // If today is their 18th birthday, delete
            if ($eighteenthBirthday->format('Y-m-d') === $today) {
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