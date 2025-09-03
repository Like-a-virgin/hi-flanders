<?php

namespace modules\accountapi;

use Craft;
use yii\base\Module as BaseModule;
use yii\base\Event;
use yii\web\Application;
use yii\web\Response;

class AccountApi extends BaseModule
{
    public static string $jwtSecret;

    public function init(): void
    {
        Craft::setAlias('@modules/accountapi', __DIR__);

        self::$jwtSecret = getenv('JWT_SECRET') ?: 'fallbackSecret';

        if (Craft::$app->request->isConsoleRequest) {
            $this->controllerNamespace = 'modules\\accountapi\\console\\controllers';
        } else {
            $this->controllerNamespace = 'modules\\accountapi\\controllers';
        }

        parent::init();

        Event::on(
            Application::class,
            Application::EVENT_BEFORE_REQUEST,
            function () {
                $this->handleCorsPreflight();
            }
        );

        Craft::$app->onInit(function() {

        });
    }

    private function handleCorsPreflight(): void
    {
        $request = Craft::$app->getRequest();
        $response = Craft::$app->getResponse();
        $origin = $request->getOrigin();

        $allowedOrigins = [
            'http://localhost:4200',
            'https://app.hiflanders.be',
            'capacitor://localhost'
        ];

        if (in_array($origin, $allowedOrigins, true)) {
            $response->getHeaders()
                ->set('Access-Control-Allow-Origin', $origin)
                ->set('Access-Control-Allow-Methods', 'GET, POST')
                ->set('Access-Control-Allow-Headers', 'Authorization, Content-Type')
                ->set('Access-Control-Allow-Credentials', 'true');
        }

        if ($request->getMethod() === 'OPTIONS') {
            Craft::$app->end();
        }
    }
}
