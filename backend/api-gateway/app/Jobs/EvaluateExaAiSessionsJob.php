<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\ExaAiExecutionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class EvaluateExaAiSessionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->onConnection('redis');
        $this->onQueue('exaai');
    }

    public function handle(ExaAiExecutionService $execution): void
    {
        $execution->evaluateActiveSessions();
    }
}