<?php

namespace modules\dailychecks;

use Craft;
use yii\base\Module as BaseModule;
use modules\dailychecks\jobs\DailyActivationCheck;
use modules\dailychecks\jobs\DailyDeactivationCheck;
use modules\dailychecks\jobs\DailyPaymentCheck;
use modules\dailychecks\jobs\DailyRenewalCheck;
use modules\dailychecks\jobs\DailyExtrasCheck;
use modules\dailychecks\jobs\DailyResaveUsers;

/**
 * DailyChecks module
 *
 * @method static DailyChecks getInstance()
 */
class DailyChecks extends BaseModule
{
    public function init(): void
    {
        Craft::setAlias('@modules/dailychecks', __DIR__);

        // Set the controllerNamespace based on whether this is a console or web request
        if (Craft::$app->request->isConsoleRequest) {
            $this->controllerNamespace = 'modules\\dailychecks\\console\\controllers';
        } else {
            $this->controllerNamespace = 'modules\\dailychecks\\controllers';
        }

        parent::init();

    }

    public static function addJobsToQueue()
    {
        $queue = Craft::$app->queue;

        $queue->push(new DailyResaveUsers());             
        $queue->push(new DailyActivationCheck());       
        $queue->push(new DailyRenewalCheck());           
        $queue->push(new DailyPaymentCheck());           
        $queue->push(new DailyDeactivationCheck());
        $queue->push(new DailyExtrasCheck());

        Craft::info('All jobs have been added to the queue.', __METHOD__);
    }
}
