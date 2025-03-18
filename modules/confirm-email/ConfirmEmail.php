<?php

namespace modules\confirmemail;

use Craft;
use craft\elements\User;
use craft\events\ModelEvent;
use yii\base\Event;
use yii\base\Module as BaseModule;

/**
 * ConfirmEmail module
 *
 * @method static ConfirmEmail getInstance()
 */
class ConfirmEmail extends BaseModule
{
    public function init(): void
    { 
        Craft::setAlias('@modules/confirmemail', __DIR__);

        // Set the controllerNamespace based on whether this is a console or web request
        if (Craft::$app->request->isConsoleRequest) {
            $this->controllerNamespace = 'modules\\confirmemail\\console\\controllers';
        } else {
            $this->controllerNamespace = 'modules\\confirmemail\\controllers';
        }

        parent::init();

        $this->attachEventHandlers();
    }

    private function attachEventHandlers(): void
    {
        // Hook into the User element's beforeSave event
        Event::on(
            User::class,
            User::EVENT_BEFORE_SAVE,
            function (ModelEvent $event) {
                /** @var User $user */
                $user = $event->sender;
                $request = Craft::$app->getRequest();

                if ($request->getIsSiteRequest() && $request->getIsPost()) {
                    // Check if "confirmEmail" exists in the request (relevant for forms that include it)
                    $confirmEmail = $request->getBodyParam('confirmEmail');
    
                    // Skip validation if "confirmEmail" is not part of the request
                    if ($confirmEmail !== null) {
                        // Validate that the email and confirmEmail fields match
                        if ($user->email !== $confirmEmail) {
                            $user->addError('email', Craft::t('site', 'E-mailadressen komen niet overeen.'));
                            $event->isValid = false;
                        }
                    }
                }

                if ($event->isNew) {
                    Craft::error("Before Save Triggered for: " . $user->email, __METHOD__);

                    if ($this->checkUserExists($user)) {
                        $user->addError('email', Craft::t('site', 'Dit e-mailadres is al in gebruik, maar is gedeactiveerd'));
                        $event->isValid = false;
                        Craft::$app->getSession()->setError("Dit e-mailadres is al in gebruik. Gebruik een ander e-mailadres of herstel je wachtwoord.");
                        Craft::error("User save blocked: " . $user->email, __METHOD__);
                        return;
                    }
                }
            }
        );
    }

    private function checkUserExists(User $user): bool
    {
        if (empty($user->email)) {
            return false; // Avoids calling getUserByUsernameOrEmail with a null value
        }
        
        return Craft::$app->users->getUserByUsernameOrEmail($user->email) !== null;
    }
}
