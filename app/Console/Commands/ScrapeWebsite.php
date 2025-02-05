<?php

namespace App\Console\Commands;

use App\Models\Announcement;
use Illuminate\Console\Command;

class ScrapeWebsite extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:scrape-website';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scrape website and store data in google sheet';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $websiteUrl = config('scrape.website');
        $page = 1;
        $url = $websiteUrl . config('scrape.pages.main') . "?orderby=nyeste";
        $professions = config('scrape.professions');
        $jobs = [];
        $maxPage = 1;

        foreach ($professions as $profession) {
            $url .= '&profession=' . $profession;
        }

        $patterns = [
            'deadlinePattern'      => '/<li>\s*<strong>\s*Ansøgningsfrist\s*<\/strong>\s*<br\s*\/?>\s*([^<]+)\s*<\/li>/i',
            'emailPattern'         => '/<a\s+[^>]*rel\s*=\s*"nofollow"[^>]*href\s*=\s*"mailto:([^"]+)"[^>]*>([^<]*)<\/a>/i',
            'emailFromTextPattern' => '/<a\s+[^>]*href\s*=\s*"mailto:([^"]+)"[^>]*>([^<]*)<\/a>/i',
            'phonePattern'         => '/(?:^|[^<img])(?<!\w)(\+45[0-9]{8}|(?:\d{2} ?){3}\d{2}|\d{8})(?!\w)/',
            'specialityPattern'    => '/<li>\s*<strong>\s*Speciale\s*<\/strong>\s*<br\s*\/?>\s*([^<]+)\s*<\/li>/i',
            'workplacePattern'     => '/<li>\s*<strong>\s*Arbejdssted\s*<\/strong>\s*<br\s*\/?>\s*([^<]+)\s*<\/li>/i',
            'regionPattern'        => '/<li>\s*<strong>\s*Adresse\s*<\/strong>\s*<br\s*\/?>\s*([^<]+)\s*<\/li>/i',
            'contactInfo'          => '/<strong>\s*Kontaktperson\s*<\/strong>\s*<br\s*\/?>\s*([^<]+)\s*(?:<br\s*\/?>|<a)/i',
            'pages'                => '/\bpage=(\d+)\b/',
            'links'                => '/<a\s*[^>]*?\s*href=[\'"]([^\'"]*\/jobopslag\/[^\'"]*)[\'"]/i',
        ];

        // get last numbers from Google sheet
        $existingNumbers = Announcement::query()->orderBy('id')->get();

        while ($page < ($maxPage + 1)) {
            $this->info('Page: ' . $page);
            $mainPage = \ScrapingClient::scrape(['url' => $url . "&page=$page"]);

            $matches = [];

            // find max page from pagination
            preg_match_all($patterns['pages'], $mainPage, $matches);
            $pages = array_map('intval', $matches[1]);
            $maxPage = !empty($pages) ? max($pages) : null;

            // find job links
            preg_match_all($patterns['links'], $mainPage, $matches);
            $links = $matches[1];

            foreach ($links as $link) {

                // break if job number already exists in google sheet
                if ($existingNumbers->where('number', explode('/', $link)[2])->first()) {
                    break 2;
                }

                // scrape job page
                $job = \ScrapingClient::scrape(['url' => $websiteUrl . $link]);

                $this->info(explode('/', $link)[2]);

                // find contact person name
                preg_match($patterns['contactInfo'], $job, $contactMatches);
                $contactPerson = html_entity_decode(trim($contactMatches[1] ?? ''), ENT_QUOTES | ENT_HTML5);

                // find emails
                preg_match_all($patterns['emailPattern'], $job, $matches);
                $emails = array_values(array_unique($matches[1]));
                $emails = array_map(function ($email) {
                    return html_entity_decode($email, ENT_QUOTES | ENT_HTML5);
                }, $emails);

                if (count($emails)) {
                    preg_match_all($patterns['emailFromTextPattern'], $job, $matches);
                    $emails = array_values(array_unique($matches[1]));
                    $emails = array_map(function ($email) {
                        return html_entity_decode($email, ENT_QUOTES | ENT_HTML5);
                    }, $emails);
                }

                // find phone numbers
                preg_match_all($patterns['phonePattern'], $job, $matches);
                $phoneNumbers = array_values(array_unique($matches[0]));

                $phoneNumbers = array_map(function ($number) {
                    $number = preg_replace('/[>]/', '', $number);
                    $number = trim($number);
                    $number = preg_replace('/[:]/', '', $number);
                    $number = preg_replace('/[;]/', '', $number);
                    $number = preg_replace('/[(]/', '', $number);
                    $number = preg_replace('/[)]/', '', $number);
                    $number = str_replace('b', '', $number);
                    $number = trim($number);
                    $number = preg_replace('/\s+/', '', $number);

                    return preg_replace('/\s+/', '', $number);
                }, $phoneNumbers);

                $phoneNumbers = array_unique(array_filter($phoneNumbers, function ($number) use ($link) {
                    $number = mb_convert_encoding($number, 'UTF-8', 'UTF-8');
                    return !preg_match('/^\//', $number) && !preg_match('/[%]/', $number) && !preg_match('/[?]/', $number) && !empty($number);
                }));

                // find job deadline
                preg_match($patterns['deadlinePattern'], $job, $matches);
                $deadline = $matches[1];

                // find job speciality
                preg_match($patterns['specialityPattern'], $job, $matches);
                $speciality = isset($matches[1]) ? html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5) : '';

                // find job workplace
                preg_match($patterns['workplacePattern'], $job, $matches);
                $workplace = isset($matches[1]) ? html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5) : '';

                // find job workplace
                preg_match($patterns['regionPattern'], $job, $matches);
                $region = isset($matches[1]) ? html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5) : '';

                $jobs [] = [
                    $deadline,
                    explode('/', $link)[2],
                    $contactPerson,
                    implode("\n", $emails),
                    implode("\n", $phoneNumbers),
                    $speciality,
                    $workplace,
                    $region,
                    $websiteUrl . $link
                ];
            }

            $page += 1;
        }
        if (count($jobs) >= 10) {
            Announcement::query()->where('id', '>', '0')->delete();
        } else {
            Announcement::query()->whereIn('id', $existingNumbers->take(count($jobs))->pluck('id')->toArray())->delete();
        }

        Announcement::query()->insert(array_map(function ($job) {
            return [
                'number' => $job[1],
                'link'   => $job[8]
            ];
        }, array_slice($jobs, 0, 10)));

        $this->info('storing');
        \GoogleClient::insertDataIntoSheet(array_reverse($jobs));
    }
}
