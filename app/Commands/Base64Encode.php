<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class Base64Encode extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'job:encode {job}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Encode a job string';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info(base64_encode($this->argument('job')));
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
