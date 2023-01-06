<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class Base64Decode extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'job:decode {job}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Decode a job';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info(base64_decode($this->argument('job')));
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
