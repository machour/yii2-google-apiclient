<?php

namespace machour\yii2\google\apiclient;

use yii\base\BootstrapInterface;
use yii\base\Module as BaseModule;

class Module extends BaseModule implements BootstrapInterface
{
    public $controllerNamespace = 'machour\yii2\google\apiclient\controllers';

    public function bootstrap($app)
    {
        if ($app instanceof \yii\console\Application) {
            $app->controllerMap[$this->id] = [
                'class' => 'machour\yii2\google\apiclient\commands\GoogleController',
                'module' => $this,
            ];
        }
    }
}
