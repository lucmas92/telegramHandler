<?php

namespace App\Http\Handler;

use DefStudio\Telegraph\Keyboard\ReplyKeyboard;

class TestCommandHandler extends CommandHandler
{
    public function handle(): void
    {
        $keyboard = ReplyKeyboard::make()
            ->button('/ral')
            ->button('Start WebApp')->webApp('https://breakingitaly.it/');
        $this->chat->html(__('telegraph::' . __METHOD__))->removeReplyKeyboard()->send();
    }

}
