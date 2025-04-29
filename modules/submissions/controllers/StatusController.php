<?php

namespace modules\submissions\controllers;

use Craft;
use craft\web\Controller;
use verbb\formie\elements\Submission;
use yii\web\BadRequestHttpException;
use yii\web\Response;

class StatusController extends Controller
{
    protected array|bool|int $allowAnonymous = true; // Allow front-end form submissions

    public function actionUpdateStatus(): Response
    {
        $this->requirePostRequest();
        $request = Craft::$app->getRequest();

        $submissionId = $request->getBodyParam('submissionId');
        $statusHandle = $request->getBodyParam('statusHandle');

        if (!$submissionId || !$statusHandle) {
            throw new BadRequestHttpException('Missing required parameters.');
        }

        $submission = Submission::find()->id($submissionId)->one();

        if (!$submission) {
            throw new BadRequestHttpException('Submission not found.');
        }

        $form = $submission->getForm();
        $status = null;
        $statuses = \verbb\formie\Formie::$plugin->getStatuses()->getAllStatuses();


        foreach ($statuses as $formStatus) {
            if ($formStatus->handle === $statusHandle) {
                $status = $formStatus;
                break;
            }
        }

        if (!$status) {
            throw new BadRequestHttpException('Invalid status.');
        }

        $submission->statusId = $status->id;

        if (!Craft::$app->elements->saveElement($submission)) {
            throw new BadRequestHttpException('Could not save submission.');
        }

        return $this->redirectToPostedUrl();
    }
}
