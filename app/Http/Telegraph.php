<?php

/** @noinspection PhpUnused */
/** @noinspection PhpDocMissingThrowsInspection */

/** @noinspection PhpUnhandledExceptionInspection */

namespace App\Http;

use DefStudio\Telegraph\Client\TelegraphResponse;

class Telegraph extends \DefStudio\Telegraph\Telegraph
{

    /**
     * @return array<string, mixed>
     */
    protected function prepareData(): array
    {
        $asMultipart = $this->files->isNotEmpty();

        $data = $this->data;

        $data = $this->pipeTraits('preprocessData', $data);

        if ($asMultipart) {
            $data = collect($data)
                ->mapWithKeys(function ($value, $key) {
                    if (!is_array($value)) {
                        return [$key => $value];
                    }

                    return [$key => json_encode($value)];
                })->toArray();
        }

        if (env('APP_DEBUG', true)) {
            $data['chat_id'] = null;
        }

        return $data;
    }

}
