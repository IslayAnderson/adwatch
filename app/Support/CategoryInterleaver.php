<?php

namespace App\Support;

use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Orders items so that no two neighbours share a category, while still looking shuffled.
 *
 * Each step picks a category at random, weighted by how many items it has left, excluding the previous
 * category. If one category would otherwise become impossible to keep apart (it holds at least half of
 * what's left), it is picked first. When separation is impossible (e.g. everything is one category)
 * it degrades gracefully and just lets them sit together.
 */
class CategoryInterleaver
{
    /**
     * @param  array<int, int>  $itemCategories  item id => category id
     * @return list<int> item ids in display order
     */
    public static function order(array $itemCategories, int $seed): array
    {
        $random = new Randomizer(new Mt19937($seed));

        $queues = [];
        foreach ($itemCategories as $item => $category) {
            $queues[$category][] = $item;
        }
        ksort($queues);
        foreach ($queues as $category => $items) {
            $queues[$category] = $random->shuffleArray($items);
        }

        $order = [];
        $remaining = count($itemCategories);
        $previous = null;

        while ($remaining > 0) {
            $candidates = array_filter(array_keys($queues), fn ($c) => $c !== $previous && $queues[$c]);
            if (! $candidates) {
                $candidates = [$previous]; // only one category left: can't be helped
            }

            $pick = null;
            foreach ($candidates as $category) {
                if (count($queues[$category]) * 2 >= $remaining) {
                    $pick = $category; // must lead now or it will end up doubled later
                    break;
                }
            }

            if ($pick === null) {
                $roll = $random->getInt(1, array_sum(array_map(fn ($c) => count($queues[$c]), $candidates)));
                foreach ($candidates as $category) {
                    $roll -= count($queues[$category]);
                    if ($roll <= 0) {
                        $pick = $category;
                        break;
                    }
                }
            }

            $order[] = array_pop($queues[$pick]);
            $previous = $pick;
            $remaining--;
        }

        return $order;
    }
}
