<?php
/**
 * Created by PhpStorm.
 * User: o.nasonov
 * Date: 19.06.2018
 * Time: 12:15
 *
 * Класс описывает все возможные методы нашего микро*апи
 */

class ComagicAPI
{

    private $registry = null;
    private $startMinitime;

    public function __construct(Pimple\Container $registry)
    {
        $this->registry = $registry;
        $this->startMinitime = microtime(true);
    }

    /**
     * Проверка связи
     * @return array
     */
    public function ping()
    {
        return ['data' => 'pong', 'time' => microtime(true), 'ver' => Config::$VER, 'build' => Config::$B_VER];
    }

    public function getAnalyticsMeta($date_from, $date_till, $site_id )
    {
        if ($this->checkUser()) {


            try {
                $date_from = new DateTime($date_from, new DateTimeZone('Europe/Moscow'));
                $date_till = new DateTime($date_till, new DateTimeZone('Europe/Moscow'));
            } catch (Exception $e){
                $this->registry['file_log']->error('Ошибка обработки даты' , [$e->getMessage()]);
                throw new Exception('parameter_value_error: date_from or date_till value malformated');
            }

            $anal = $this->registry['cmgc_client']->getAnalyticsMeta($date_from, $date_till, $site_id);
            return ['data' => $anal, 'metadata' => $this->getMeta($anal)];


        } else {
            $this->registry['file_log']->error('Ошибка проверки пользовательской сессии' , []);
            throw new JsonRPC\Exception\AuthenticationFailureException('User not found');
        }

    }

    public function getAds($date_from, $date_till, $site_id , $campaign_id , $group_id, $ad_id=null)
    {
        if ($this->checkUser()) {

            try {
                $date_from = new DateTime($date_from, new DateTimeZone('Europe/Moscow'));
                $date_till = new DateTime($date_till, new DateTimeZone('Europe/Moscow'));
            } catch (Exception $e){
                $this->registry['file_log']->error('Ошибка обработки даты' , [$e->getMessage()]);
                throw new Exception('parameter_value_error: date_from or date_till value malformated');
            }

            $anal = $this->registry['cmgc_client']->getAds($date_from, $date_till, $site_id, $campaign_id, $group_id, $ad_id );
            return $this->updateMeta($anal ?? ['data' => []]);


        } else {
            $this->registry['file_log']->error('Ошибка проверки пользовательской сессии' , []);
            throw new JsonRPC\Exception\AuthenticationFailureException('User not found');
        }
    }

    public function getAdsGroups($date_from, $date_till, $site_id , $campaign_id , $ac_id, $group_id=null )
    {
        if ($this->checkUser()) {

            try {
                $date_from = new DateTime($date_from, new DateTimeZone('Europe/Moscow'));
                $date_till = new DateTime($date_till, new DateTimeZone('Europe/Moscow'));
            } catch (Exception $e){
                $this->registry['file_log']->error('Ошибка обработки даты' , [$e->getMessage()]);
                throw new Exception('parameter_value_error: date_from or date_till value malformated');
            }

            $anal = $this->registry['cmgc_client']->getAdsGroups($date_from, $date_till, $site_id, $campaign_id, $ac_id, $group_id );
            return $this->updateMeta($anal ?? ['data' => []]);


        } else {
            $this->registry['file_log']->error('Ошибка проверки пользовательской сессии' , []);
            throw new JsonRPC\Exception\AuthenticationFailureException('User not found');
        }

    }

    public function getAnalyticsAc($date_from, $date_till, $site_id , $campaign_id , $ac_id = null ){
        if ($this->checkUser()) {

            try {
                $date_from = new DateTime($date_from, new DateTimeZone('Europe/Moscow'));
                $date_till = new DateTime($date_till, new DateTimeZone('Europe/Moscow'));
            } catch (Exception $e){
                $this->registry['file_log']->error('Ошибка обработки даты' , [$e->getMessage()]);
                throw new Exception('parameter_value_error: date_from or date_till value malformated');
            }

            $anal = $this->registry['cmgc_client']->getAnalyticsAc($date_from, $date_till, $site_id, $campaign_id, $ac_id );
			
			
            return $this->updateMeta($anal ?? ['data' => []]);


        } else {
            $this->registry['file_log']->error('Ошибка проверки пользовательской сессии' , []);
            throw new JsonRPC\Exception\AuthenticationFailureException('User not found');
        }

    }

    public function getAnalyticsTotal($date_from, $date_till, $site_id , $campaign_id = null, $is_ext = null, $fields = null, $settings = null)
    {
        if ($this->checkUser()) {

            try {
                $date_from = new DateTime($date_from, new DateTimeZone('Europe/Moscow'));
                $date_till = new DateTime($date_till, new DateTimeZone('Europe/Moscow'));
            } catch (Exception $e){
                $this->registry['file_log']->error('Ошибка обработки даты' , [$e->getMessage()]);
                throw new Exception('parameter_value_error: date_from or date_till value malformated');
            }

            $anal = $this->registry['cmgc_client']->getAnalyticsTotal($date_from, $date_till, $site_id, $settings );


            if (is_array($fields) && isset($anal['data'] )){
                $fieldedData = [];
                $an = $anal['data'];
           //     foreach ($anal['data'] as $an){
                    $s_tmp = [];
                    foreach ($fields as $fld){
                        if (isset($an[$fld])){
                            $s_tmp[$fld] = $an[$fld];
                        } else {
                            $s_tmp[$fld] = 0;
                        }
                    }
                    $fieldedData = $s_tmp;
             //   }

                return $this->updateMeta(['data' => $fieldedData, 'metadata' => $anal['metadata']]);
            } else {
                return $this->updateMeta($anal ?? ['data' => []]);
            }




        } else {
            $this->registry['file_log']->error('Ошибка проверки пользовательской сессии' , []);
            throw new JsonRPC\Exception\AuthenticationFailureException('User not found');
        }
    }

    public function getAnalytics($date_from, $date_till, $site_id , $campaign_id = null, $is_ext = null, $fields = null, $settings = null )
    {
        if ($this->checkUser()) {

                try {
                    $date_from = new DateTime($date_from, new DateTimeZone('Europe/Moscow'));
                    $date_till = new DateTime($date_till, new DateTimeZone('Europe/Moscow'));
                } catch (Exception $e){
                    $this->registry['file_log']->error('Ошибка обработки даты' , [$e->getMessage()]);
                    throw new Exception('parameter_value_error: date_from or date_till value malformated');
                }


            $anal = $this->registry['cmgc_client']->getAnalytics($date_from, $date_till, $site_id, $campaign_id, $is_ext, $settings );


            if (is_array($fields) && isset($anal['data'] )){
                $fieldedData = [];

                foreach ($anal['data'] as $an){
                    $s_tmp = [];
                    foreach ($fields as $fld){
                        if (isset($an[$fld])){
                            $s_tmp[$fld] = $an[$fld];
                        } else {
                            $s_tmp[$fld] = 0;
                        }

                    }
                    $fieldedData[] = $s_tmp;
                }

                return $this->updateMeta(['data' => $fieldedData, 'metadata' => $anal['metadata']]);
            } else {
                return $this->updateMeta($anal ?? ['data' => []]);
            }




        } else {
            $this->registry['file_log']->error('Ошибка проверки пользовательской сессии' , []);
            throw new JsonRPC\Exception\AuthenticationFailureException('User not found');
        }


    }

    /** Получить РК клиента по сайту или скопом с минимальным набором полей параметров
     * @param $date_from
     * @param $date_till
     * @param null $site_id
     * @return array
     * @throws \JsonRPC\Exception\AuthenticationFailureException
     */
    public function getCampaigns($date_from, $date_till, $site_id = null)
    {
        if ($this->checkUser()) {


            try {
                $date_from = new DateTime($date_from, new DateTimeZone('Europe/Moscow'));
                $date_till = new DateTime($date_till, new DateTimeZone('Europe/Moscow'));
            } catch (Exception $e){
                $this->registry['file_log']->error('Ошибка обработки даты' , [$e->getMessage()]);
                throw new Exception('parameter_value_error: date_from or date_till value malformated');
            }

            if ($site_id) {
                $sites = $this->registry['cmgc_client']->getCampaigns($date_from, $date_till, $site_id);
                return ['data' => $sites, 'metadata' => $this->getMeta($sites)];
            } else {
                $sites = $this->registry['cmgc_client']->getCampaigns($date_from, $date_till);
                return ['data' => $sites, 'metadata' => $this->getMeta($sites)];
            }


        } else {
            $this->registry['file_log']->error('Ошибка проверки пользовательской сессии' , []);
            throw new JsonRPC\Exception\AuthenticationFailureException('User not found');
        }


    }


    /** Получить список сайтов клиента
     * @param $date_from
     * @param $date_till
     * @return array
     * @throws \JsonRPC\Exception\AuthenticationFailureException
     */
    public function getSites($date_from, $date_till, $fields = null)
    {

        if ($this->checkUser()) {


            try {
                $date_from = new DateTime($date_from, new DateTimeZone('Europe/Moscow'));
                $date_till = new DateTime($date_till, new DateTimeZone('Europe/Moscow'));
            } catch (Exception $e){
                $this->registry['file_log']->error('Ошибка обработки даты' , [$e->getMessage()]);
                throw new Exception('parameter_value_error: date_from or date_till value malformated');
            }

            $sites = $this->registry['cmgc_client']->getSites($date_from, $date_till);
            if (is_array($fields)){
                $fieldedData = [];
                foreach ($sites as $site){
                    $s_tmp = [];
                    foreach ($fields as $fld){
                        if (isset($site[$fld])){
                            $s_tmp[$fld] = $site[$fld];
                        }

                    }
                    $fieldedData[] = $s_tmp;
                }

                return ['data' => $fieldedData, 'metadata' => $this->getMeta($fieldedData)];
            } else {
                return ['data' => $sites, 'metadata' => $this->getMeta($sites)];
            }



        } else {
            $this->registry['file_log']->error('Ошибка проверки пользовательской сессии' , []);
            throw new JsonRPC\Exception\AuthenticationFailureException('User not found');
        }

    }

    /** Получить конкретный сайтец
     * @param $date_from
     * @param $date_till
     * @param $site_id
     * @param null $fields
     * @return array
     * @throws \JsonRPC\Exception\AuthenticationFailureException
     */
    public function getSite($date_from, $date_till, $site_id, $fields = null)
    {
        if ($this->checkUser()) {


            try {
                $date_from = new DateTime($date_from, new DateTimeZone('Europe/Moscow'));
                $date_till = new DateTime($date_till, new DateTimeZone('Europe/Moscow'));
            } catch (Exception $e){
                $this->registry['file_log']->error('Ошибка обработки даты' , [$e->getMessage()]);
                throw new Exception('parameter_value_error: date_from or date_till value malformated');
            }
            $sites = $this->registry['cmgc_client']->getSites($date_from, $date_till, $site_id);

            if (is_array($fields)){
                $fieldedData = [];
                foreach ($sites as $site){
                    $s_tmp = [];
                    foreach ($fields as $fld){
                        if (isset($site[$fld])){
                            $s_tmp[$fld] = $site[$fld];
                        }

                    }
                    $fieldedData[] = $s_tmp;
                }

                return ['data' => $fieldedData, 'metadata' => $this->getMeta($fieldedData)];
            } else {
                return ['data' => $sites, 'metadata' => $this->getMeta($sites)];
            }

        } else {
            $this->registry['file_log']->error('Ошибка проверки пользовательской сессии' , []);
            throw new JsonRPC\Exception\AuthenticationFailureException('User not found');
        }
    }


    private function checkUser()
    {
        // проверим что пользователь есть активный
        $user = $this->registry['active_user'];
        if (($user instanceof User) && ($user->id > 0)) {
            return true;
        } else {
            return false;
        }
    }

    /** Метод просто добавляет некоторые параметры в существующую мету
     * @param array $arr
     */
    private function updateMeta(array $arr)
    {
        if (isset($arr['error'])){
            return $arr;
        }

        if ( (isset($arr['metadata'])) && (!isset($arr['metadata']['total_time']))){

            $arr['metadata']['total_time'] = microtime(true) - $this->startMinitime;
        }

        if ( (isset($arr['metadata'])) && (!isset($arr['metadata']['total']))){

            $arr['metadata']['total'] = count($arr)-1;
        }

        if (!isset($arr['metadata'])){
            $arr['metadata']['total_time'] = microtime(true) - $this->startMinitime;
			if (isset($arr['data'])){
				if (empty($arr['data'])){
					$arr['metadata']['total'] = 0;
				}else {
					$arr['metadata']['total'] = count($arr);
				}
			} else {
			$arr['metadata']['total'] = count($arr);	
			}
            

        }


        return $arr;
    }

    private function getMeta(array $arr)
    {
        // по массивув надо посчитать количество
        $total_time_secs = microtime(true) - $this->startMinitime;
        $total = count($arr);
        return ['total' => $total, 'total_time' => $total_time_secs];

    }

}