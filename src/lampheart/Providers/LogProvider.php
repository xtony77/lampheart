<?php

namespace lampheart\Providers;

use lampheart\Support\Http\Response;
use Monolog\Logger;
use lampheart\Support\Log;
use lampheart\Support\App;

class LogProvider
{
    public function __construct()
    {
        $this->errorHandler();

        $config = require_once dirname(dirname(dirname(dirname(__DIR__)))).'/config/logging.php';

        if (!file_exists($config)) {
            throw new \Exception('Log config not exist: '.$config);
        }

        $channelName = $config['default'];
        $channel = $config['channels'][$channelName];
        if (isset($channel['channels']))
        {
            $channels = $channel['channels'];
            foreach ($channels as $channel)
            {
                $this->setupChannel($channel);
            }
        }
        else
        {
            $this->setupChannel($channel);
        }
    }

    private function errorHandler()
    {
        set_exception_handler(function ($e) {

        });

        register_shutdown_function(function () {
            $error = error_get_last() ?: [];
            if (!empty($error))
            {
                Log::error('', $error);

                header("HTTP/1.0 500 Internal Server Error");
                if (env('APP_DEBUG') == true)
                {
                    echo Response::json($error);
                }
                else
                {
                    echo Response::json('500 Internal Server Error');
                }
                exit($error['type']);
            }
        });
    }

    private function setupChannel($channel)
    {
        switch ($channel['driver']) {
            case 'daily':
                $this->daily($channel);
                break;
            case 'amqp':
                $this->amqp($channel);
                break;
        }
    }

    private function daily($channel)
    {
        $handler = new \Monolog\Handler\RotatingFileHandler($channel['path'], $channel['days']);
        $handler->setFilenameFormat('{filename}-{date}', 'Y-m-d');

        $formatter = new \Monolog\Formatter\LineFormatter(null, null, true, true);
        $handler->setFormatter($formatter);

        $log = new Logger(env('APP_ENV'));
        $log->setHandlers([$handler]);

        App::set([
            'log' => [
                $log
            ]
        ]);
    }

    private function amqp($channel)
    {
        $exchange = (new \PhpAmqpLib\Connection\AMQPStreamConnection(
            env('RABBITMQ_HOST'),
            env('RABBITMQ_PORT'),
            env('RABBITMQ_USER'),
            env('RABBITMQ_PASS')
        ))->channel();
        $exchangeName = env('RABBITMQ_LOG_EXCHANGE');
        $level = \Monolog\Logger::INFO;

        $handler = new \Monolog\Handler\AmqpHandler($exchange, $exchangeName, $level);
        $handler->setFilenameFormat('{filename}-{date}', 'Y-m-d');

        $formatter = new \Monolog\Formatter\LineFormatter(null, null, true, true);
        $handler->setFormatter($formatter);

        $log = new Logger(env('APP_ENV'));
        $log->setHandlers([$handler]);

        App::set([
            'log' => [
                $log
            ]
        ]);
    }
}