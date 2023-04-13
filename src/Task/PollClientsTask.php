<?php

namespace BiffBangPow\SSMonitor\Server\Task;

use BiffBangPow\SSMonitor\Server\Helper\CommsHelper;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJob;

class PollClientsTask extends AbstractQueuedJob
{
    public function __construct()
    {

    }

    public function getTitle()
    {
        return "BBP Monitoring - Collect data";
    }

    public function process()
    {
        $helper = new CommsHelper();
        $res = $helper->process();

        if (is_array($res)) {
            $this->addMessage($this->updateClients($res));
        }
        else {
            $this->addMessage($res);
        }

        $this->isComplete = true;
        $this->addMessage('Done');
    }

    /**
     * @param array $res
     * @return string
     * @todo - Write this code - iterate the array and update the client records, checking for successful responses and valid response data, correct uuid, etc.
     */
    private function updateClients($res) {

        return "Foo";
    }



    /**
     * @todo Add requeue / after run functions
     */
}
