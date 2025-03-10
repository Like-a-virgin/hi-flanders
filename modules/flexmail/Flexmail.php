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
        $newsletterSchoolField = $user->getFieldValue('newsletterSchool');
        $language = $user->getFieldValue('lang')->value ?? 'nl';
        $interests = [];

        if ($newsletterField) {
            foreach ($newsletterField as $option) {
                if ($option->value === 'enroll' && $option->selected) {
                    if ($language === 'nl') {
                        $interests[] = '19cf14ba-4350-4e6b-8773-ee738a0b2f49';
                    }
                }
            }
        }
        
        if ($newsletterSchoolField) {
            foreach ($newsletterSchoolField as $option) {
                if ($option->value === 'enroll' && $option->selected) {
                    if ($language === 'nl') {
                        $interests[] = 'a30976b4-7613-42ce-a4bb-497c6616e1e3';
                    } elseif ($language === 'fr') {
                        $interests[] = 'e8be5ac9-597d-460c-af98-a6e4b5fb31a5';
                    }
                }
            }
        }

        
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
                        'json' => [
                            'first_name' => $user->getFieldValue('altFirstName') ?? $user->getFieldValue('organisation'),
                            'name' => $user->getFieldValue('altLastName') ?? $user->getFieldValue('contactPerson'),
                            'language' => $language,
                            'custom_fields' => [
                                'lid_type' => $user->getFieldValue('memberType')->value ?? '',
                            ],
                        ],
                    ]);

                    foreach ($interests as $interestId) {
                        $client->post("$apiUrl/$contactId/interest-subscriptions", [
                            'auth' => [$apiUsername, $apiPassword],
                            'json' => ['interest_id' => $interestId],
                        ]);
                    }
                    
                    Craft::info("User {$user->email} updated in Flexmail.", __METHOD__);
                    return;
                }
            }
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() !== 404) {
                Craft::error('Flexmail check error: ' . $e->getMessage(), __METHOD__);
                return;
            }
        }

        try {
            $contactData = [
                'email' => $user->email,
                'first_name' => $user->getFieldValue('altFirstName') ?? $user->getFieldValue('organisation'),
                'name' => $user->getFieldValue('altLastName') ?? $user->getFieldValue('contactPerson'),
                'language' => $language,
                'custom_fields' => [
                    'lid_type' => $user->getFieldValue('memberType')->value ?? '',
                ],
                'source' => 861500,
            ];
            
            $createResponse = $client->post($apiUrl, [
                'auth' => [$apiUsername, $apiPassword],
                'json' => $contactData,
            ]);
            
            $createdContact = json_decode($createResponse->getBody(), true);
            $contactId = $createdContact['id'] ?? null;
            
            if ($contactId) {
                foreach ($interests as $interestId) {
                    $client->post("$apiUrl/$contactId/interest-subscriptions", [
                        'auth' => [$apiUsername, $apiPassword],
                        'json' => ['interest_id' => $interestId],
                    ]);
                }
                Craft::info("User {$user->email} created and subscribed to interests in Flexmail.", __METHOD__);
            }
        } catch (\Exception $e) {
            Craft::error('Flexmail sync error: ' . $e->getMessage(), __METHOD__);
        }
    }
}
