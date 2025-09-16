<?php

namespace App\Services\File;

use Generator;
use PrinsFrank\PdfParser\PdfParser as VendorPdfParser;

class PdfParser
{
    private VendorPdfParser $parser;

    public function __construct()
    {
        $this->parser = new VendorPdfParser();
    }

    public function stream(string $path): Generator
    {
        $document = $this->parser->parseFile(
            filePath: storage_path('app/private/' . $path),
            useInMemoryStream: false,
        );
        $pages = $document->getPages();
        foreach ($pages as $page) {
            $text = $page->getText();
            // It cannot properly parse certain PDFs and this loop goes into an infinite loop. This is the hotfix.
            // However, there are PDFs that start with an image as a cover. We don't `break` in those cases.
            if (trim($text) === '') {
                $images = $page->getImages();
                if (count($images) > 0) {
                    continue;
                }
                break;
            }
            yield $text;
        }
    }
}
