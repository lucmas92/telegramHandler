<?php

namespace App\Http\Webhooks;

use App\Http\Handler\CommandHandler;
use DefStudio\Telegraph\Exceptions\TelegramWebhookException;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Stringable;

class WebhookHandler extends \DefStudio\Telegraph\Handlers\WebhookHandler
{

    protected CommandHandler|null $classHandler = null;

    protected function handleCommand(Stringable $text): void
    {
        [$command, $parameter] = $this->parseCommand($text);

        if (!$this->canHandle($command)) {
            $this->handleUnknownCommand($text);
            return;
        }

        if (!is_null($this->classHandler)) {
            $this->classHandler->handleCommand();
        } else {
            $this->$command($parameter);
        }
    }

    protected function handleCallbackQuery(): void
    {
        $this->extractCallbackQueryData();

        if (config('telegraph.debug_mode', config('telegraph.webhook.debug'))) {
            Log::debug('Telegraph webhook callback', $this->data->toArray());
        }

        /** @var string $action */
        $action = $this->callbackQuery?->data()->get('action') ?? '';

        if (!$this->canHandle($action)) {
            report(TelegramWebhookException::invalidAction($action));
            $this->reply(__('telegraph::errors.invalid_action'));

            return;
        }

        if (!is_null($this->classHandler)) {
            $this->classHandler->handleCommand();
        } else {
            /** @phpstan-ignore-next-line */
            App::call([$this, $action], $this->data->toArray());
        }
    }

    protected function canHandle(string $action): bool
    {
        $classHandler = 'App\Http\Handler\\' . ucfirst(strtolower($action)) . 'CommandHandler';
        Log::debug("Class handler: " . $classHandler);
        $class_exists = class_exists($classHandler);
        if ($class_exists) {
            $this->classHandler = App::make($classHandler, ['chat' => $this->chat]);
            return true;
        } else {
            return parent::canHandle($action);
        }
    }

    public function test(): void
    {
        Log::debug(__METHOD__);
        $this->chat->html(__('telegraph::' . __METHOD__))->send();
    }

}
