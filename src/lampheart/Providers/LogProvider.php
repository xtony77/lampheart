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

        $path = dirname(dirname(dirname(dirname(dirname(dirname(__DIR__)))))).'/config/logging.php';

        if (!is_file($path)) {
            throw new \Exception('Log config not exist: '.$path);
        }

        $config = require_once $path;

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
        set_error_handler(function ($code, $msg, $file, $line) {
            throw new \Exception($msg);
        });

        set_exception_handler(function ($e) {
            Log::error('', [$e->getMessage()]);

            http_response_code($e->getCode());

            echo Response::json([
                $e->getCode(),
                $e->getMessage(),
                $e->getTrace()
            ]);
            exit();
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

        if (!empty(env('SENTRY_DSN')) && env('APP_ENV') !== 'local') {
            \Sentry\init(['dsn' => env('SENTRY_DSN')]);
        }
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