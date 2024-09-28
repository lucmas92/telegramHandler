<?php

namespace App\Http\Handler;

use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler;

class RalCommandHandler extends CommandHandler
{
    private string $base_url = "https://my.studioziveri.it/";
    private string $username = "MSSLCU92H23L551G";
    private string $password = "payroll7!";

    private int $maxRetries = 3;

    private int $currentRetry = 0;

    /**
     * @return void
     * @throws Exception
     */
    public function handle(): void
    {
        Log::debug(__METHOD__);

        $key = __METHOD__ . 'Message';
        if (!Cache::has($key)) {
            $this->chat->message("Uff.... Mi tocca cercare sul sito...")->send();
            $data = $this->getData();
            $payCheck = $this->getLastPaycheck();
            $message = sprintf(
                "Livello: %s, RAL: €%s\nUltima busta paga: %s - %s/%s\nImporto: €%s",
                $data['Livello'],
                ((float)str_replace("€", "", $data['Lordo mensile']) * 13),
                $payCheck['mese_plaintext'],
                $payCheck['anno'],
                $payCheck['mese'],
                $payCheck['importo'],
            );
            Cache::set($key, $message, 1800);
        }
        $message = Cache::get($key);
        Log::debug("Message sent: " . $message);

        $this->chat->html($message)->send();
    }

    /**
     * @return void
     * @throws Exception
     */
    private function login(): void
    {
        $this->chat->message("Sto facendo il login...")->send();
        Log::debug(__METHOD__);
        $url = $this->base_url . "default.aspx";
        $headers = ["Content-Type: application/x-www-form-urlencoded"];
        $data = [
            "langDropDown" => "IT",
            "__EVENTTARGET" => "Entra",
            "__VIEWSTATE" => "/wEPDwUKLTE3OTA5NDk3Nw9kFgICAQ9kFhBmDxUBIExhIHR1YSBwb3J0YSBkaWdpdGFsZSBpbiBhemllbmRhZAICDxUBDkNvZGljZSBmaXNjYWxlZAIDDw9kFgIeC3BsYWNlaG9sZGVyBSBJbnNlcmlzY2kgaWwgdHVvIGNvZGljZSBmaXNjYWxlLmQCBQ9kFgJmDxYCHgRUZXh0BRRQYXNzd29yZCBkaW1lbnRpY2F0YWQCBw8PZBYCHwAFGUluc2VyaXNjaSBsYSB0dWEgcGFzc3dvcmRkAgkPEA8WAh4LXyFEYXRhQm91bmRnZGQWAWZkAgsPDxYCHwEFBUVudHJhZGQCDA8VAkFTZSBub24gcmllc2NpIGFkIGFjY2VkZXJlLCBjb250YXR0YSBpbCB0dW8gdWZmaWNpbyBkZWwgcGVyc29uYWxlLlNTVFVESU8gWklWRVJJIFNSTCA8YnIvPiBhc3Npc3Rlbnphc29mdHdhcmVAc3R1ZGlveml2ZXJpLml0IDxici8+UC5JVkEgSVQwMTk1OTE4MDM0OGRkLaEkRZzJf1J6W8UxvneofYiV4f1QXoJ9Uaj8hqgarvA=",
            "__VIEWSTATEGENERATOR" => "CA0B0334",
            "__EVENTVALIDATION" => "/wEdAAisQzTIMA/DeYgfc3Sv2zLn1vfz4eH+qJVYUPHOMOUASGHQ7SdrF7jHL2TyMf3KuNB2NvjHOkq5wKoqN6Aim8WGzeURZMZ7sCsTuq8oRfYjnrHYrXFBdkpT5G/aDVU0QFCZYhEurIqW07cwoJdHtnFcijVz3TgvKL/8WFjSeTbbipur3YcKRXtOLUSzTrU/thK9EX4rDnOi4cojsto31bBD",
            "txtCF" => $this->username,
            "txtPassword" => $this->password,
        ];
        $response = Http::asForm()->withHeaders($headers)->post($url, $data);
        Log::debug($url . ' - ' . $response->status());
        $cookies = [
            'cookiesession1' => $response->cookies()->getCookieByName('cookiesession1')->getValue(),
            'ASP.NET_SessionId' => $response->cookies()->getCookieByName('ASP.NET_SessionId')->getValue()
        ];
        Cache::set('ziveriCookies', $cookies);
        Log::debug(__METHOD__ . ' - ' . $response->ok());
        if (!$response->ok()) {
            throw new Exception("Login error!");
        }
    }

    /**
     * @return array
     * @throws Exception
     */
    private function getData(): array
    {
        Log::debug(__METHOD__);

        $url = $this->base_url . 'HRWindow.aspx';

        $this->chat->message("...recupero i dati da HRWindow...")->send();

        $html = $this->getPage($url, 'getData');

        // Crea un'istanza di DOMDocument
        $crawler = new Crawler($html);

        $containers = $crawler->filter('div.listaInformazioni');

        $data = [];

        $containers->each(function ($container, $containerIndex) use (&$data) {
            if ($containerIndex == 1)
                $container->filter('div.informazione')
                    ->each(function (Crawler $node) use (&$data): void {
                        $data[$node->filter('span')->text()] = $node->filter('div')->getNode(2)->textContent;
                    });
        });

        return $data;

    }

    private function getLastPaycheck(): array
    {

        Log::debug(__METHOD__);

        $url = $this->base_url . 'Stipendi.aspx';
        $this->chat->message("...recupero i dati sulla busta paga...")->send();

        $html = $this->getPage($url, 'getLastPaycheck');

        // Crea un'istanza di DOMDocument
        $crawler = new Crawler($html);

        $paycheckTable = $crawler->filter('table#DataGrid1');
        $lastPaycheck = $paycheckTable->filter('tr')->getNode(1);
        $anno = $lastPaycheck->childNodes->item(2)->textContent;
        $mese = $lastPaycheck->childNodes->item(3)->textContent;
        $mese_plaintext = $lastPaycheck->childNodes->item(4)->textContent;
        $paycheckUrl = $this->base_url . $lastPaycheck->childNodes->item(7)->childNodes->item(1)->attributes->item(2)->nodeValue;
        $amount = $this->getPaycheckAmount($paycheckUrl);
        return [
            'anno' => $anno,
            'mese' => $mese,
            'mese_plaintext' => $mese_plaintext,
            'importo' => $amount,
        ];
    }

    private function getPaycheckAmount($url): float
    {
        Log::debug(__METHOD__);

        $html = $this->getPage($url, 'getPaycheckAmount');

        $crawler = new Crawler($html);

        return (float)$crawler->filter('span#cedolino_main_amount_value')->text();

    }


    /**
     * @param $url
     * @param $method
     * @return string
     * @throws Exception
     */
    private function getPage($url, $method): string
    {
        Log::debug(__METHOD__);

        if (!Cache::has('ziveriCookies')) {
            $this->login();
        }
        $cookies = Cache::get('ziveriCookies');
        $response = Http::withHeader('User-agent','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36')
            ->withCookies($cookies, "my.studioziveri.it")
            ->get($url);
        if (!$response->ok()) {
            if ($this->currentRetry >= $this->maxRetries) {
                Log::error(__METHOD__ . "maxRetries exceeded");
                throw new Exception(__METHOD__ . "maxRetries exceeded");
            }

            $this->currentRetry++;
            Cache::forget('ziveriCookies');
            $this->{$method}();
        }
        Log::debug($url . ' - ' . $response->status());
        $body = $response->body();
        if (Str::contains($body, 'Nessuna azienda selezionata')){
            if ($this->currentRetry >= $this->maxRetries) {
                Log::error(__METHOD__ . "maxRetries exceeded");
                throw new Exception(__METHOD__ . "maxRetries exceeded");
            }
            $this->currentRetry++;
            $this->login();
            return $this->getPage($url, $method);
        }
        return $response->body();
    }

}
