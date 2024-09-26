<?php

namespace App\Http\Handler;

use DefStudio\Telegraph\Keyboard\ReplyKeyboard;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EventsCommandHandler extends CommandHandler
{
    public function handle(): void
    {
        if (!Cache::has('csrftoken')) {
            Log::debug("Ricavo csrftoken");
            $response = Http::get('https://www.eventbrite.it/');
            Log::debug($response->ok());
            $csrftoken = $response->cookies()->getCookieByName('csrftoken')->getValue();
            Cache::set('csrftoken', $csrftoken);
        }

        $events = $this->getEventForLocation(["101752737"]);
        $eventsList = [];
        foreach ($events as $event) {
            $eventsList[] = [
                "image" => $event['image']['url'],
                "name" => $event['name'],
                "description" => $event['summary'],
                "data" => $event['start_date'] . " - " . $event['end_date'],
                "location" => sprintf("%s - %s - %s",
                    $event['primary_venue']['address']['region'],
                    $event['primary_venue']['address']['city'],
                    $event['primary_venue']['address']['localized_address_display'])
            ];
        }
        foreach ($eventsList as $event) {
            $this->chat->photo($event['image'])->send();
            $message = sprintf("<b>%s</b>\n%s\n\n%s\n\n%s", $event['name'],$event['description'], $event['data'], $event['location']);
            Log::debug("message: $message");
            $this->chat->html($message)->send();
        }
    }

    private function getEventForLocation($locationId): array
    {
        if (!is_array($locationId)) {
            $locationId = [$locationId];
        }
        $csrftoken = Cache::get('csrftoken');
        $cookies = [
            'csrftoken' => $csrftoken
        ];

        $url = "https://www.eventbrite.it/api/v3/destination/search/";
        $headers = [
            "x-csrftoken" => $csrftoken,
            "Accept" => "application/json",
            "referer" => "https://www.eventbrite.it/",
        ];
        $data = [
            "event_search" => [
                "dates" => ["this_weekend"],
                "dedup" => true,
                "places" => $locationId,
                "price" => "free",
                "tags" => ["EventbriteCategory/110"],
                "page" => 1,
                "page_size" => 10,
                "online_events_only" => false
            ],
            "expand.destination_event" => [
                "primary_venue",
                "image",
                "ticket_availability",
                "saves",
                "event_sales_status",
                "primary_organizer",
                "public_collections"
            ],
            "browse_surface" => "search"
        ];

        $response = Http::withCookies($cookies, 'eventbrite.it')->withHeaders($headers)->post($url, $data);
        Log::debug($response->status());
        if (!$response->ok())
            dd($response->body());

        $data = $response->json();
        Log::debug(sprintf("Trovati %s eventi! ", sizeof($data['events']['results'])));
        return $data['events']['results'];

    }
}
