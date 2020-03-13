<?php

namespace lampheart\Console;

class Kernel
{
    protected $commands = [];

    public function make($params)
    {
        $this->loadCommandsClass($params);
    }

    private function loadCommandsClass($params)
    {
        $commandName = $params[0];
        unset($params[0]);
        $params = array_values($params);

        if ($commandName === 'key:generate') {
            $this->keygen();
        }

        $matchStatus = false;
        foreach ($this->commands as $key => $fileName) {
            $path = dirname(dirname(dirname(dirname(dirname(dirname(__DIR__)))))).'/app/Console/Commands/'.$fileName.'.php';

            if (!is_file($path)) {
                throw new \Exception('Command not exist: '.$path);
            }

            require_once $path;
            $class = 'App\\Console\\Commands\\'.$fileName;
            if ($class::$name === $commandName) {
                $matchStatus = true;
                break;
            }
        }

        if (is_null($class) || !$matchStatus) {
            throw new \Exception('CLI `'.$commandName.'` Not Found!');
        }

        $class::handle($params);
    }

    private function keygen()
    {
        if (!empty(env('APP_KEY'))) {
            echo "APP_KEY not empty!\n";
            echo "Are you sure you want to replace?\n";
            echo "Type 'yes' to continue: \n";
            $handle = fopen ("php://stdin","r");
            $line = fgets($handle);
            if(trim($line) != 'yes'){
                echo "ABORTING!\n";
                exit;
            }
            fclose($handle);
        }

        $key = generateKey();

        $path = dirname(dirname(dirname(dirname(dirname(dirname(__DIR__)))))).'/.env';

        if (!is_file($path)) {
            throw new \Exception('key:generate can not found env: '.$path);
        }

        if (is_file($path)) {
            file_put_contents($path, str_replace(
                'APP_KEY='.env('APP_KEY'),
                'APP_KEY='.$key,
                file_get_contents($path)
            ));
        }

        echo "\n";
        echo "done! new key: ".$key."\n";
        exit();
    }
}