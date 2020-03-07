<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class CreateJob extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'job:create {job}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Create a new job';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        dispatch(new \App\Jobs\QueueJob($this->argument('job')))->onQueue('default');
    }

    /**
     * Define the command's schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule $schedule
     * @return void
     */
    public function schedule(Schedule $schedule): void
    {
        // $schedule->command(static::class)->everyMinute();
    }
}
