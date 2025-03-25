<?php

namespace modules\rateextramember\controllers;

use Craft;
use yii\web\Response;
use craft\web\Controller;
use craft\elements\Entry;
use craft\elements\User;

class ConnectController extends Controller
{
    protected array|int|bool $allowAnonymous = false;

    public function actionSave(): Response
    {
        $request = Craft::$app->getRequest();
        $currentUser = Craft::$app->getUser()->getIdentity();

        // Get optional selected member (for admin use)
        $selectedMemberId = $request->getBodyParam('selectedMemberId') ?? null;

        // If selectedMemberId is passed, fetch that user, otherwise fallback to current user
        /** @var User|null $user */
        $user = $selectedMemberId
            ? User::find()->id($selectedMemberId)->one()
            : $currentUser;

        // Guard against invalid selected user
        if (!$user) {
            Craft::$app->getSession()->setError(Craft::t('site', 'Lid niet gevonden.'));
            return $this->redirectToPostedUrl();
        }

        $entryId = $request->getBodyParam('entryId');
        $entry = Entry::find()->id($entryId)->one();

        if (!$entry) {
            Craft::$app->getSession()->setError(Craft::t('site', 'KidId bestaat niet.'));
            return $this->redirectToPostedUrl();
        }

        if ($entry->section->handle !== 'extraMembers') {
            Craft::$app->getSession()->setError(Craft::t('site', 'KidId hoort niet bij extraMembers.'));
            return $this->redirectToPostedUrl();
        }

        $existing = $entry->getFieldValue('parentMember')->ids();
        if (in_array($user->id, $existing)) {
            Craft::$app->getSession()->setError(Craft::t('site', 'Deze gebruiker is al gekoppeld aan dit kind.'));
            return $this->redirectToPostedUrl();
        }

        $newParents = array_unique(array_merge($existing, [$user->id]));
        $entry->setFieldValue('parentMember', $newParents);

        if (!Craft::$app->getElements()->saveElement($entry)) {
            Craft::$app->getSession()->setError(Craft::t('site', 'Kon kind niet koppelen.'));
        } else {
            Craft::$app->getSession()->setNotice(Craft::t('site', 'Kind succesvol gekoppeld.'));
        }

        return $this->redirectToPostedUrl();
    }
}
