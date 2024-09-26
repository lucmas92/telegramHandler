<?php

namespace App\Http\Handler;

use DefStudio\Telegraph\Models\TelegraphChat;
use Illuminate\Support\Facades\Log;

abstract class CommandHandler
{
    protected TelegraphChat $chat;

    public function __construct(TelegraphChat $chat)
    {
        $this->chat = $chat;
    }

    public function handleCommand(): void
    {
        Log::info(get_called_class().'::handle');
        $this->handle();
    }

    abstract public function handle(): void;

}
