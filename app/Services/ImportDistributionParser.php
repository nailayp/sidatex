<?php

namespace App\Services;

use App\Services\Contracts\DbfParserInterface;
use App\Services\Parsers\ImportDistributionDbfParser;
use Generator;

/**
 * Adapter kompatibilitas mundur untuk ImportDistributionParser lama.
 * Mewarisi implementasi baru ImportDistributionDbfParser agar seluruh controller/service
 * yang memanggil ImportDistributionParser secara otomatis mendapatkan fitur bebas float.
 */
class ImportDistributionParser implements DbfParserInterface
{
    protected ImportDistributionDbfParser $parser;

    public function __construct(DbfReaderService $dbfReader)
    {
        $this->parser = new ImportDistributionDbfParser($dbfReader);
    }

    public function inspect(string $filePath, ?string $originalFilename = null): array
    {
        return $this->parser->inspect($filePath, $originalFilename);
    }

    public function parseStream(string $filePath, ?int $limit = null, int $offset = 0, ?string $originalFilename = null): Generator
    {
        return $this->parser->parseStream($filePath, $limit, $offset, $originalFilename);
    }
}
