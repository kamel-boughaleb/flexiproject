<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/** Chaque texte de l'interface marqué t('…') / $t('…') a sa traduction anglaise dans lang/en.json. */
class TranslationsTest extends TestCase
{
    public function test_every_interface_text_has_an_english_translation(): void
    {
        $root = dirname(__DIR__, 2);
        $english = json_decode(file_get_contents("{$root}/lang/en.json"), true, flags: JSON_THROW_ON_ERROR);
        $missing = [];

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$root}/resources/js"));
        foreach ($files as $file) {
            $path = $file->getPathname();
            if (! preg_match('/\.(vue|ts)$/', $path) || str_contains($path, '/components/ui/')) {
                continue;
            }
            preg_match_all('/(?<![\w.])\$?tc?\(\s*(\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*")/s', file_get_contents($path), $matches);
            foreach ($matches[1] as $literal) {
                $key = stripcslashes(substr($literal, 1, -1));
                if (! array_key_exists($key, $english)) {
                    $missing[] = basename($path).' : '.$key;
                }
            }
        }

        $this->assertSame([], array_values(array_unique($missing)));
    }
}
