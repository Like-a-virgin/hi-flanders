<?php

namespace modules\flexmail;

use Craft;

use craft\elements\User;
use craft\events\ElementEvent;
use craft\services\Elements;
use yii\base\Event;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

use craft\events\ModelEvent;

use yii\base\Module as BaseModule;


/**
 * Flexmail module
 *
 * @method static Flexmail getInstance()
 */
class Flexmail extends BaseModule
{
    public function init(): void
    {
        Craft::setAlias('@modules/flexmail', __DIR__);

        parent::init();
        $this->attachEventHandlers();
    }

    private function attachEventHandlers(): void
    {
        Event::on(
            Elements::class,
            Elements::EVENT_AFTER_SAVE_ELEMENT,
            function (ElementEvent $event) {
                $element = $event->element;

                if ($element instanceof User && $this->shouldSyncUser($element)) {
                    $this->syncToFlexmail($element);
                }
            }
        );
    }

    private function shouldSyncUser(User $user): bool
    {
        $allowedGroups = ['members', 'membersGroup'];

        $userGroup = Craft::$app->getRequest()->getBodyParam('groupHandle');
    
        if (!$userGroup) {
            Craft::error('User group field is empty for user ID: ' . $user->id, __METHOD__);
            return false;
        }
    
        if (in_array($userGroup, $allowedGroups)) {
            return true;
        }
    
        return false;
    }

    private function syncToFlexmail(User $user)
    {
        $apiUrl = 'https://api.flexmail.eu/contacts';
        $apiUsername = getenv('FLEXMAIL_USER_ID');
        $apiPassword = getenv('FLEXMAIL_API_KEY');

        $client = new Client();

        $newsletterField = $user->getFieldValue('newsletter');
        $customCheckboxValue = false;

        if ($newsletterField) {
            foreach ($newsletterField as $option) {
                if ($option->value === 'enroll' && $option->selected) {
                    $customCheckboxValue = true;
                    break;
                }
            }
        }

        $contactData = [
            'first_name' => $user->getFieldValue('altFirstName') ?? $user->getFieldValue('organisation'),
            'name' => $user->getFieldValue('altLastName') ?? $user->getFieldValue('contactPerson'),
            'language' => $user->getFieldValue('lang')->value ?? 'nl',
            'custom_fields' => [
                'lid_type' => $user->getFieldValue('memberType')->value ?? '',
                'nieuwsbrief' => $customCheckboxValue ? 'true' : 'false',
            ]        
        ];

        try {
            
            $existingContactResponse = $client->get("$apiUrl?email={$user->email}", [
                'auth' => [$apiUsername, $apiPassword]
            ]);
            
            if ($existingContactResponse->getStatusCode() === 200) {
                $existingContact = json_decode($existingContactResponse->getBody(), true);
                $contactId = $existingContact['_embedded']['item'][0]['id'] ?? null;
                
                if ($contactId) {
                    $updateUrl = "$apiUrl/$contactId";
                    $client->patch($updateUrl, [
                        'auth' => [$apiUsername, $apiPassword],
                        'json' => $contactData,
                    ]);

                    
                    Craft::info("User {$user->email} updated in Flexmail.", __METHOD__);
                    return;
                }
            }
        } catch (ClientException $e) {
            Craft::dd('catch');
            if ($e->getResponse()->getStatusCode() !== 404) {
                Craft::error('Flexmail check error: ' . $e->getMessage(), __METHOD__);
                return;
            }
        }

        try {
            $contactData['email'] = $user->email;
            $contactData['source'] = 861500;
            $client->post($apiUrl, [
                'auth' => [$apiUsername, $apiPassword],
                'json' => $contactData,
            ]);
            Craft::info("User {$user->email} created in Flexmail.", __METHOD__);
        } catch (\Exception $e) {
            Craft::error('Flexmail sync error: ' . $e->getMessage(), __METHOD__);
        }
    }
}
