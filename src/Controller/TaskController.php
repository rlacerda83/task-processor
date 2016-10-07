<?php 

namespace App\Controller;

use App\Services\TaskProcessor;
use GuzzleHttp\Client;
use Symfony\Component\HttpFoundation\JsonResponse;

class TaskController
{
    protected $app;

	public function __construct($app)
	{
        $this->app = $app;
	}
	
	public function test()
	{
		
	}

    public function index()
    {
        $client = new Client();
        $taskUrl = $this->app['taskControlUrl'] . $this->app['taskControlToken'];

        $result = json_decode($client->get($taskUrl)->getBody()->getContents());

        if (!isset($result->data->configuration)) {
            return new JsonResponse(['status' => false]);
        }
        $processor = new TaskProcessor();
        $processor->setSecretKey($this->app['secretKey']);
        $processor->setConfiguration($result->data->configuration);
        $processor->setTasks($result->data->tasks);
        $success = $processor->process();
        
        if (!$success) {
            echo $processor->getError();
        }

        $response = [];
        $response['token'] = $this->app['taskControlToken'];
        $response['tasks'] = [];
        $tasksWithSuccess = $processor->getTasksFinishedWithSuccess();
        foreach ($tasksWithSuccess as $task) {
            $response['tasks'][] = [
                'id' => $task->id,
                'link' => $task->link,
                'status' => $task->status
            ];
        }

        $taskErrors = $processor->getTasksWithError();
        foreach ($taskErrors as $task) {
            $response['tasks'][] = [
                'id' => $task->id,
                'link' => $task->link,
                'status' => $task->status,
                'error_message' => substr($task->error_message, 0, 100)
            ];
        }

        if (count($response['tasks'])) {
            $this->sendConfirmation($response);
        }

        return new JsonResponse(['status' => true]);
    }

    /**
     * @param $response
     * @return bool
     */
    public function sendConfirmation($response)
    {
        $client = new Client();

        $response = $client->request(
            'POST',
            $this->app['taskControlConfirmUrl'],
            ['body' => json_encode($response)]
        );

        $bodyResponse = json_decode($response->getBody()->getContents());

        return true;
    }
}