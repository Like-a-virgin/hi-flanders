<?php

namespace modules\excelusers;

use Craft;
use yii\base\Module as BaseModule;

/**
 * ExcelUsers module
 *
 * @method static ExcelUsers getInstance()
 */
class ExcelUsers extends BaseModule
{
    public function init(): void
    {
        Craft::setAlias('@modules/excelusers', __DIR__);

        // Set the controllerNamespace based on whether this is a console or web request
        if (Craft::$app->request->isConsoleRequest) {
            $this->controllerNamespace = 'modules\\excelusers\\console\\controllers';
        } else {
            $this->controllerNamespace = 'modules\\excelusers\\controllers';
        }

        parent::init();

        $this->attachEventHandlers();

        Craft::$app->onInit(function() {
            Craft::info('ExcelUsers module initialized.', __METHOD__);
        });
    }

    private function attachEventHandlers(): void
    {
        // Register event handlers here ...
        // (see https://craftcms.com/docs/5.x/extend/events.html to get started)
    }
}
