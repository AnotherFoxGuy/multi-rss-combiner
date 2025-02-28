<?php declare(strict_types=1);

namespace MultiRssCombiner\Provider;

use MultiRssCombiner\Value\Item;

class RssCache implements CacheProvider
{
    /** @var Item[] */
    private array $cache = [];

    public function __construct(string $fileName, int $limit)
    {
        $cacheFile = sprintf('%s%s', getcwd(), $fileName);

        if (!file_exists($cacheFile)) {
            return;
        }

        $cache = file_get_contents($cacheFile);

        $xml = simplexml_load_string($cache, 'SimpleXMLElement', LIBXML_NOCDATA);

        $json = json_encode($xml);
        $cacheContent = json_decode($json, true);

        if (!isset($cacheContent['item'])) {
            return;
        }

        for ($i = 0; $i < $limit; ++$i) {
            if (!isset($cacheContent['item'][$i])) {
                break;
            }

            $item = $cacheContent['item'][$i];

            $date = new \DateTime($item['pubDate']);

            if (gettype($item['description']) == "array"){
                $description = '';
            }else{
                $description = $item['description'];
            }

            $this->cache[] = new Item(
                $item['channelName'],
                $item['title'],
                $description,
                $item['link'],
                $item['guid'],
                \is_array($item['image']) ? null : $item['image'],
                $date
            );
        }
    }

    /**
     * @return Item[]
     */
    public function get(): array
    {
        return $this->cache;
    }
}
