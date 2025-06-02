<?php

namespace modules\accountapi;

use Craft;
use yii\base\Module as BaseModule;

class AccountApi extends BaseModule
{
    public static string $jwtSecret;

    public function init(): void
    {
        Craft::setAlias('@modules/accountapi', __DIR__);

        self::$jwtSecret = getenv('JWT_SECRET') ?: 'fallbackSecret';

        if (Craft::$app->request->isConsoleRequest) {
            $this->controllerNamespace = 'modules\\accountapi\\console\\controllers';
        } else {
            $this->controllerNamespace = 'modules\\accountapi\\controllers';
        }

        parent::init();

        $this->attachEventHandlers();

        Craft::$app->onInit(function() {

        });
    }

    private function attachEventHandlers(): void
    {

    }
}
