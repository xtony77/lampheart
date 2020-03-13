<?php

namespace lampheart\Providers;

use Illuminate\Database\Capsule\Manager;

class DatabaseProvider
{
    public function __construct()
    {
        $config = dirname(dirname(dirname(dirname(dirname(dirname(__DIR__)))))).'/config/database.php';

        if (!is_file($config)) {
            throw new \Exception('Database config not exist: '.$config);
        }

        $capsule = new Manager;
        $capsule->addConnection(require_once $config);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
    }
}