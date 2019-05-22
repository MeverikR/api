<?php
/**
 * Created by PhpStorm.
 * User: o.nasonov
 * Date: 19.10.2018
 * Time: 14:39
 * // Comagic Adds
 * @Клиент:
 * @Инфопин:
 * @Задача:
 * @Сайт:
 *
 * Модель логирования запроса в БД
 */
use Illuminate\Database\Eloquent\Model as Eloquent;
class Log extends Eloquent

{

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */

    protected $fillable = [
        'user', 'ip', 'request','method', 'external_id', 'req_method', 'status',
        'mnemonic', 'ua'
    ];

    public function getIpAttribute($value)
    {
        return long2ip($value);
    }

    public function setIpAttribute($value)
    {
        $this->attributes['ip'] = ip2long($value);
    }

    public function setCreatedAtAttribute($value)
    {
        $this->attributes['created_at'] = (new DateTime('now', new DateTimeZone('Europe/Moscow')))->format('Y-m-d H:i:s');
    }


    public function setUpdatedAtAttribute($value)
    {
        $this->attributes['updated_at'] = (new DateTime('now', new DateTimeZone('Europe/Moscow')))->format('Y-m-d H:i:s');
    }



}