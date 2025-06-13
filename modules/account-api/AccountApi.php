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

        $this->attachCorsHeaders();

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
        ];

        if (in_array($origin, $allowedOrigins, true)) {
            $response->getHeaders()
                ->set('Access-Control-Allow-Origin', $origin)
                ->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
                ->set('Access-Control-Allow-Headers', 'Authorization, Content-Type')
                ->set('Access-Control-Allow-Credentials', 'true');
        }

        if ($request->getMethod() === 'OPTIONS') {
            // Short-circuit preflight requests
            Craft::$app->end();
        }
    }

    private function attachCorsHeaders(): void
    {
        Event::on(
            Response::class,
            Response::EVENT_BEFORE_SEND,
            function (Event $event) {
                $response = $event->sender;
                $request = Craft::$app->getRequest();
                $origin = $request->getOrigin();

                $allowedOrigins = [
                    'http://localhost:4200',
                    'https://app.hiflanders.be',
                ];

                if (in_array($origin, $allowedOrigins, true)) {
                    $response->getHeaders()
                        ->set('Access-Control-Allow-Origin', $origin)
                        ->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
                        ->set('Access-Control-Allow-Headers', 'Authorization, Content-Type')
                        ->set('Access-Control-Allow-Credentials', 'true');
                }
            }
        );
    }
}
