<?php

namespace Tests\Unit;

use App\Support\CategoryInterleaver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CategoryInterleaverTest extends TestCase
{
    /** Build item id => category id from [category => how many]. */
    private function items(array $sizes): array
    {
        $items = [];
        foreach ($sizes as $category => $count) {
            for ($i = 0; $i < $count; $i++) {
                $items[count($items) + 1] = $category;
            }
        }

        return $items;
    }

    public static function distributions(): array
    {
        return [
            'like the real catalogue' => [[1 => 2228, 2 => 1428, 3 => 973, 4 => 931, 5 => 824, 6 => 803, 7 => 776, 8 => 762, 9 => 759, 10 => 747, 11 => 637, 12 => 223]],
            'one category is exactly half' => [[1 => 50, 2 => 30, 3 => 20]],
            'odd total, majority by one' => [[1 => 51, 2 => 50]],
            'two equal' => [[1 => 40, 2 => 40]],
            'many tiny' => [[1 => 1, 2 => 1, 3 => 1, 4 => 5, 5 => 3]],
        ];
    }

    #[DataProvider('distributions')]
    public function test_no_two_neighbours_share_a_category(array $sizes): void
    {
        $items = $this->items($sizes);

        foreach ([1, 42, 987654321] as $seed) {
            $order = CategoryInterleaver::order($items, $seed);

            $this->assertEqualsCanonicalizing(array_keys($items), $order, 'every item appears exactly once');
            for ($i = 1; $i < count($order); $i++) {
                $this->assertNotSame($items[$order[$i - 1]], $items[$order[$i]], "positions $i-1 and $i share a category (seed $seed)");
            }
        }
    }

    public function test_same_seed_gives_same_order_and_different_seeds_differ(): void
    {
        $items = $this->items([1 => 30, 2 => 30, 3 => 30]);

        $this->assertSame(CategoryInterleaver::order($items, 7), CategoryInterleaver::order($items, 7));
        $this->assertNotSame(CategoryInterleaver::order($items, 7), CategoryInterleaver::order($items, 8));
    }

    public function test_impossible_cases_still_return_everything(): void
    {
        $items = $this->items([1 => 10, 2 => 1]);

        $this->assertCount(11, CategoryInterleaver::order($items, 1));
    }
}
