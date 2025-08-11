<?php

namespace modules\resendactivation;

use Craft;
use yii\base\Module as BaseModule;

/**
 * ResendActivation module
 *
 * @method static ResendActivation getInstance()
 */
class ResendActivation extends BaseModule
{
    public function init(): void
    {
        Craft::setAlias('@modules/resendactivation', __DIR__);

        // Set the controllerNamespace based on whether this is a console or web request
        if (Craft::$app->request->isConsoleRequest) {
            $this->controllerNamespace = 'modules\\resendactivation\\console\\controllers';
        } else {
            $this->controllerNamespace = 'modules\\resendactivation\\controllers';
        }

        parent::init();

        $this->attachEventHandlers();

        // Any code that creates an element query or loads Twig should be deferred until
        // after Craft is fully initialized, to avoid conflicts with other plugins/modules
        Craft::$app->onInit(function() {
            // ...
        });
    }

    private function attachEventHandlers(): void
    {
        // Register event handlers here ...
        // (see https://craftcms.com/docs/5.x/extend/events.html to get started)
    }
}
