<?php

namespace modules\customactivationmailing;

use Craft;
use yii\base\Event;
use craft\elements\User;
use craft\events\ModelEvent;
use craft\mail\Message;
use craft\mail\Mailer;
use yii\mail\MailEvent;

use yii\base\Module as BaseModule;

/**
 * CustomActivationMailing module
 *
 * @method static CustomActivationMailing getInstance()
 */
class CustomActivationMailing extends BaseModule
{
    public function init(): void
    {
        Craft::setAlias('@modules/customactivationmailing', __DIR__);

        // Set the controllerNamespace based on whether this is a console or web request
        if (Craft::$app->request->isConsoleRequest) {
            $this->controllerNamespace = 'modules\\customactivationmailing\\console\\controllers';
        } else {
            $this->controllerNamespace = 'modules\\customactivationmailing\\controllers';
        }

        parent::init();

        $this->attachEventHandlers();
    }

    private function attachEventHandlers(): void
    {
        // Event::on(
        //     Mailer::class,
        //     Mailer::EVENT_BEFORE_SEND,
        //     function (MailEvent $event) {
        //         $message = $event->message;

        //         Craft::dd($message);

        //         if (isset($message->key) && $message->key === 'account_activation') {
        //             $event->isValid = false; // Prevent sending
        //         }
        //     }
        // );

        // Send a custom activation email after a new user is created
        // Event::on(
        //     User::class,
        //     User::EVENT_AFTER_SAVE,
        //     function (ModelEvent $event) {
        //         $user = $event->sender;
        //         // Ensure it's the first save for a new user
        //         if ($user->firstSave && $user->getStatus() === 'pending') {
        //             $this->sendCustomActivationEmail($user);
        //         }
        //     }
        // );
    }

    private function sendCustomActivationEmail(User $user): void
    {

    }
}
