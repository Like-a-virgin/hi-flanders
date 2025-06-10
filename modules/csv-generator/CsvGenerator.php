<?php

namespace modules\csvgenerator;

use Craft;
use yii\base\Module as BaseModule;

/**
 * CsvGenerator module
 *
 * @method static CsvGenerator getInstance()
 */
class CsvGenerator extends BaseModule
{
    public function init(): void
    {
        Craft::setAlias('@modules/csvgenerator', __DIR__);

        // Set the controllerNamespace based on whether this is a console or web request
        if (Craft::$app->request->isConsoleRequest) {
            $this->controllerNamespace = 'modules\\csvgenerator\\console\\controllers';
        } else {
            $this->controllerNamespace = 'modules\\csvgenerator\\controllers';
        }

        parent::init();

        $this->attachEventHandlers();

        // Any code that creates an element query or loads Twig should be deferred until
        // after Craft is fully initialized, to avoid conflicts with other plugins/modules
        Craft::$app->onInit(function () {
            Craft::$app->urlManager->addRules([
                'actions/csvgenirator/generate' => 'csvgenirator/default/generate',
            ], false);
        });
    }

    private function attachEventHandlers(): void
    {
        // Register event handlers here ...
        // (see https://craftcms.com/docs/5.x/extend/events.html to get started)
    }
}
