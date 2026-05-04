<?php

namespace App\Jobs;

use App\Helpers\NotificationHelper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $userId;
    public string $type;
    public string $title;
    public string $message;
    public ?array $data;

    public function __construct(
        int $userId,
        string $type,
        string $title,
        string $message,
        ?array $data = null
    ) {
        $this->userId = $userId;
        $this->type = $type;
        $this->title = $title;
        $this->message = $message;
        $this->data = $data;
    }

    public function handle(): void
    {
        NotificationHelper::send(
            userId: $this->userId,
            type: $this->type,
            title: $this->title,
            message: $this->message,
            data: $this->data
        );
    }
}