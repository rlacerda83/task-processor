<?php 

namespace App\Controller;

use App\Services\TaskProcessor;
use GuzzleHttp\Client;

class TaskController
{
    protected $app;

	public function __construct($app)
	{
        $this->app = $app;
	}

    public function index()
    {
        $client = new Client();
        $taskUrl = $this->app['taskControlUrl'] . $this->app['taskControlToken'];

        $result = json_decode($client->get($taskUrl)->getBody()->getContents());

        $processor = new TaskProcessor();
        $processor->setSecretKey($this->app['secretKey']);
        $processor->setConfiguration($result->data->configuration);
        $processor->setTasks($result->data->tasks);
        $success = $processor->process();
        
        if (!$success) {
            echo $processor->getError();
        }
        
        $tasksWithSuccess = $processor->getTasksFinishedWithSuccess();
        foreach ($tasksWithSuccess as $task) {

        }

        return $this->app['twig']->render('processor.twig');
    }
}