<?php
/**
 * Created by PhpStorm.
 * User: o.nasonov
 * Date: 07.06.2018
 * Time: 16:11
 */


require 'vendor/autoload.php';

use GuzzleHttp\Client;

$headers = [
    'headers' => [
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:61.0) Gecko/20100101 Firefox/61.0',
        'Accept'     => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Encoding'      => 'gzip, deflate, br',
        'Accept-Language'      => 'ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
        'Connection' => 'keep-alive',
        'Host' => 'app.comagic.ru',
        'Referer' =>	'https://www.comagic.ru/authorization/'
    ]
];

$c1 = new Client([
    // Base URI is used with relative requests
    'base_uri' => 'https://app.comagic.ru',
    // You can set any number of default request options.
    'timeout'  => 5.0,
]);
$jar = new \GuzzleHttp\Cookie\CookieJar();
echo 'Логинемся' , PHP_EOL;
// логинемся
$resp = $c1->request('POST', '/login/', [
    'form_params' => [
        'email' => 'testlk',
        'password' => 'Logrus247'
    ],
    'cookies' => $jar,
    'headers' => [
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:61.0) Gecko/20100101 Firefox/61.0',
        'Accept'     => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Encoding'      => 'gzip, deflate, br',
        'Accept-Language'      => 'ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
        'Connection' => 'keep-alive',
        'Host' => 'app.comagic.ru',
        'Referer' =>	'https://www.comagic.ru/authorization/'
    ]
]);

echo 'CODE: ', $resp->getStatusCode() , PHP_EOL;


// грабим CRSF токен

$resp = $c1->request('GET', 'https://app.comagic.ru/',  array_merge(['cookies' => $jar],$headers));

$body = $resp->getBody()->getContents();

if (preg_match('/csrf_token:\s\'([A-Za-z0-9\-\=]{2,})\'/s', $body , $regs)) {
    $crsf_token = $regs[1];
} else {
    $crsf_token = false;
}

echo 'CRSF-TOKEN:' , $crsf_token , PHP_EOL;

if (preg_match('/ssoToken:\s\'(.*)\'/', $body , $regs)) {
    $sso_token = $regs[1];
} else {
    $sso_token = false;
}

echo 'SSO-TOKEN:' , $sso_token , PHP_EOL;

//https://app.comagic.ru/analytics/advsales/get_grid_data/?_dc=1528384025424&layer=main&pc_type=all&start_date=2018-06-01%2000%3A00%3A00&end_date=2018-06-07%2023%3A59%3A59&compare_start_date=&compare_end_date=&isCompare=false&date_range={%22start_date%22%3A%222018-06-01%2000%3A00%3A00%22%2C%22end_date%22%3A%222018-06-07%2023%3A59%3A59%22%2C%22compare_start_date%22%3A%22%22%2C%22compare_end_date%22%3A%22%22%2C%22interval%22%3A7}&site_id=23711&reportType=sales_ac&aggregation=day&parameter=[%22sessions_count%22]&filter=[]&checkFilterAtServer=false&page=1&start=0&limit=200&sort=[{%22property%22%3A%22sessions_count%22%2C%22direction%22%3A%22DESC%22}]&csrf_token=Flxu3IoZj-BpNKDF73wG9g%3D%3D

$dataResponse = $c1->request('GET', 'https://app.comagic.ru/analytics/advsales/get_grid_data/?layer=main&pc_type=all&start_date=2018-06-02%2000%3A00%3A00&end_date=2018-06-08%2023%3A59%3A59&compare_start_date=&compare_end_date=&isCompare=false&date_range=%7B%22start_date%22%3A%222018-06-02%2000%3A00%3A00%22%2C%22end_date%22%3A%222018-06-08%2023%3A59%3A59%22%2C%22compare_start_date%22%3A%22%22%2C%22compare_end_date%22%3A%22%22%2C%22interval%22%3A7%7D&site_id=27064&reportType=sales_ac&aggregation=day&parameter=%5B%22sessions_count%22%5D&filter=%5B%5D&checkFilterAtServer=false&page=1&start=0&limit=25&sort=%5B%7B%22property%22%3A%22sessions_count%22%2C%22direction%22%3A%22DESC%22%7D%5D&csrf_token='.urlencode($crsf_token), array_merge(['cookies' => $jar],$headers));


print_r(json_decode($dataResponse->getBody()->getContents(), true))  ;