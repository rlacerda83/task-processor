<?php

namespace App\Services;

use Carbon\Carbon;
use GuzzleHttp\Client;
use App\Helpers\Crypt;

Class TaskProcessor
{
    const STATUS_ERROR = 'error';
    const STATUS_PROCESSED = 'processed';

    const URL_LOGIN = 'https://accounts.google.com/ServiceLogin';
    const URL_LOGIN_AUTH = 'https://accounts.google.com/ServiceLoginAuth';

    const ERROR_CODE_PASSWORD = 10;
    const ERROR_CODE_AUTHENTICATION = 20;
    const ERROR_CODE_URL_FORM = 30;
    const ERROR_CODE_EMAIL = 40;

    const ERROR_PASSWORD_CONFIGURATION = 'You did not fill the password in the system settings.The password is required for authentication on Google, please enter your password:';
    const ERROR_URL_FORM_CONFIGURATION = 'You did not fill the google forms URL in the system settings. Please enter the information in the configuration menu';
    const ERROR_EMAIL_CONFIGURATION = 'You did not fill the email in the system settings. Please enter the information in the configuration menu';

    /**
     * @var array
     */
    protected static $messagesLoginSuccessful = [
        'Account Overview',
        'Visão geral da conta'
    ];

    /**
     * @var array
     */
    protected static $defaultOptions = [
        'verify' => false,
        'headers' => [
            'User-Agent' => "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)"
        ]
    ];

    protected static $defaultRedirectOptions = [
        'max'             => 15,
        'strict'          => false,
        'referer'         => true,
        'protocols'       => ['http', 'https'],
        'track_redirects' => false
    ];

    /**
     * @var array
     */
    protected $hiddenValues = [];

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var array
     */
    protected $tasks = [];

    /**
     * @var array
     */
    protected $tasksFinished = [];

    /**
     * @var array
     */
    protected $tasksError = [];

    /**
     * @var \stdClass
     */
    protected $configuration;

    /**
     * @var string
     */
    protected $secretKey;

    /**
     * @var string
     */
    protected $error;

    /**
     * TaskProcessor constructor.
     */
    public function __construct()
    {
        libxml_use_internal_errors(true);

        $jar = new \GuzzleHttp\Cookie\CookieJar;
        $options = array_merge(['cookies' => $jar], self::$defaultOptions);
        $this->client = new Client($options);
    }

    /**
     * @param $tasks
     */
    public function setTasks($tasks)
    {
        $this->tasks = $tasks;
    }

    /**
     * @param $configuration
     */
    public function setConfiguration($configuration)
    {
        $this->configuration = $configuration;
    }

    /**
     * @param $key
     */
    public function setSecretKey($key)
    {
        $this->secretKey = $key;
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function process()
    {
        if (!count($this->tasks)) {
            return true;
        }

        try {
            $this->login();
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            return false;
        }

        foreach ($this->tasks as $task) {
            try {
                $this->processTask($task);
                $this->tasksFinished[] = $task;
            } catch (\Exception $e) {
                $task->status = self::STATUS_ERROR;
                $task->error_message = $e->getMessage();
                $this->tasksError[] = $task;
            }
        }

        return true;
    }

    /**
     * @param Tasks $task
     * @return bool
     */
    private function processTask($task)
    {
        $urlGet = $task->link ? $task->link : $this->configuration->url_form;
        $urlPost = str_replace('viewform', 'formResponse', $urlGet);
        $time = str_replace('.', ',', $task->time);

        $data = [
            'entry.116160053' => $task->task,
            'entry.789822953' => $task->date,
            'entry.368040154' => $time,
            'entry_1252005894' => $task->description,
            'emailReceipt' => $this->configuration->send_email_process ? 'true' : ''
        ];

        $responseGetForm = $this->client->get($urlGet);

        $this->updateHiddenValues($responseGetForm->getBody()->getContents());
        $allData = $this->mergeData($data);

        //request options
        $options = self::$defaultOptions;
        $options['form_params'] = $allData;
        $options['allow_redirects'] = self::$defaultRedirectOptions;;

        $responsePostForm = $this->client->post($urlPost, $options)->getBody()->getContents();
        $link = $this->getLinkFromResponseForm($responsePostForm);

        $this->updateTask($task, $link);

        return true;
    }

    /**
     * @param Tasks $task
     * @return bool
     * @throws \Exception
     */
    public function processOneTask(Tasks $task)
    {
        Log::info("Start one task process [{$task->id}]");

        if ($task->status == Tasks::STATUS_PROCESSED) {
            Log::info("Task [{$task->id}] is already processed");
            return true;
        }

        $this->login();
        $this->processTask($task);

        Log::info('Finish one task process');

        return true;
    }

    /**
     * @param $responsePostForm
     * @return bool
     */
    protected function getLinkFromResponseForm($responsePostForm)
    {
        $html = new \DOMDocument();
        $html->strictErrorChecking = false;
        $html->loadHtml($responsePostForm);

        $xpath = new \DOMXpath($html);

        $results = $xpath->query("//*[@class='ss-bottom-link']");

        foreach ($results as $nodeLink) {
            foreach ($nodeLink->attributes as $attribute) {
                if ($attribute->name == 'href') {
                    return $nodeLink->getAttribute('href');
                }
            }
        }

        return false;
    }

    /**
     * @param Tasks $task
     * @param $link
     * @return bool
     */
    private function updateTask($task, $link)
    {
        if (!$link) {
            $task->status = self::STATUS_ERROR;
            $task->error_message = 'Cant save form';
            return true;
        }

        $task->status = self::STATUS_PROCESSED;
        $task->sent_at = Carbon::now()->format('Y-m-d');
        $task->link = $link;

        return true;
    }

    /**
     * @param $page
     */
    private function updateHiddenValues($page)
    {
        $html = new \DOMDocument();
        $html->loadHtml($page);

        $nodes = $html->getElementsByTagName('input');

        foreach($nodes as $node) {
            if ($node->hasAttributes()) {
                foreach ($node->attributes AS $attribute) {
                    if ($attribute->nodeName == 'type' && $attribute->nodeValue == 'hidden') {
                        $this->hiddenValues[$node->getAttribute('name')] = $node->getAttribute('value');
                    }
                }
            }
        }
    }

    /**
     * @return bool
     * @throws \Exception
     */
    private function login()
    {
        // get login page and update hidden values
        $response = $this->client->get(self::URL_LOGIN);
        $this->updateHiddenValues($response->getBody()->getContents());

        // authenticate
        $resultAuthenticate = $this->authenticate();
        if (!$this->checkAuthentication($resultAuthenticate)) {
            throw new \Exception('Authentication failed, check your credentials.', 20);
        }

        return true;
    }

    /**
     * @return string
     */
    private function authenticate()
    {
        // post login
        $data = [
            'Email' => $this->configuration->email,
            'Passwd' => Crypt::decrypt($this->configuration->password, $this->secretKey)
        ];

        $allData = $this->mergeData($data);
        unset($allData['PersistentCookie']);

        //request options
        $options = self::$defaultOptions;
        $options['form_params'] = $allData;
        $options['allow_redirects'] = self::$defaultRedirectOptions;

        return $this->client->post(self::URL_LOGIN_AUTH, $options)->getBody()->getContents();
    }

    /**
     * @param $page
     * @return bool
     */
    private function checkAuthentication($page) {
        $html = new \DOMDocument();
        $html->strictErrorChecking = false;
        $html->loadHtml($page);

        $title = $html->getElementsByTagName('title')->item(0)->nodeValue;
        if ( in_array($title, self::$messagesLoginSuccessful)) {
            return true;
        }

        return false;
    }

    /**
     * @param array $data
     * @param bool|false $force
     * @return array
     */
    private function mergeData(array $data, $force = false)
    {
        foreach ( $this->hiddenValues AS $i => $v) {
            if ( array_key_exists($i, $data) && $force ) {
                continue;
            }
            $data[$i] = $v;
        }

        return $data;
    }

    /**
     * @return array
     */
    public function getTasksFinishedWithSuccess()
    {
        return $this->tasksFinished;
    }

    /**
     * @return array
     */
    public function getTasksWithError()
    {
        return $this->tasksError;
    }

    /**
     * @return string
     */
    public function getError()
    {
        return $this->error;
    }
}