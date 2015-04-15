<?php
/**
 * @link http://www.wayhood.com/
 */

namespace wh\jpush;

use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\base\ViewContextInterface;
use yii\helpers\ArrayHelper;

use JPush\Model as M;
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

    private $_payloadPath;

    private $_platform;

    private $_payload;

    private $_logPath;

    public function getJpusher()
    {
        if (!is_object($this->_jpusher)) {
            $this->_jpusher = $this->createJpusher();
        }

        return $this->_jpusher;
    }

    public function createJpusher()
    {
        JPushLog::setLogHandlers(array(new StreamHandler($this->getLogPath().'/jpush.log', Logger::DEBUG)));
        return new JPushClient($this->appKey, $this->appSecret);
    }

    public function getLogPath()
    {
        if ($this->_logPath === null) {
            $this->setLogPath('@app/runtime');
        }
        return $this->_logPath;
    }

    public function setLogPath($path)
    {
        $this->_logPath = Yii::getAlias($path);
    }

    public function getPayloadPath()
    {
        if ($this->_payloadPath === null) {
            $this->setPayloadPathPath('@app/push');
        }
        return $this->_payloadPath;
    }

    public function setPayloadPath($path)
    {
        $this->_payloadPath = Yii::getAlias($path);
    }

    public function setPlatform($platform = 'all')
    {
        if (is_string($platform) && $platform = 'all') {
            $this->_platform = M\all;
        } elseif (is_array($platform)) {
            $this->_platform = M\Platform($platform);
        }
    }

    public function compose($id = null, array $params = [])
    {
        $payloadFile  = $this->getPayloadPath().'/'. $id .'.php';
        if (is_file($payloadFile)) {
            $payload = require($payloadFile);
            $this->merge($payload, $params);
            $payload = $this->replace($payload, $params);
            $this->_payload = $payload;
        }
        return $this;
    }

    public function merge(&$payload, &$params) {
        $m = [];

        foreach($params as $key => $value) {
            if (!preg_match('/{.+}/is', $key)) {
                $m[$key] = $value;
                unset($params[$key]);
            }
        }
        $payload = ArrayHelper::merge($payload, $m);
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

    public function send($audience = null)
    {
        $push = $this->getJpusher()->push();

        $push->setPlatform($this->_platform);

        $params = [];
        if (is_null($audience) || !is_array($audience)) {
            $push->setAudience(M\all);
        } else {

            if (isset($audience['tags'])) {
                $params[] = call_user_func_array('\JPush\Model\tag', [$audience['tags']]);
            }

            if (isset($audience['alias'])) {
                $params[] = call_user_func_array('\JPush\Model\alias', [$audience['alias']]);
            }

            $audience = call_user_func_array('\JPush\Model\audience', $params);
            $push->setAudience($audience);
        }

        if (isset($this->_payload['notification'])) {
            $params = [];
            $notification = $this->_payload['notification'];
            foreach($notification as $key => $value) {
                if (is_int($key)) {
                    $params[] = $value;
                } else if ($key == 'ios') {
                    $iosparams = [];
                    if (isset($value['alert'])) {
                        $iosparams[] = $value['alert'];
                    } else {
                        $iosparams[] = 'Hello Jpush';
                    }

                    if (isset($value['sound'])) {
                        $iosparams[] = $value['sound'];
                    } else {
                        $iosparams[] = null;
                    }

                    if (isset($value['badge'])) {
                        $iosparams[] = $value['badge'];
                    } else {
                        $iosparams[] = null;
                    }

                    if (isset($value['contentAvailable'])) {
                        $iosparams[] = intval($value['contentAvailable']) == 1 ? true : false;
                    } else {
                        $iosparams[] = null;
                    }

                    if (isset($value['extras'])) {
                        $iosparams[] = $value['extras'];
                    } else {
                        $iosparams[] = null;
                    }

                    if (isset($value['category'])) {
                        $iosparams[] = $value['category'];
                    } else {
                        $iosparams[] = null;
                    }
                    //$params[] = M\ios($iosparams[0], $iosparams[1], $iosparams[2], $iosparams[3], $iosparams[4], $iosparams[5]);

                    $params[] = call_user_func_array('\JPush\Model\ios', $iosparams);
                } else if ($key == 'android') {
                    $androidparams = [];

                    if (isset($value['alert'])) {
                        $androidparams[] = $value['alert'];
                    } else {
                        $androidparams[] = 'Hello Jpush';
                    }

                    if (isset($value['title'])) {
                        $androidparams[] = $value['title'];
                    } else {
                        $androidparams[] = null;
                    }

                    if (isset($value['builder_id'])) {
                        $androidparams[] = intval($value['builder_id']);
                    } else {
                        $androidparams[] = null;
                    }

                    if (isset($value['extras'])) {
                        $androidparams[] = $value['extras'];
                    } else {
                        $androidparams[] = null;
                    }

                    $params[] = call_user_func_array('\JPush\Model\android', $androidparams);
                }
            }

            $notification = call_user_func_array('\JPush\Model\notification', $params);

            $push->setNotification($notification);
        }

        if (isset($this->_payload['message'])) {
            $message = $this->_payload['message'];
            $params = ['', '', null, null];
            foreach($message as $key => $value) {
                switch($key) {
                    case 'content':
                        $params[0] = $value;
                        break;
                    case 'title' :
                        $params[1] = $value;
                        break;
                    case 'type' :
                        $params[2] = $value;
                        break;
                    case 'extras' :
                        $params[3] = $value;
                        break;
                }
            }
            $message = call_user_func_array('\JPush\Model\message', $params);

            $push->setMessage($message);

        }

        $push->send();
    }
} 