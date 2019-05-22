<?php
/**
 * Created by PhpStorm.
 * User: o.nasonov
 * Date: 19.06.2018
 * Time: 17:51
 */
use Illuminate\Database\Eloquent\Model as Eloquent;
class User extends Eloquent

{

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */

    protected $fillable = [

        'login', 'password', 'last_csrf','last_sso', 'last_login', 'last_jar', 'min_limit', 'day_limit'

    ];

    public function ips(){
        return Ip::where('user_id', $this->id)->get();
    }

    public function ipsList(){
        $ips = Ip::where('user_id', $this->id)->get();
        $ipo = [];
        foreach ($ips as $ip){
            $ipo[] = $ip->value;
        }
        return $ipo;
    }


}