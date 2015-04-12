<?php
/**
 * @link http://www.wayhood.com/
 */

namespace wh\jpush;

use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\base\ViewContextInterface;

use JPush\JPushClient;
use JPush\JPushLog;

use JPush\Exception\APIConnectionException;
use JPush\Exception\APIRequestException;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

/**
 * Class Jpusher
 * @package wh\jpush
 * @author Song Yeung <netyum@163.com>
 * @date 4/12/15
 */
class Jpusher extends Component
{
    public $appKey = 'dd1066407b044738b6479275';

    public $appSecret = '6b135be0037a5c1e693c3dfa';

    private $_jpusher;

    private $_playloadPath;

    public function getJpusher()
    {
        if (!is_object($this->_jpusher)) {
            $this->_jpusher = $this->createJpusher();
        }

        return $this->_jpusher;
    }

    public function createJpusher()
    {
        JPushLog::setLogHandlers(array(new StreamHandler('jpush.log', Logger::DEBUG)));
        $client = new JPushClient($this->appKey, $this->appSecret);
    }

    public function getPayloadPath()
    {
        if ($this->_playloadPath === null) {
            $this->setPayloadPathPath('@app/push');
        }
        return $this->_playloadPath;
    }

    public function setPayloadPath($path)
    {
        $this->_playloadPath = Yii::getAlias($path);
    }

    public function compose($id = null, array $params = [])
    {
        $payloadFile  = $this->getPayloadPath().'/'. $id;
        if (is_file($payloadFile)) {
            $playload = require($playload);
            $playload = $this->replace($playload, $params);

            var_dump($playload);
        }
    }

    public function replace($array, $params)
    {
        foreach($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = $this->replace($value, $params);
            } else {
                $array[$key] = $value = strtr($value, $params);
            }
        }
        return $array;
    }
} 