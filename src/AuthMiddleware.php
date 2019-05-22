<?php
/**
 * Created by PhpStorm.
 * User: o.nasonov
 * Date: 19.06.2018
 * Time: 17:03
 */


use JsonRPC\MiddlewareInterface;
use JsonRPC\Exception\AuthenticationFailureException;
use Illuminate\Database\Capsule\Manager as DB;

class AuthMiddleware implements MiddlewareInterface
{
    private $registry = null;

    public function __construct(Pimple\Container $container)
    {
        $this->registry = $container;

    }

    public function execute($username, $password, $procedureName)
    {
        $this->registry['file_log']->info('Используем метод API' , [$procedureName]);
        $this->registry['file_log']->info('Попытка авторизации' , [$username, $password]);
        $this->registry['procName'] = $procedureName;
        $authUser  =  User::where(['login' => $username, 'password' => $password])->first();


        if ($authUser instanceof User ) {
            $this->registry['file_log']->info('Пользователь найден' , [$authUser->id]);

            if ((!$authUser->id) || (false == $authUser->id)){
                $this->registry['file_log']->error('Пользователь найден, но пуст. Это какой-то бред.' , [$authUser]);
                throw new AuthenticationFailureException('Wrong credentials!');

            }
            else {
                $this->registry['file_log']->info('Вход успешен. Пользователь определен.' , [$authUser->id]);
                // прежде чем все это намутить, проверим с какого ip чувак выполняет запрос
                $userIps = array_flip($authUser->ipsList()) ;
                $this->registry['file_log']->info('Доступ разрешен только с IP' , [$userIps ]);
                if (!isset($userIps[ $this->registry['__request_ip']])){
                    $this->registry['file_log']->error('IP адрес запроса заблокирован' , [$this->registry['__request_ip'], $userIps]);
                    throw new Exception('Your ip address ' .  $this->registry['__request_ip'] . ' is not whitelisted', 403);
                }
                // и если все окай, проверим лимиты:
                // МИНУТНЫЕ
                $this->registry['file_log']->info('Определяем временные лимиты' , []);
                try {
                    $gx = DB::select(DB::raw('SELECT COUNT(id) AS cnt FROM `logs` WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE) AND user = '.$authUser->id.';'));
                } catch (Exception $e){
                    $this->registry['file_log']->error('Не удалось получить минутные лимиты из настроек пользователя' , [$e->getMessage()]);
                    throw new Exception('system limit overflow error: contact support', 500);
                }

                if (!isset($gx[0])){
                    $this->registry['file_log']->error('Не удалось получить минутные лимиты из настроек пользователя' , [$gx]);
                    throw new Exception('system limit overflow error: contact support', 500);
                }

                 $curMin = (int) $gx[0]->cnt;
                // смотрим сколько стоит у пользователя
                 $curUserMin = (int) $authUser->min_limit;
                $this->registry['file_log']->info('Текущие запросы в минуту' , [$curMin , $curUserMin ]);

                 if ($curMin > $curUserMin ){
                     $this->registry['file_log']->error('Доступ запрещен. Минутный лимит израсходован' , [$curUserMin -$curMin ]);
                     throw new Exception('Limit per minute has been exceeded. Value of current limit per minute is ' . $curUserMin , -32029);
                 }
                 unset($gx);

                 // ДНЕВНЫЕ
                try {
                    $gx = DB::select(DB::raw('SELECT COUNT(id) AS cnt FROM `logs` WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 DAY) AND user = '.$authUser->id.';'));
                } catch (Exception $e){
                    $this->registry['file_log']->error('Не удалось получить дневные лимиты из настроек пользователя' , [$e->getMessage()]);
                    throw new Exception('system limit overflow error: contact support', 500);
                }

                if (!isset($gx[0])){
                    $this->registry['file_log']->error('Не удалось получить дневные лимиты из настроек пользователя' , [$gx]);
                    throw new Exception('system limit overflow error: contact support', 500);
                }

                $curDay = (int) $gx[0]->cnt;
                // смотрим дневные у пользователя
                $curUserDay = (int) $authUser->day_limit;
                if ($curDay > $curUserDay ){
                    $this->registry['file_log']->error('Доступ запрещен. Дневной лимит израсходован' , [$curUserDay - $curDay ]);
                    throw new Exception('Limit per day has been exceeded. Value of current limit per day is ' . $curUserDay , -32029);
                }


                $this->registry['active_user'] = $authUser;
                $this->registry['is_auth'] = true;
                $this->registry['is_cmgc_auth'] = false;
                $this->registry['cmgc_client']  = new ComagicClient($authUser->login);
                try {
                    $authResult = $this->registry['cmgc_client']->auth($authUser->login, $authUser->password);
                } catch (Exception $e){
                    $this->registry['file_log']->error('Ошибка авторизации в CoMagic.' , [$e->getMessage()]);
                }
                if ($authResult){
                    $this->registry['is_cmgc_auth'] = $authResult;
                    $this->registry['tokens'] = $this->registry['cmgc_client']->seeTokens();
                    $this->registry['file_log']->info('Установлены токены клиента' , [$this->registry['tokens']]);
                    // запилим тут обновление данных пользователя
                    $this->registry['active_user']->last_login = (new DateTime('now', new DateTimeZone('Europe/Moscow')))->format('Y-m-d H:i:s');
                    $this->registry['active_user']->last_csrf = $this->registry['tokens']['csrf'];
                    $this->registry['active_user']->last_sso = $this->registry['tokens']['sso'];
                    $this->registry['active_user']->last_jar = $this->registry['cmgc_client']->getJarPath();
                    try {
                        $this->registry['active_user']->save(); // сохраним поля пользователя
                    } catch (Exception $e){
                        $this->registry['file_log']->error('Ошибка обновления данных сесии клиента' , [$e->getMessage()]);
                    }



                } else {
                    $this->registry['file_log']->error('Ошибка авторизации в CoMagic. Guzzle error.' , []);
                    throw new AuthenticationFailureException('Parent cant login! System error.');
                }

            }
        } else {
            $this->registry['file_log']->error('Пользователь с такими логином и паролем не найден' , [$username, $password]);
            throw new AuthenticationFailureException('Wrong credentials!');
        }



    }
}