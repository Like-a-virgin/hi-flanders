<?php

namespace modules\emailmessages;

use Craft;
use craft\events\RegisterEmailMessagesEvent;
use craft\services\SystemMessages;
use yii\base\Event;
use yii\base\Module as BaseModule;

/**
 * EmailMessages module
 *
 * @method static EmailMessages getInstance()
 */
class EmailMessages extends BaseModule
{
    public function init(): void
    {
        Craft::setAlias('@modules/emailmessages', __DIR__);

        // Set the controllerNamespace based on whether this is a console or web request
        if (Craft::$app->request->isConsoleRequest) {
            $this->controllerNamespace = 'modules\\emailmessages\\console\\controllers';
        } else {
            $this->controllerNamespace = 'modules\\emailmessages\\controllers';
        }

        parent::init();

        Event::on(
            SystemMessages::class,
            SystemMessages::EVENT_REGISTER_MESSAGES,
            function (RegisterEmailMessagesEvent $event) {
                $event->messages[] = [
                    'key' => 'custom_email_key',
                    'heading' => 'Custom Email', // This appears in the System Messages UI
                    'subject' => 'Custom Email Subject', // Default subject
                    'body' => 'Hello {{ user.username }},<br><br>This is a custom email body.<br><br>Thank you!', // Default body
                ];
            }
        );
    }
}
