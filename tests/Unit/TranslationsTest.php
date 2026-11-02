<?php

namespace Tests\Unit;

use App\Support\CategoryTemplate;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class TranslationsTest extends TestCase
{
    public function test_every_english_string_has_a_bangla_translation(): void
    {
        $bangla = json_decode(file_get_contents(lang_path('bn.json')), true, flags: JSON_THROW_ON_ERROR);

        $missing = array_values(array_filter(
            $this->usedStrings(),
            fn (string $key) => ! array_key_exists($key, $bangla),
        ));

        $this->assertSame([], $missing, 'Add these to lang/bn.json');
    }

    /** @return list<string> */
    private function usedStrings(): array
    {
        $paths = [];

        foreach ([app_path(), resource_path('views')] as $dir) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir)) as $file) {
                if (str_ends_with($file, '.php')) {
                    $paths[] = (string) $file;
                }
            }
        }

        $strings = CategoryTemplate::NAMES;

        foreach ($paths as $path) {
            preg_match_all('/(?:__|trans_choice)\(\s*([\'"])((?:\\\\.|(?!\1).)*)\1/s', file_get_contents($path), $matches);

            foreach ($matches[2] as $i => $string) {
                $strings[] = $matches[1][$i] === "'" ? str_replace("\\'", "'", $string) : $string;
            }
        }

        // Strings that are passed through __() indirectly (labels held in arrays).
        $strings = [...$strings, 'Photo or PDF of the bill', 'Contract or quote (photo or PDF)', 'Order', 'Paid', 'Pay by'];

        return array_values(array_unique($strings));
    }
}
