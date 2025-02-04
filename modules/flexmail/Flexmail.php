<?php

namespace modules\flexmail;

use Craft;
use yii\base\Module as BaseModule;
use yii\base\Event;
use craft\services\Elements;
use craft\elements\User;
use craft\events\ElementEvent;
use GuzzleHttp\Client;

/**
 * Flexmail module
 *
 * @method static Flexmail getInstance()
 */
class Flexmail extends BaseModule
{
    private string $apiKey = 'FLEXMAIL_API';

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
                if ($event->element instanceof User && $event->isNew) {
                    $this->addUserToFlexmail($event->element);
                }
            }
        );
    }

    private function addUserToFlexmail(User $user): void
    {
        $allowedGroups = ['members', 'membersGroup'];
        $userGroups = $user->getGroups();
        $isInAllowedGroup = false;

        foreach ($userGroups as $group) {
            if (in_array($group->handle, $allowedGroups)) {
                $isInAllowedGroup = true;
                break;
            }
        }

        if (!$isInAllowedGroup) {
            Craft::info("User {$user->email} is not in the required groups. Skipping Flexmail sync.", __METHOD__);
            return;
        }

        $data = [
            'email' => $user->email,
            'firstName' => $user->firstName,
            'lastName' => $user->lastName,
            'customFields' => [
                'type' => 'member',
                'memberType' => $user->memberType,
            ],
        ];

        try {
            $client = new Client(['base_uri' => 'https://api.flexmail.eu/', 'timeout' => 10.0]);
            $response = $client->post('/v1/contacts', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $data,
            ]);

            $body = json_decode($response->getBody(), true);

            if (isset($body['id'])) {
                Craft::info("Successfully added {$user->email} to Flexmail with ID {$body['id']}.", __METHOD__);
            } else {
                Craft::error("Failed to add {$user->email} to Flexmail: " . json_encode($body), __METHOD__);
            }
        } catch (\Exception $e) {
            Craft::error("Flexmail API error: " . $e->getMessage(), __METHOD__);
        }
    }
}
