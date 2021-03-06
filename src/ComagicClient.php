<?php
/**
 * Created by PhpStorm.
 * User: o.nasonov
 * Date: 19.06.2018
 * Time: 13:34
 */

// класс для выполнения запросов к ЛК Comagic, просто получаем нужные нам данные
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\FileCookieJar;

class ComagicClient
{
    //const DT_FORMAT = 'Y-m-d H:i:s';
    //const CMGC_SESSION_TTL = 15;
    private $DT_FORMAT = 'Y-m-d H:i:s';
    private $CMGC_SESSION_TTL = 15;
    private $headers = [
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:61.0) Gecko/20100101 Firefox/61.0',
        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Encoding' => 'gzip, deflate, br',
        'Accept-Language' => 'ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
        'Connection' => 'keep-alive',
        'Host' => 'app.comagic.ru',
        'Referer' => 'https://www.comagic.ru/authorization/'
    ]; // это дефолтные заголовки
    private $base_uri = 'https://app.comagic.ru';
    private $timeout = 30.0;
    private $jar = null;
    private $client = null;
    private $__lastResponse = null;
    private $csrf = null;
    private $sso = null;
    private $storage = 'storage';
    private $login = null;

    public function __construct($login)
    {
        $this->client = new Client([
            'base_uri' => $this->base_uri,
            'timeout' => $this->timeout,
        ]);

        $this->login = $login;

        // задаим хранилище кук по пути и другие параметры
        if (Config::$DT_FORMAT) {
            $this->DT_FORMAT = Config::$DT_FORMAT;
        }
        if (Config::$CMGC_SESSION_TTL) {
            $this->CMGC_SESSION_TTL = Config::$CMGC_SESSION_TTL;
        }

        if (Config::$appPath) {
            $this->storage = Config::$appPath . $this->storage;
        }

        // зададим куку
        $this->jar = new FileCookieJar($this->storage . DIRECTORY_SEPARATOR . $login . '_cookie.txt', true);


    }

    public function getJarPath()
    {
        return $this->storage . DIRECTORY_SEPARATOR . $this->login . '_cookie.txt';
    }

    public function getAnalyticsMeta(DateTime $startDate, DateTime $endDate, $siteId)
    {
        if ($startDate > $endDate) {
            throw new Exception('parameter_value_error: date_from greater than date_till', -30227);
        }

        if (!is_numeric($siteId)) {
            throw new Exception('parameter_value_error: site_id not numeric', -30229);
        }

        $returnData = $this->getAnalyticsAll($startDate, $endDate, $siteId);
        if (!isset($returnData['data'])) {
            throw new Exception('parent_request_error: No data. System error', -30226);
        }
        if (!isset($returnData['success'])) {
            throw new Exception('parent_request_not_success: System error', -30225);
        }
        if (!$returnData['success']) {
            throw new Exception('parent_request_not_success: System error', -30224);
        }

        return $returnData['metaData']['grid'];

    }

    private function getAdsAll(DateTime $startDate, DateTime $endDate, $siteId, $campaign_id, $group_id)
    {
        $path = 'analytics/advsales/get_grid_data';
        $params = [
            'layer' => 'gr', // объявления
            'pc_type' => 'all',
            'start_date' => $startDate->format($this->DT_FORMAT),
            'end_date' => $endDate->format($this->DT_FORMAT),
            'compare_start_date' => '',
            'compare_end_date' => '',
            'isCompare' => false,
            'date_range' => json_encode([
                'start_date' => $startDate->format($this->DT_FORMAT),
                'end_date' => $endDate->format($this->DT_FORMAT),
                'compare_start_date' => '',
                'compare_end_date' => '',
                'interval' => 7
            ]),
            'site_id' => (int)$siteId,
            'reportType' => 'sales_ac',
            'aggregation' => 'day',
            'parameter' => json_encode(['session_count']),
            'layer_id'=>(int)$group_id,
            'ac_id'=>(int)$campaign_id,
            'extac_cost_ratio'=>'',
            'first_dimension'=>'ad',
            'filter' => json_encode([]),
            'checkFilterAtServer' => false,
            'page' => 1,
            'start' => 0,
            'limit' => 150,
            'sort' => json_encode([['property' => 'sessions_count', 'direction' => 'DESC']]),
            'csrf_token' => $this->csrf
        ];

        $this->setRefer('https://app.comagic.ru/');
        $response = $this->client->request('GET', $this->base_uri . '/' . $path . '/', [
            'query' => $params,
            'headers' => $this->headers,
            'cookies' => $this->jar
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    private function getAdsGroupsAll(DateTime $startDate, DateTime $endDate, $siteId, $campaign_id, $ac_id)
    {
        // точка входа в АНАЛитику
        $path = 'analytics/advsales/get_grid_data';
        $params = [
            'layer' => 'pc', // группы
            'pc_type' => 'all',
            'start_date' => $startDate->format($this->DT_FORMAT),
            'end_date' => $endDate->format($this->DT_FORMAT),
            'compare_start_date' => '',
            'compare_end_date' => '',
            'isCompare' => false,
            'date_range' => json_encode([
                'start_date' => $startDate->format($this->DT_FORMAT),
                'end_date' => $endDate->format($this->DT_FORMAT),
                'compare_start_date' => '',
                'compare_end_date' => '',
                'interval' => 7
            ]),
            'site_id' => (int)$siteId,
            'reportType' => 'sales_ac',
            'aggregation' => 'day',
            'parameter' => json_encode(['session_count']),
            'layer_id'=>(int)$ac_id,
            'ac_id'=>(int)$campaign_id,
            'extac_cost_ratio'=>'',
            'first_dimension'=>'gr',
            'filter' => json_encode([]),
            'checkFilterAtServer' => false,
            'page' => 1,
            'start' => 0,
            'limit' => 150,
            'sort' => json_encode([['property' => 'sessions_count', 'direction' => 'DESC']]),
            'csrf_token' => $this->csrf
        ];

        $this->setRefer('https://app.comagic.ru/');
        $response = $this->client->request('GET', $this->base_uri . '/' . $path . '/', [
            'query' => $params,
            'headers' => $this->headers,
            'cookies' => $this->jar
        ]);

        return json_decode($response->getBody()->getContents(), true);


    }

    private function getAnalyticsAllAC(DateTime $startDate, DateTime $endDate, $siteId, $campaign_id){
        // точка входа в АНАЛитику
        $path = 'analytics/advsales/get_grid_data';
        $params = [
            'layer' => 'ac',
            'pc_type' => 'all',
            'start_date' => $startDate->format($this->DT_FORMAT),
            'end_date' => $endDate->format($this->DT_FORMAT),
            'compare_start_date' => '',
            'compare_end_date' => '',
            'isCompare' => false,
            'date_range' => json_encode([
                'start_date' => $startDate->format($this->DT_FORMAT),
                'end_date' => $endDate->format($this->DT_FORMAT),
                'compare_start_date' => '',
                'compare_end_date' => '',
                'interval' => 7
            ]),
            'site_id' => (int)$siteId,
            'reportType' => 'sales_ac',
            'aggregation' => 'day',
            'parameter' => json_encode(['session_count']),
            'layer_id'=>$campaign_id,
            'ac_id'=>$campaign_id,
            'extac_cost_ratio'=>'',
            'first_dimension'=>'pc',
            'filter' => json_encode([]),
            'checkFilterAtServer' => false,
            'page' => 1,
            'start' => 0,
            'limit' => 150,
            'sort' => json_encode([['property' => 'sessions_count', 'direction' => 'DESC']]),
            'csrf_token' => $this->csrf
        ];
        $this->setRefer('https://app.comagic.ru/');
        $response = $this->client->request('GET', $this->base_uri . '/' . $path . '/', [
            'query' => $params,
            'headers' => $this->headers,
            'cookies' => $this->jar
        ]);

        return json_decode($response->getBody()->getContents(), true);

    }

    public function getAds(DateTime $startDate, DateTime $endDate, $siteId , $campaign_id , $group_id , $ad_id = null)
    {

        if ($startDate > $endDate) {
            throw new Exception('parameter_value_error: date_from greater than date_till', -30227);
        }

        if ((!is_numeric($siteId)) || (empty($siteId)) ) {
            throw new Exception('parameter_value_error: site_id not numeric', -30229);
        }

        if ((!is_numeric($campaign_id)) || (empty($campaign_id)) ) {
            throw new Exception('parameter_value_error: parameter campaign_id not numeric', -30230);
        }

        if ((!is_numeric($group_id)) || (empty($group_id)) ) {
            throw new Exception('parameter_value_error: parameter group_id not numeric', -30230);
        }


        // TODO: фильтрацию тут добавить через параметр
        // установим значения для фильтрации по умолчанию
        $settingsSetRes = $this->setAnalyticsGlobalSetting($siteId);
        if (!$settingsSetRes ){
            throw new Exception('analytics_user_settings_update_error: can`t set global settings', -30150);
        }

        $returnData = $this->getAdsAll($startDate, $endDate, $siteId, $campaign_id, $group_id);
        /* Стандартные проверки надо будет убрать  */
        if (!isset($returnData['data'])) {
            throw new Exception('parent_request_error: No data. System error', -30226);
        }
        if (!isset($returnData['success'])) {
            throw new Exception('parent_request_not_success: System error', -30225);
        }
        if (!$returnData['success']) {
            throw new Exception('parent_request_not_success: System error', -30224);
        }

        if ($ad_id){
            foreach ($returnData['data'] as $rk) {
                if ($rk['item_id'] == $ad_id) {
                    return ['data' => $rk, 'metadata' => ['total' => $returnData["metaData"]["total"]]];
                }
            }

        } else {
            return ['data' => $returnData['data'], 'metadata' => ['total' => $returnData["metaData"]["total"]]];
        }

    }
    public function getAdsGroups(DateTime $startDate, DateTime $endDate, $siteId , $campaign_id , $ac_id , $group_id = null )
    {
        if ($startDate > $endDate) {
            throw new Exception('parameter_value_error: date_from greater than date_till', -30227);
        }

        if ((!is_numeric($siteId)) || (empty($siteId)) ) {
            throw new Exception('parameter_value_error: site_id not numeric', -30229);
        }

        if ((!is_numeric($campaign_id)) || (empty($campaign_id)) ) {
            throw new Exception('parameter_value_error: parameter campaign_id not numeric', -30230);
        }

        if ((!is_numeric($ac_id)) || (empty($ac_id)) ) {
            throw new Exception('parameter_value_error: parameter ac_id not numeric', -30230);
        }

            // TODO: фильтрацию тут добавить через параметр
            // установим значения для фильтрации по умолчанию
        $settingsSetRes = $this->setAnalyticsGlobalSetting($siteId);
        if (!$settingsSetRes ){
            throw new Exception('analytics_user_settings_update_error: can`t set global settings', -30150);
        }

        $returnData = $this->getAdsGroupsAll($startDate, $endDate, $siteId, $campaign_id, $ac_id);
        /* Стандартные проверки надо будет убрать  */
        if (!isset($returnData['data'])) {
            throw new Exception('parent_request_error: No data. System error', -30226);
        }
        if (!isset($returnData['success'])) {
            throw new Exception('parent_request_not_success: System error', -30225);
        }
        if (!$returnData['success']) {
            throw new Exception('parent_request_not_success: System error', -30224);
        }

        if ($group_id){
            foreach ($returnData['data'] as $rk) {
                if ($rk['item_id'] == $group_id) {
                    return ['data' => $rk, 'metadata' => ['total' => $returnData["metaData"]["total"]]];
                }
            }

        } else {
            return ['data' => $returnData['data'], 'metadata' => ['total' => $returnData["metaData"]["total"]]];
        }


    }

    public function getAnalyticsAc (DateTime $startDate, DateTime $endDate, $siteId , $campaign_id , $ac_id = null )
    {
        if ($startDate > $endDate) {
            throw new Exception('parameter_value_error: date_from greater than date_till', -30227);
        }

        if ((!is_numeric($siteId)) || (empty($siteId)) ) {
            throw new Exception('parameter_value_error: site_id not numeric', -30229);
        }

        if ((!is_numeric($campaign_id)) || (empty($campaign_id)) ) {
            throw new Exception('parameter_value_error: parameter campaign_id not numeric', -30230);
        }


        // TODO: фильтрацию тут добавить через параметр
        // установим значения для фильтрации по умолчанию
        $settingsSetRes = $this->setAnalyticsGlobalSetting($siteId);
        if (!$settingsSetRes ){
            throw new Exception('analytics_user_settings_update_error: can`t set global settings', -30150);
        }

        $returnData = $this->getAnalyticsAllAC($startDate, $endDate, $siteId, $campaign_id);
        /* Стандартные проверки надо будет убрать  */
        if (!isset($returnData['data'])) {
            throw new Exception('parent_request_error: No data. System error', -30226);
        }
        if (!isset($returnData['success'])) {
            throw new Exception('parent_request_not_success: System error', -30225);
        }
        if (!$returnData['success']) {
            throw new Exception('parent_request_not_success: System error', -30224);
        }

        if ($ac_id){
            foreach ($returnData['data'] as $rk) {
                if ($rk['item_id'] == $ac_id) {
                    return ['data' => $rk, 'metadata' => ['total' => $returnData["metaData"]["total"]]];
                }
            }

        } else {
            return ['data' => $returnData['data'], 'metadata' => ['total' => $returnData["metaData"]["total"]]];
        }


    }

    private function getAnalyticsAll(DateTime $startDate, DateTime $endDate, $siteId)
    {

        // точка входа в АНАЛитику
        $path = 'analytics/advsales/get_grid_data';
        $params = [
            'layer' => 'main',
            'pc_type' => 'all',
            'start_date' => $startDate->format($this->DT_FORMAT),
            'end_date' => $endDate->format($this->DT_FORMAT),
            'compare_start_date' => '',
            'compare_end_date' => '',
            'isCompare' => false,
            'date_range' => json_encode([
                'start_date' => $startDate->format($this->DT_FORMAT),
                'end_date' => $endDate->format($this->DT_FORMAT),
                'compare_start_date' => '',
                'compare_end_date' => '',
                'interval' => 7
            ]),
            'site_id' => (int)$siteId,
            'reportType' => 'sales_ac',
            'aggregation' => 'day',
            'parameter' => json_encode(['session_count']),
            'filter' => json_encode([]),
            'checkFilterAtServer' => false,
            'page' => 1,
            'start' => 0,
            'limit' => 150,
            'sort' => json_encode([['property' => 'sessions_count', 'direction' => 'DESC']]),
            'csrf_token' => $this->csrf
        ];
        $this->setRefer('https://app.comagic.ru/');
        $response = $this->client->request('GET', $this->base_uri . '/' . $path . '/', [
            'query' => $params,
            'headers' => $this->headers,
            'cookies' => $this->jar
        ]);

        return json_decode($response->getBody()->getContents(), true);

    }

    public function setRefer($str)
    {
        $this->headers['Referer'] = trim($str);
        return $this;

    }

    public function getAnalyticsTotal(DateTime $startDate, DateTime $endDate, $siteId,  $settings = null)
    {

        if ($startDate > $endDate) {
            throw new Exception('parameter_value_error: date_from greater than date_till', -30227);
        }

        if (!is_numeric($siteId)) {

            throw new Exception('parameter_value_error: site_id not numeric', -30229);
        }
        // TODO: фильтрацию тут добавить через параметр
        if (is_array($settings) && (count($settings) > 0) )
        {
            // установим настройки полученные от пользователя
            $settingsSetRes = $this->setSpecifiedSettings($settings, $siteId);
            if (!$settingsSetRes ){

                throw new Exception('analytics_user_settings_update_error: can`t set USER settings', -30151);
            }

        } else {
            // установим значения для фильтрации по умолчанию
            $settingsSetRes = $this->setAnalyticsGlobalSetting($siteId);
            if (!$settingsSetRes ){
                throw new Exception('analytics_user_settings_update_error: can`t set global settings', -30150);
            }

        }

        $returnData = $this->getAnalyticsAll($startDate, $endDate, $siteId);
        if (!isset($returnData['summaryData'])) {
            throw new Exception('parent_request_error: No data. System error', -30226);
        }
        if (!isset($returnData['success'])) {
            throw new Exception('parent_request_not_success: System error', -30225);
        }
        if (!$returnData['success']) {
            throw new Exception('parent_request_not_success: System error', -30224);
        }



       return ['data' => $returnData['summaryData'], 'metadata' => ['total' => $returnData["metaData"]["total"]]];



    }

    // грабим компании
    public function getAnalytics(DateTime $startDate, DateTime $endDate, $siteId, $campaign_id = null, $is_ext = null, $settings = null)
    {
        if ($campaign_id && $is_ext) {
            throw new Exception('parameter_value_error: use only one campaign_id or is_ext', -30420);
        }

        if ($startDate > $endDate) {
            throw new Exception('parameter_value_error: date_from greater than date_till', -30227);
        }

        if (!is_numeric($siteId)) {
            throw new Exception('parameter_value_error: site_id not numeric', -30229);
        }
        // TODO: фильтрацию тут добавить через параметр
        if (is_array($settings) && (count($settings) > 0) )
        {
            // установим настройки полученные от пользователя
            $settingsSetRes = $this->setSpecifiedSettings($settings, $siteId);
            if (!$settingsSetRes ){
                throw new Exception('analytics_user_settings_update_error: can`t set USER settings', -30151);
            }

        } else {
            // установим значения для фильтрации по умолчанию
            $settingsSetRes = $this->setAnalyticsGlobalSetting($siteId);
            if (!$settingsSetRes ){
                throw new Exception('analytics_user_settings_update_error: can`t set global settings', -30150);
            }

        }

        $returnData = $this->getAnalyticsAll($startDate, $endDate, $siteId);
        if (!isset($returnData['data'])) {
            throw new Exception('parent_request_error: No data. System error', -30226);
        }
        if (!isset($returnData['success'])) {
            throw new Exception('parent_request_not_success: System error', -30225);
        }
        if (!$returnData['success']) {
            throw new Exception('parent_request_not_success: System error', -30224);
        }

        if ($campaign_id) {

            foreach ($returnData['data'] as $rk) {
                if ($rk['item_id'] == $campaign_id) {
                    return ['data' => $rk, 'metadata' => ['total' => $returnData["metaData"]["total"]]];
                }
            }

        } else {
            if ($is_ext != null) {
                $retDat = [];
                foreach ($returnData['data'] as $rk) {
                    if ($rk['is_ext'] == $is_ext) {
                        $retDat[] = $rk;
                    }
                }
                return ['data' => $retDat];

            } else {
                return ['data' => $returnData['data'], 'metadata' => ['total' => $returnData["metaData"]["total"]]];
            }

        }


    }

    // отдать массив сайтов

    public function getCampaigns(DateTime $startDate, DateTime $endDate, $siteId = null)
    {
        if ($startDate > $endDate) {
            throw new Exception('parameter_value_error: date_from greater than date_till', -30227);
        }
        // получаем список сайтов и РК по уму
        $path = 'analytics/review/review_grid/read';
        $params = [
            'date_range' => json_encode([
                'start_date' => $startDate->format($this->DT_FORMAT),
                'end_date' => $endDate->format($this->DT_FORMAT),
                'compare_start_date' => '',
                'compare_end_date' => '',
                'interval' => 7
            ]),
            'site_id' => -1,
            'isCompare' => false,
            'start_date' => $startDate->format($this->DT_FORMAT),
            'end_date' => $endDate->format($this->DT_FORMAT),
            'compare_start_date' => '',
            'compare_end_date' => '',
            'page' => 1,
            'start' => 0,
            'limit' => 150,
            'csrf_token' => $this->csrf
        ];
        $this->setRefer('https://app.comagic.ru/');
// https://app.comagic.ru/analytics/review/review_grid/read/?_dc=1529408175903&date_range=%7B%22start_date%22%3A%222018-06-13%2000%3A00%3A00%22%2C%22end_date%22%3A%222018-06-19%2023%3A59%3A59%22%2C%22compare_start_date%22%3A%22%22%2C%22compare_end_date%22%3A%22%22%2C%22interval%22%3A7%7D&site_id=-1&isCompare=false&start_date=2018-06-13%2000%3A00%3A00&end_date=2018-06-19%2023%3A59%3A59&compare_start_date=&compare_end_date=&page=1&start=0&limit=25&csrf_token=61G-3kAMiYv3nkFOiyEOUQ%3D%3D
        $response = $this->client->request('GET', $this->base_uri . '/' . $path . '/', [
            'query' => $params,
            'headers' => $this->headers,
            'cookies' => $this->jar
        ]);

        $returnData = json_decode($response->getBody()->getContents(), true);
        // не забываем что тут нам надо отдать юзеру сайты
        if (!isset($returnData['data'])) {
            throw new Exception('parent_request_error: No data. System error', -30226);
        }
        if (!isset($returnData['success'])) {
            throw new Exception('parent_request_not_success: System error', -30225);
        }
        if (!$returnData['success']) {
            throw new Exception('parent_request_not_success: System error', -30224);
        }
        $allSites = [];
        if ($siteId) {
            foreach ($returnData['data'] as $num => $site) {
                if (!isset($site['parameter_mnemonic'])) {
                    continue;
                }
                if (($site['parameter_mnemonic'] === 'ac') && $site['site_id'] == $siteId) {
                    $allSites[] = $site;
                }
            }
        } else {

            foreach ($returnData['data'] as $num => $site) {
                if (!isset($site['parameter_mnemonic'])) {
                    continue;
                }
                if ($site['parameter_mnemonic'] === 'ac') {
                    $allSites[] = $site;
                }
            }
        }

        if (count($allSites) <= 0) {
            if ($siteId) {
                throw new Exception('site_not_found: ' . 'campaigns for site with id ' . $siteId . ' not found', -30200);
            } else {
                throw new Exception('sites_empty: ' . 'campaigns not found. Empty data. ', -30201);
            }

        }

        return $allSites;
    }

    public function getSites(DateTime $startDate, DateTime $endDate, $siteId = null)
    {
        if ($startDate > $endDate) {
            throw new Exception('parameter_value_error: date_from greater than date_till', -30227);
        }
        // получаем список сайтов и РК по уму
        $path = 'analytics/review/review_grid/read';
        $params = [
            'date_range' => json_encode([
                'start_date' => $startDate->format($this->DT_FORMAT),
                'end_date' => $endDate->format($this->DT_FORMAT),
                'compare_start_date' => '',
                'compare_end_date' => '',
                'interval' => 7
            ]),
            'site_id' => -1,
            'isCompare' => false,
            'start_date' => $startDate->format($this->DT_FORMAT),
            'end_date' => $endDate->format($this->DT_FORMAT),
            'compare_start_date' => '',
            'compare_end_date' => '',
            'page' => 1,
            'start' => 0,
            'limit' => 150,
            'csrf_token' => $this->csrf
        ];
        $this->setRefer('https://app.comagic.ru/');
// https://app.comagic.ru/analytics/review/review_grid/read/?_dc=1529408175903&date_range=%7B%22start_date%22%3A%222018-06-13%2000%3A00%3A00%22%2C%22end_date%22%3A%222018-06-19%2023%3A59%3A59%22%2C%22compare_start_date%22%3A%22%22%2C%22compare_end_date%22%3A%22%22%2C%22interval%22%3A7%7D&site_id=-1&isCompare=false&start_date=2018-06-13%2000%3A00%3A00&end_date=2018-06-19%2023%3A59%3A59&compare_start_date=&compare_end_date=&page=1&start=0&limit=25&csrf_token=61G-3kAMiYv3nkFOiyEOUQ%3D%3D
        $response = $this->client->request('GET', $this->base_uri . '/' . $path . '/', [
            'query' => $params,
            'headers' => $this->headers,
            'cookies' => $this->jar
        ]);

        $returnData = json_decode($response->getBody()->getContents(), true);
        // не забываем что тут нам надо отдать юзеру сайты
        if (!isset($returnData['data'])) {
            throw new Exception('parent_request_error: No data. System error', -30226);
        }
        if (!isset($returnData['success'])) {
            throw new Exception('parent_request_not_success: System error', -30225);
        }
        if (!$returnData['success']) {
            throw new Exception('parent_request_not_success: System error', -30224);

        }
        $allSites = [];
        if ($siteId) {
            foreach ($returnData['data'] as $num => $site) {
                if (!isset($site['parameter_mnemonic'])) {
                    continue;
                }
                if (($site['parameter_mnemonic'] === 'site') && $site['site_id'] == $siteId) {
                    $allSites[] = $site;
                }
            }
        } else {

            foreach ($returnData['data'] as $num => $site) {
                if (!isset($site['parameter_mnemonic'])) {
                    continue;
                }
                if ($site['parameter_mnemonic'] === 'site') {
                    $allSites[] = $site;
                }
            }
        }

        if (count($allSites) <= 0) {
            if ($siteId) {
                throw new Exception('site_not_found: ' . 'campaigns for site with id ' . $siteId . ' not found', -30200);
            } else {
                throw new Exception('sites_empty: ' . 'campaigns not found. Empty data. ', -30201);
            }

        }

        return $allSites;

    }

    public function auth($login, $password)
    {
        // TODO: если есть кука с логином и не прошло 15 минут, то заново логинетсо не надо
        if (file_exists($this->storage . DIRECTORY_SEPARATOR . $login . '_cookie.txt')) {
            $cookieTime = filemtime($this->storage . DIRECTORY_SEPARATOR . $login . '_cookie.txt');
            $cookieDateTime = new DateTime();
            $cookieDateTime->setTimestamp($cookieTime);
            $cookieDateTime->setTimezone(new DateTimeZone('Europe/Moscow'));
            $now = new DateTime('now', new DateTimeZone('Europe/Moscow'));
            $diff = $cookieDateTime->diff($now);
            $ds = $diff->days;
            $diffMins = ($ds * 24) * 60;
            $diffMins += $diff->h * 60;
            $diffMins += $diff->i;
            if ($diffMins <= $this->CMGC_SESSION_TTL) {
                $this->jar->load($this->storage . DIRECTORY_SEPARATOR . $login . '_cookie.txt');
                if ($this->getTokens() !== false) {
                    return true;
                } else {
                    return false;
                }
            }
        }

        $this->setRefer('https://www.comagic.ru/authorization/');
        $response = $this->client->request('POST', '/login/', [
            'form_params' => [
                'email' => $login,
                'password' => $password
            ],
            'cookies' => $this->jar,
            'headers' => $this->headers
        ]);
        $this->__lastResponse = $response;

        if ($response->getStatusCode() != '200') {
            return false;
        }
        if (file_exists($this->storage . DIRECTORY_SEPARATOR . $login . '_cookie.txt')) {
            @unlink($this->storage . DIRECTORY_SEPARATOR . $login . '_cookie.txt');
        }

        $this->jar->save($this->storage . DIRECTORY_SEPARATOR . $login . '_cookie.txt');
        $this->jar->load($this->storage . DIRECTORY_SEPARATOR . $login . '_cookie.txt');

        // если все ок, делаем еще один запрос, чтоб получить токены
        if ($this->getTokens() !== false) {
            return true;
        } else {
            return false;
        }
    }

    private function getTokens()
    {

        $response = $this->client->request('GET', $this->base_uri, [
            'cookies' => $this->jar,
            'headers' => $this->headers
        ]);
        $this->__lastResponse = $response;

        $body = $response->getBody()->getContents();


        if (preg_match('/csrf_token:\s\'([A-Za-z0-9\-\=_]{2,})\'/s', $body, $regs)) {
            $crsf_token = $regs[1];
        } else {
            $crsf_token = false;
        }

        if (preg_match('/ssoToken:\s\'(.*)\'/', $body, $regs)) {
            $sso_token = $regs[1];
        } else {
            $sso_token = false;
        }

        if ($crsf_token === false || $sso_token === false) {

            return false; // не удалось авторизоватсо
        }

        // сохраним наши токены
        $this->csrf = $crsf_token;
        $this->sso = $sso_token;

        // сохраним куку

        return $this;
    }

    public function seeTokens()
    {
        return ['csrf' => $this->csrf, 'sso' => $this->sso];
    }

    private function setSpecifiedSettings(array $settings, $site_id){
        // defaults

        if ((!is_numeric($site_id)) || (empty($site_id)) ){
            return false;
        }

        if (is_array($settings) && (count($settings) > 0) ){
            $params = [
                'csrf_token' => $this->csrf,
                'goals' =>  ((isset($settings['goals'])) ? $settings['goals'] :  '' ),
                'include_calls' => ((isset($settings['include_calls'])) ? $settings['include_calls'] :  'true' ),
                'include_chats' => ((isset($settings['include_chats'])) ? $settings['include_chats'] :  'true' ),
                'include_orders' => ((isset($settings['include_orders'])) ? $settings['include_orders'] :  'true' ),
                'include_only_first_contacts' => ((isset($settings['include_only_first_contacts'])) ? $settings['include_only_first_contacts'] :  'false' ),
                'include_only_first_good_contacts' =>  ((isset($settings['include_only_first_good_contacts'])) ? $settings['include_only_first_good_contacts'] :  'false' ),
                'include_only_good_contacts' => ((isset($settings['include_only_good_contacts'])) ? $settings['include_only_good_contacts'] :  'false' ),
                'include_only_through_first_good_contacts' => ((isset($settings['include_only_through_first_good_contacts'])) ? $settings['include_only_through_first_good_contacts'] :  'false' ),
                'sales_ac_columns' => 'cpa,communications_types_cost,goals_map_cost,cost_part,shows,premium_shows,clicks,ctr,block_str,cpc,limits,limits_part,balance,quality_score,shows_average_position,clicks_average_position,sessions_count,cpv,session_time,waste_sessions,pages_per_session,new_sessions,communications_count,communications_types,goals_map_count,communications_sorts,not_goods_communications,goods_count,sales_count,before_sale_days,client_cost,communications_to_sales,cr_types,revenue,avg_revenue,roi'
            ];
        } else {
            $params = [
                'csrf_token' => $this->csrf,
                'goals' => '',
                'include_calls' => 'true',
                'include_chats' => 'true',
                'include_orders' => 'true',
                'include_only_first_contacts' => 'false',
                'include_only_first_good_contacts' => 'false',
                'include_only_good_contacts' => 'false',
                'include_only_through_first_good_contacts' => 'false',
                'sales_ac_columns' => 'cpa,communications_types_cost,goals_map_cost,cost_part,shows,premium_shows,clicks,ctr,block_str,cpc,limits,limits_part,balance,quality_score,shows_average_position,clicks_average_position,sessions_count,cpv,session_time,waste_sessions,pages_per_session,new_sessions,communications_count,communications_types,goals_map_count,communications_sorts,not_goods_communications,goods_count,sales_count,before_sale_days,client_cost,communications_to_sales,cr_types,revenue,avg_revenue,roi'
            ];
        }


        foreach ($settings as $setName => $setValue){
            if ($setName != 'sales_ac_columns') {
                if ( isset($params[$setName]) ){
                    $params[$setName] = $setValue ? true : false;
                }
            }

        }
        $path = 'analytics/advsales/settings/update';
        $this->setRefer('https://app.comagic.ru/');

        $response = $this->client->request('POST', $this->base_uri . '/' . $path . '/?site_id='.(int)$site_id , [
            'form_params' => $params,
            'headers' => $this->headers,
            'cookies' => $this->jar
        ]);

        if ($response->getStatusCode() != '200') {
            return false;
        }

        $body = json_decode($response->getBody()->getContents(), true);

        if (isset($body ['success'])) {
            return $body ['success'];
        } else {
            return false;
        }

    }

    private function setAnalyticsGlobalSetting(
        $site_id,
        $calls = 'true',
        $chats = 'true',
        $orders = 'true',
        $first_contacts = 'false',
        $first_good_contacts = 'false',
        $good_contacts = 'false',
        $through_first_good_contacts = 'false'
    ) {

        if ((!is_numeric($site_id)) || (empty($site_id)) ){
            return false;
        }

        $path = 'analytics/advsales/settings/update';
        $params = [
            'csrf_token' => $this->csrf,
            'goals' => '',
            'include_calls' => $calls,
            'include_chats' => $chats,
            'include_orders' => $orders,
            'include_only_first_contacts' => $first_contacts,
            'include_only_first_good_contacts' => $first_good_contacts,
            'include_only_good_contacts' => $good_contacts,
            'include_only_through_first_good_contacts' => $through_first_good_contacts,
            'sales_ac_columns' => 'cpa,communications_types_cost,goals_map_cost,cost_part,shows,premium_shows,clicks,ctr,block_str,cpc,limits,limits_part,balance,quality_score,shows_average_position,clicks_average_position,sessions_count,cpv,session_time,waste_sessions,pages_per_session,new_sessions,communications_count,communications_types,goals_map_count,communications_sorts,not_goods_communications,goods_count,sales_count,before_sale_days,client_cost,communications_to_sales,cr_types,revenue,avg_revenue,roi'
        ];


        $this->setRefer('https://app.comagic.ru/');

        $response = $this->client->request('POST', $this->base_uri . '/' . $path . '/?layer=main&first_dimension=&site_id='.(int)$site_id , [
            'form_params' => $params,
            'headers' => $this->headers,
            'cookies' => $this->jar
        ]);

        if ($response->getStatusCode() != '200') {
            return false;
        }

        $body = json_decode($response->getBody()->getContents(), true);

        if (isset($body ['success'])) {
            return $body ['success'];
        } else {
            return false;
        }

    }


}