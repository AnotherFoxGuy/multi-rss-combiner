<?php declare(strict_types=1);

namespace MultiRssCombiner;

use DOMDocument;
use MultiRssCombiner\Manager\RssCache as RssCacheManager;
use MultiRssCombiner\Provider\ChannelConfiguration;
use MultiRssCombiner\Provider\GeneralConfiguration;
use MultiRssCombiner\Provider\RssCache as RssCacheProvider;
use MultiRssCombiner\Renderer\PageRenderer;
use MultiRssCombiner\Renderer\RssRenderer;
use MultiRssCombiner\Value\Item;
use SimplePie;
use Wolfcast\BrowserDetection;

class App
{
    public const APP_RSS_CACHE_FILE = '/cache/cache.xml';

    public const APP_CONFIGURATION_FILE = '/config/general.ini';

    public const APP_PUBLIC_FILES_DIR = '/public/';

    public const APP_CHANNEL_CONFIGURATION_PATH = '/config/';

    public const NAMESPACE_MRSS = 'http://search.yahoo.com/mrss/';

    public function buildView(bool $showDefault = true): void
    {
        $configuration = new GeneralConfiguration(self::APP_CONFIGURATION_FILE);
        $cache = new RssCacheProvider(self::APP_RSS_CACHE_FILE, $configuration->get()->getLimit());
        $browser = new BrowserDetection();

        // detect if client belongs to the one of the supported web browsers or is just an RSS reader
        if (BrowserDetection::BROWSER_UNKNOWN === $browser->getName() || !$showDefault) {
            $template = new RssRenderer();

            header('Content-Type: application/rss+xml; charset=utf-8');
        } else {
            $template = new PageRenderer();
        }

        $template->display(
            $configuration->get(),
            $cache->get()
        );
    }

    public function buildCache(): void
    {
        $channels = new ChannelConfiguration(self::APP_CHANNEL_CONFIGURATION_PATH);

        $feed = new SimplePie\SimplePie();
        $feed->enable_order_by_date(true);
        $feed->force_feed(true);
        $feed->enable_cache(false);

        $items = [];

        // fetch RSS details
        foreach ($channels->getAll() as $channel) {
            printf('Fetching %s (%s)<br>', $channel->getName(), $channel->getUrl());

            $feed->set_feed_url($channel->getUrl());

            if (!$feed->init()) {
                break;
            }

            $feedItems = $feed->get_items();

            if (!$feedItems) {
                break;
            }

            foreach ($feedItems as $item) {
                $date = new \DateTime($item->get_date());

                if (str_contains($item->get_id(), "yt:video:")) {
                    // Stupid yt feed handling
                    $image = $item->data["child"]["http://search.yahoo.com/mrss/"]["group"][0]["child"]["http://search.yahoo.com/mrss/"]["thumbnail"][0]["attribs"][""]["url"];
                    $description = $item->data["child"]["http://search.yahoo.com/mrss/"]["group"][0]["child"]["http://search.yahoo.com/mrss/"]["description"][0]["data"];
                    $description = htmlspecialchars(nl2br($description));
                } else {
                    $image = '';
                    if (null !== $item->get_content()) {
                        $image = $this->getFirstImage($item->get_content());
                    }

                    $description = $item->get_description();
                    if (null !== $description) {
                        $description = preg_replace('/<img[^>]+\>/i', '', $description);
                    }

                }
                $item = new Item(
                    $channel->getName(),
                    $item->get_title() ?? '',
                    $description ?? '',
                    $item->get_link() ?? '',
                    $item->get_id() ?? '',
                    $image ?? '',
                    $date
                );


                $items[] = $item;
            }
        }

        // reorder items
        usort($items, function ($a, $b) {
            //return $b->getPubDate() <=> $a->getPubDate();
            return $a->getPubDate() > $b->getPubDate() ? -1 : 1;
        });

        // store everything in cache
        $cache = new RssCacheManager(self::APP_RSS_CACHE_FILE);

        foreach ($items as $item) {
            $cache->add($item);
        }

        $cache->save();
    }

    private function getFirstImage(string $content): ?string
    {
        $dom = new DOMDocument();
        @$dom->loadHTML($content);

        $links = [];
        $images = $dom->getElementsByTagName('img');

        foreach ($images as $singleImage) {
            $src = $singleImage->getAttribute('src');

            $links[] = $src;
        }

        if (\count($links) > 0) {
            return $links[0];
        }

        return null;
    }
}
