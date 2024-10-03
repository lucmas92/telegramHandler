<?php

namespace App\Http\Handler;

use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

class OnTvCommandHandler extends CommandHandler
{
    public function handle(): void
    {
        $channels = $this->getChannelPrograms();
        $html = sprintf("Programmazione %s:\n\n", date('d-m-Y'));
        foreach ($channels as $channel) {
            $html .= " --------- " . str_pad($channel['channel'] . " ", 20, "-") . "\n";
            $html .= sprintf("    Time: %s\n     Title: %s\n\n",
                $channel['program']['time'],
                $channel['program']['title']
            );
        }
        $this->chat->html($html)->send();
    }

    /**
     * @return array
     */
    private function getChannelPrograms(): array
    {
        $url = "https://www.sorrisi.com/guidatv/stasera-in-tv/";
        $response = Http::get($url);
        $html = $response->body();
        $crawler = new Crawler($html);

        $result = [];
        $channels = $crawler->filter('section.gtv-mod21');
        $channels->each(function (Crawler $channel) use (&$result) {

            $ch = $channel->filter('a.gtv-logo')->text();

            $currentChannel = ['channel' => $ch];
            $currentChannel['program'] = [];
            $programs = $channel->filter('article.gtv-program')->first();
            if ($programs->count() > 0) {
                $programs->each(function (Crawler $node) use ($ch, &$currentChannel) {
                    $time = $node->filter('time')->text();
                    $title = $node->filter('h3.gtv-program-title')->text();
                    $currentChannel['program'] = [
                        'time' => $time,
                        'title' => $title
                    ];
                });
            }
            $result[] = $currentChannel;
        });
        return $result;

    }

}
