<?php

namespace App\Services;

use App\Services\Contracts\DbfParserInterface;
use App\Services\Parsers\ExportDistributionDbfParser;
use Generator;

/**
 * Adapter kompatibilitas mundur untuk ExportDistributionParser lama.
 * Mewarisi implementasi baru ExportDistributionDbfParser agar seluruh controller/service
 * yang memanggil ExportDistributionParser secara otomatis mendapatkan fitur bebas float.
 */
class ExportDistributionParser implements DbfParserInterface
{
    protected ExportDistributionDbfParser $parser;

    public function __construct(DbfReaderService $dbfReader)
    {
        $this->parser = new ExportDistributionDbfParser($dbfReader);
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
