<?php

namespace modules\extramembers;

use Craft;
use yii\base\Module as BaseModule;
use craft\events\ModelEvent;
use craft\services\Elements;
use yii\base\Event;

class Module extends \yii\base\Module
{
    public function init()
    {
        // Define a custom alias named after the namespace
        Craft::setAlias('@foo', __DIR__);

        // Set the controllerNamespace based on whether this is a console or web request
        if (Craft::$app->getRequest()->getIsConsoleRequest()) {
            $this->controllerNamespace = 'foo\\console\\controllers';
        } else {
            $this->controllerNamespace = 'foo\\controllers';
        }

        parent::init();

        // Custom initialization code goes here...
    }
}