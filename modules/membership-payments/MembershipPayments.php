<?php

namespace modules\membershippayments;

use Craft;
use yii\base\Module as BaseModule;
use Mollie\Api\MollieApiClient;


class MembershipPayments extends BaseModule
{
    private MollieApiClient $mollie;

    public function init(): void
    {
        Craft::setAlias('@modules/membershippayments', __DIR__);

        // Set the controllerNamespace based on whether this is a console or web request
        $this->controllerNamespace = Craft::$app->request->isConsoleRequest
            ? 'modules\\membershippayments\\console\\controllers'
            : 'modules\\membershippayments\\controllers';

        parent::init();

        $this->initializeMollie();
    }

    private function initializeMollie(): void
    {
        $apiKey = getenv('MOLLIE_API_KEY');

        if (!$apiKey) {
            Craft::error('Mollie API key is not set in the enviroment file.', __METHOD__);
            return;
        }

        $this->mollie = new MollieApiClient();
        $this->mollie->setApiKey($apiKey);
    }

    public function getMollie(): MollieApiClient
    {
        return $this->mollie;
    }
}
