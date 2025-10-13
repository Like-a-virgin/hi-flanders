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
    private bool $syncSuspended = false;

    public function init(): void
    {
        Craft::setAlias('@modules/flexmail', __DIR__);

        parent::init();
        $this->attachEventHandlers();
    }

    public function setSyncSuspended(bool $suspended): void
    {
        $this->syncSuspended = $suspended;
    }

    public function isSyncSuspended(): bool
    {
        return $this->syncSuspended;
    }

    private function attachEventHandlers(): void
    {
        Event::on(
            Elements::class,
            Elements::EVENT_AFTER_SAVE_ELEMENT,
            function (ElementEvent $event) {
                $element = $event->element;

                if ($element instanceof User) {
                    if ($this->syncSuspended) {
                        Craft::info('Flexmail sync skipped: suspended during automated job (user ID ' . $element->id . ').', __METHOD__);
                        return;
                    }

                    if ($this->shouldSyncUser($element)) {
                        $this->syncToFlexmail($element);
                    }
                }
            }
        );
    }

    private function shouldSyncUser(User $user): bool
    {
        $allowedGroups = ['members', 'membersGroup'];

        $request = Craft::$app->getRequest();
        $groupHandles = [];

        if ($request instanceof \craft\web\Request) {
            $bodyGroupHandle = $request->getBodyParam('groupHandle');
            if ($bodyGroupHandle) {
                $groupHandles[] = $bodyGroupHandle;
            }
        }

        if (!$groupHandles) {
            foreach ($user->getGroups() as $group) {
                $groupHandles[] = $group->handle;
            }
        }

        foreach ($groupHandles as $handle) {
            if (in_array($handle, $allowedGroups, true)) {
                return true;
            }
        }

        Craft::info('Flexmail sync skipped: user not in allowed groups (user ID ' . $user->id . ').', __METHOD__);
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
                        try {
                            $client->post("$apiUrl/$contactId/interest-subscriptions", [
                                'auth' => [$apiUsername, $apiPassword],
                                'json' => ['interest_id' => $interestId],
                            ]);
                        } catch (ClientException $e) {
                            $statusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : null;

                            if ($statusCode === 409) {
                                Craft::info("Interest {$interestId} already attached for user {$user->email}.", __METHOD__);
                                continue;
                            }

                            throw $e;
                        }
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
                    try {
                        $client->post("$apiUrl/$contactId/interest-subscriptions", [
                            'auth' => [$apiUsername, $apiPassword],
                            'json' => ['interest_id' => $interestId],
                        ]);
                    } catch (ClientException $e) {
                        $statusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : null;

                        if ($statusCode === 409) {
                            Craft::info("Interest {$interestId} already attached for user {$user->email}.", __METHOD__);
                            continue;
                        }

                        throw $e;
                    }
                }
                Craft::info("User {$user->email} created and subscribed to interests in Flexmail.", __METHOD__);
            }
        } catch (\Exception $e) {
            Craft::error('Flexmail sync error: ' . $e->getMessage(), __METHOD__);
        }
    }
}
