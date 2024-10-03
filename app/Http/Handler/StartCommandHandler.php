<?php

namespace App\Http\Handler;

use DefStudio\Telegraph\Keyboard\ReplyKeyboard;

class StartCommandHandler extends CommandHandler
{
    public function handle(): void
    {

        $keyboard = ReplyKeyboard::make()
            ->button('/ral')
            ->button('/start')
            ->button('/events')
            ->button('/test')
            ->button('/onTv')
            ->button('Start WebApp')->webApp('https://breakingitaly.it/');

            $this->chat->html(__('telegraph::' . __METHOD__))
                ->replyKeyboard($keyboard)
                ->send();
    }

}
