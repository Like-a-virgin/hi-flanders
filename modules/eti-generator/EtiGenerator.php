<?php

namespace modules\etigenerator;

use Craft;
use yii\base\Module as BaseModule;

class EtiGenerator extends BaseModule
{
    public function init(): void
    {
        Craft::setAlias('@modules/etigenirator', __DIR__);

        if (Craft::$app->request->isConsoleRequest) {
            $this->controllerNamespace = 'modules\\etigenirator\\console\\controllers';
        } else {
            $this->controllerNamespace = 'modules\\etigenirator\\controllers';
        }

        parent::init();

        $this->attachEventHandlers();

        Craft::$app->onInit(function () {
            Craft::$app->urlManager->addRules([
                'actions/etigenirator/generate' => 'etigenirator/default/generate',
            ], false);
        });
    }

    private function attachEventHandlers(): void
    {

    }
}
