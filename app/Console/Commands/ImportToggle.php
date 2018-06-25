<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use \Toggl;
use Symfony\Component\Console\Input\InputOption;

/**
 * Class ImportToggle.
 */
class ImportToggle extends Command
{
    /**
     * @var string
     */
    protected $name = 'ninja:import-toggle';

    /**
     * @var string
     */
    protected $description = 'Import data from time tracking system Toggle';

    public function fire()
    {
        $this->info(date('r') . ' Loading data...');

        $response = Toggl::summaryThisMonth();

        print_r($response);
    }

    /**
     * @return array
     */
    protected function getArguments()
    {
        return [];
    }

    /**
     * @return array
     */
    protected function getOptions()
    {
        return [];
    }
}
