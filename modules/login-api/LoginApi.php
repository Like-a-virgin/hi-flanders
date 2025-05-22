<?php

namespace modules\loginapi;

use Craft;
use yii\base\Module as BaseModule;

/**
 * LoginApi module
 *
 * @method static LoginApi getInstance()
 */
class LoginApi extends BaseModule
{
    public static string $jwtSecret;
    
    public function init(): void
    {
        Craft::setAlias('@modules/loginapi', __DIR__);

        self::$jwtSecret = getenv('JWT_SECRET') ?: 'fallbackSecret';

        // Set the controllerNamespace based on whether this is a console or web request
        if (Craft::$app->request->isConsoleRequest) {
            $this->controllerNamespace = 'modules\\loginapi\\console\\controllers';
        } else {
            $this->controllerNamespace = 'modules\\loginapi\\controllers';
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
