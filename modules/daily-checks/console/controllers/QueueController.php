<?php

namespace modules\dailychecks\console\controllers;

use Craft;
use craft\console\Controller;
use modules\dailychecks\DailyChecks;
use yii\console\ExitCode;

class QueueController extends Controller
{
    public function actionRunJobs()
    {
        DailyChecks::addJobsToQueue();
        $this->stdout("All payment reminder jobs have been added to the queue.\n");
        return ExitCode::OK;
    }
}