<?php

namespace modules\rateextramember\controllers;

use Craft;
use yii\web\Response;
use craft\web\Controller;
use craft\elements\Entry;

class ConnectController extends Controller
{
    protected array|int|bool $allowAnonymous = false;

    public function actionSave(): Response
    {
        $request = Craft::$app->getRequest();
        $user = Craft::$app->getUser()->getIdentity();
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
            Craft::$app->getSession()->setError(Craft::t('site', 'Je bent al gekoppeld aan dit kind.'));
            return $this->redirectToPostedUrl();
        }

        $newParents = array_unique(array_merge($existing, [$user->id]));
        $entry->setFieldValue('parentMember', $newParents);

        if (!Craft::$app->getElements()->saveElement($entry)) {
            Craft::$app->getSession()->setError(Craft::t('site', 'Kon kind niet koppelen.'));
        }

        return $this->redirectToPostedUrl();
    }
}
