<?php
namespace ULan\Sms;

use \Session;

class SmsManager
{
    /**
     * the application instance
     * @var
     */
    protected $app;

    /**
     * agent instances
     * @var
     */
    protected $agents;

    /**
     * sms data
     * @var
     */
    protected $smsData;

    /**
     * construct
     * @param $app
     */
	public function __construct($app)
    {
        $this->app = $app;
        $this->init();
    }

    /**
     * sms manager init
     */
    private function init()
    {
        $data = [
                'sent' => false,
                'mobile' => '',
                'code' => '',
                'deadline_time' => 0,
                'verify' => config('sms.verify'),
            ];
        $this->smsData = $data;
    }

    /**
     * get data
     * 获取发送相关信息
     * @return mixed
     */
    public function getSmsData()
    {
        return $this->smsData;
    }

    /**
     * set sent data
     * 设置发送相关信息
     * @param array $data
     */
    public function setSmsData(Array $data)
    {
        $this->smsData = $data;
    }

    /**
     * put sms data to session
     * @param array $data
     */
    public function storeSmsDataToSession(Array $data = [])
    {
        $data = $data ?: $this->smsData;
        $this->smsData = $data;
        Session::put($this->getSessionKey(), $data);
    }

    /**
     * get sms data from session
     * @return mixed
     */
    public function getSmsDataFromSession()
    {
        return Session::get($this->getSessionKey(), []);
    }

    /**
     * remove sms data from session
     */
    public function forgetSmsDataFromSession()
    {
        Session::forget($this->getSessionKey());
    }

    /**
     * Is there a designated validation rule
     * 是否有指定的验证规则
     * @param $name
     * @param $ruleName
     *
     * @return bool
     */
    public function hasRule($name, $ruleName)
    {
        $data = $this->getSmsData();
        return isset($data['verify']["$name"]['rules']["$ruleName"]);
    }

    /**
     * get rule by name
     * @param $name
     *
     * @return mixed
     */
    public function getRule($name)
    {
        $data = $this->getSmsData();
        $ruleName = $data['verify']["$name"]['choose_rule'];
        return $data['verify']["$name"]['rules']["$ruleName"];
    }

    /**
     * set rule
     * @param $name
     * @param $value
     *
     * @return mixed
     */
    public function rule($name, $value)
    {
        $data = $this->getSmsData();
        $data['verify']["$name"]['choose_rule'] = $value;
        $this->setSmsData($data);
        return $data;
    }

    /**
     * is verify
     * @param string $name
     *
     * @return mixed
     */
    public function isCheck($name = 'mobile')
    {
        $data = $this->getSmsData();
        return $data['verify']["$name"]['enable'];
    }

    /**
     * get default and alternate agent`s verify sms template id
     * 获得默认/备用代理器的验证码短信模板id
     */
    public function getVerifySmsTemplateIdArray()
    {
        if ($this->isAlternateAgentsEnable()) {
            $agents = $this->getAlternateAgents();
            $defaultAgentName = $this->getDefaultAgent();
            if ( ! in_array($defaultAgentName, $agents)) {
                array_unshift($agents, $defaultAgentName);
            }
        } else {
            $agents[] = $this->getDefaultAgent();
        }
        $tempIdArray = [];
        foreach ($agents as $agentName) {
            $tempIdArray["$agentName"] = $this->getVerifySmsTemplateId($agentName);
        }
        return $tempIdArray;
    }

    /**
     * get verify sms template id
     * @param String $agentName
     * @return mixed
     */
    public function getVerifySmsTemplateId($agentName = null)
    {
        $agentName = $agentName ?: $this->getDefaultAgent();
        $agentConfig = config('sms.'.$agentName, null);
        if ($agentConfig && isset($agentConfig['verifySmsTemplateId'])) {
            return $agentConfig['verifySmsTemplateId'];
        }
        return '';
    }

    /**
     * get verify sms content
     * @return mixed
     */
    public function getVerifySmsContent()
    {
        return config('sms.verifySmsContent');
    }

    /**
     * generate verify code
     * @param null $length
     * @param null $characters
     *
     * @return string
     */
    public function generateCode($length = null, $characters = null)
    {
        $length = $length ?: (int) config('sms.codeLength');
        $characters = $characters ?: '123456789';
        $charLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; ++$i) {
            $randomString .= $characters[mt_rand(0, $charLength - 1)];
        }
        return $randomString;
    }

    /**
     * get code valid time (minutes)
     * @return mixed
     */
    public function getCodeValidTime()
    {
        return config('sms.codeValidTime');
    }

    /**
     * get session key
     * @return mixed
     */
    public function getSessionKey()
    {
        return config('sms.sessionKey');
    }

    /**
     * get the default agent name
     * @return mixed
     */
    public function getDefaultAgent()
    {
        return config('sms.agent');
    }

    /**
     * set the default agent name
     * @param $name
     * @return string
     */
    public function setDefaultAgent($name)
    {
        config(['sms.agent' => $name]);
        return config('sms.agent');
    }

    /**
     * get a agent instance
     * @param null $agentName
     *
     * @return mixed
     */
    public function agent($agentName = null)
    {
        $agentName = $agentName ?: $this->getDefaultAgent();
        if (! isset($this->agents[$agentName])) {
            $this->agents[$agentName] = $this->createAgent($agentName);
        }
        return $this->agents[$agentName];
    }

    /**
     * create a agent instance by agent name
     * @param $agentName
     *
     * @return mixed
     */
    public function createAgent($agentName)
    {
        $method = 'create'.ucfirst($agentName).'Agent';
        $agentConfig = $this->getAgentConfig($agentName);
        return $this->$method($agentConfig);
    }

    /**
     * get agent config
     * @param $agentName
     *
     * @return array
     */
    public function getAgentConfig($agentName)
    {
        $config = config("sms.$agentName", []);
        $config['smsSendQueue'] = config('sms.smsSendQueue', false);
        $config['smsWorker'] = config('sms.smsWorker', 'ULan\Sms\SmsWorker');
        $config['nextAgentEnable'] = $this->isAlternateAgentsEnable();
        $config['nextAgentName'] = $this->getAlternateAgentNameByCurrentName($agentName);
        $config['currentAgentName'] = $agentName;
        if ( ! class_exists($config['smsWorker'])) {
            throw new \InvalidArgumentException("Worker [" . $config['smsWorker'] . "] not support.");
        }
        return $config;
    }

    /**
     * is alternate agents enable
     * return false or true
     * @return mixed
     */
    public function isAlternateAgentsEnable()
    {
        return config('sms.alternate.enable', false);
    }

    /**
     * get alternate agents name
     * @return mixed
     */
    public function getAlternateAgents()
    {
        return config('sms.alternate.agents', []);
    }

    /**
     * get alternate agent`s name
     * @param $agentName
     *
     * @return null
     */
    public function getAlternateAgentNameByCurrentName($agentName)
    {
        $agents = $this->getAlternateAgents();
        if ( ! count($agents)) {
            return null;
        }
        if ( ! in_array($agentName, $agents)) {
            return $agents[0];
        }
        if (in_array($agentName, $agents) && $agentName == $this->getDefaultAgent()) {
            return null;
        }
        $currentKey = array_search($agentName, $agents);
        if (($currentKey + 1) < count($agents)) {
            return $agents[$currentKey + 1];
        }
        return null;
    }

    /**
     * method overload
     * @param $name
     * @param $args
     *
     * @return mixed
     */
    public function __call($name, $args)
    {
        if (preg_match('/^(?:create)([0-9a-zA-Z]+)(?:Agent)$/', $name, $matches)) {
            $agentName = $matches[1];
            $className = 'ULan\\Sms\\Agents\\' . $agentName . 'Agent';
            if (class_exists($className)) {
                if (isset($args[0]) && is_array($args[0])) {
                    return new $className($args[0]);
                }
                throw new \InvalidArgumentException("Agent [$agentName] arguments cannot be empty, and must be array.");
            }
            throw new \InvalidArgumentException("Agent [$agentName] not support.");
        }
        throw new \BadMethodCallException("Method [$name] does not exist.");
    }
}
