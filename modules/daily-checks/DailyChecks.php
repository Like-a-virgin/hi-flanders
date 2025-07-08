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

        $queue->push(new DailyResaveUsers(['delay' => 0]));             
        $queue->push(new DailyActivationCheck(['delay' => 130]));       
        $queue->push(new DailyRenewalCheck(['delay' => 260]));           
        $queue->push(new DailyPaymentCheck(['delay' => 390]));           
        $queue->push(new DailyDeactivationCheck(['delay' => 520]));
        $queue->push(new DailyExtrasCheck(['delay' => 650]));

        Craft::info('All payment reminder jobs have been added to the queue.', __METHOD__);
    }
}
