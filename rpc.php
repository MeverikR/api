<?php
/**
 * Created by PhpStorm.
 * User: o.nasonov
 * Date: 14.06.2018
 * Time: 10:30
 *
 * Простой скриптец для обработки запросов JSOn-RPC.
 * По началу я хотел запилить полноценное API, но времени как всегда нет,
 * поэтому начнем скромненько
 *
 */

use Pimple\Container;
use JsonRPC\Server;
use Illuminate\Database\Capsule\Manager as Capsule;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

putenv('TZ=Europe/Moscow');
require 'vendor/autoload.php';


if (Config::$debug){
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set("log_errors", 1);
    ini_set("error_log", __DIR__.'/php_errors.log');

}
$request = Request::createFromGlobals();
$container = new Container(); // DIC нашего приложения. В нем все потом будет.
// корректируем путь приложения
$container['__path'] = __DIR__;

// логирование
$log = new Logger(Config::$logChannel);
$loggerTimeZone = new DateTimeZone('Europe/Moscow'); // зона логера
$logLevel = (int) Config::$logLevel;
$logHandler = new StreamHandler(__DIR__. DIRECTORY_SEPARATOR. Config::$logFile, $logLevel);
$logHandler->setFormatter(new \Monolog\Formatter\LineFormatter("|%channel%|[%datetime%][%level_name%] %message% %context%\n",
    'Y-m-d H:i:s'));
$log->setTimezone($loggerTimeZone );
$log->pushHandler($logHandler);
// добавим файловый логгер
$container['file_log'] = $log;
$container['file_log']->info('Получен запрос от [' . $request->getClientIp() . ']' , [$request->getContent()]);

// а теперь приступим к добавлению лога в БД
// БД-лог это у нас модель Log.
// Ее можно можно сразу создать, а потом тогда заполним сохраним
$container['action_log'] = new Log();
// заполним дополнительные служебные поля
$container['__request'] = $request->getContent();
$container['__request_method'] = $request->getMethod();
$container['__request_ip'] = $request->getClientIp();
$container['__request_client'] = $request->headers->get('User-Agent');
$container['__request_query'] = $request->getQueryString();
// все ок, но нам надо еще раз проверить что узер шлет нам правильный запрос

try {

    $capsule = new Capsule;
    $capsule->addConnection([
        'driver' => 'mysql',
        'host' => Config::$dbHost,
        'database' => Config::$dbBase,
        'username' => Config::$dbUser,
        'password' => Config::$dbPassword,
        'charset' => Config::$dbCharset,
        'collation' => Config::$dbCollation,
        'prefix' => Config::$dbPrefix,
        'time_zone' => 'Europe/Moscow'
    ]);
    $capsule->setAsGlobal();
    $capsule->bootEloquent();
} catch (Exception $e){
    $container['file_log']->error('Не удалось инициализировать слой ORM', [$e->getMessage()]);
}

try {
    $server = new Server();
} catch (Exception $e) {
    $container['file_log']->error('Не удалось инициализировать сервер RPC', [$e->getMessage()]);
}


/* очень просто и быстро опишем наши методы */
$server->getMiddlewareHandler()->withMiddleware(new AuthMiddleware($container));
$server->getProcedureHandler()->withObject(new ComagicAPI($container));
/* сервер может сразу сам отправить данные в браузер */
/* но теперь мы хотим залогировать ошибка в ответе или нет */
$answer = $server->execute();
$container['file_log']->info('Ответ сформирован', [$answer ]);

$logAnswer = json_decode($answer , true);
// какой результат
if (isset($logAnswer['error'])){
    $status = 'error';
    $mnemonic = $logAnswer['error']['message'];
} else {
    if (isset($logAnswer['result']['error'])){
        $status = 'error';
        $mnemonic = $logAnswer['result']['error']['mnemonic'] . ':' . $logAnswer['result']['error']['message'];
    } else {
        $status = 'success';
        $mnemonic = '';
    }
}


// здесь сразу логируем запрос в БД
// мы собрали всю нужную инху еще на этапе бутсрапа
// и пользователь залогировался стало быть можно записать

try {
    $container['action_log']->fill([
        'user' => (isset($container['active_user'])) ? $container['active_user']->id : -1,
        'ip' => ip2long($container['__request_ip']) , //
        'request' => $container['__request'],
        'method' => (isset($container['procName'])) ? $container['procName'] : 'unknown',
        'external_id' => '-1',
        'req_method' => $container['__request_method'],
        'status' => $status,
        'mnemonic' => $mnemonic,
        'ua' => $container['__request_client']
    ]);
    $container['action_log']->save();
} catch (Exception $e){
    $container['file_log']->error('Не удалось сохранить лог запроса. Ошибка работы с БД', [$e->getMessage()]);
}
echo $answer;