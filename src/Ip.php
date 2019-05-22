<?php
/**
 * Created by PhpStorm.
 * User: o.nasonov
 * Date: 22.10.2018
 * Time: 15:42
 * // Comagic Adds
 * @Клиент:
 * @Инфопин:
 * @Задача:
 * @Сайт:
 * Модель для контроля IP адресов
 */

use Illuminate\Database\Eloquent\Model as Eloquent;

class Ip extends Eloquent
{
    protected $fillable = [
         'user_id', 'str', 'value'
    ];


    public function getValueAttribute($value)
    {
        $value = (float) $value;
        return long2ip(sprintf("%d", $value));
    }

    public function setValueAttribute($value)
    {
        $this->attributes['value'] = sprintf("%u", ip2long($value));
        $this->attributes['str'] = $value;
    }

    public function setStrAttribute($value)
    {
        $this->attributes['value'] = sprintf("%u", ip2long($value));
        $this->attributes['str'] = $value;
    }




}