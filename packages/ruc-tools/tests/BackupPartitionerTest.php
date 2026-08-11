<?php

namespace RucTool\Tests;

use PHPUnit\Framework\TestCase;
use RucTool\Services\BackupPartitioner;

class BackupPartitionerTest extends TestCase
{
    private BackupPartitioner $partitioner;

    private string $workDir;

    protected function setUp(): void
    {
        $this->partitioner = new BackupPartitioner;
        $this->workDir = sys_get_temp_dir().'/ruc_tool_partitioner_test_'.uniqid();
        mkdir($this->workDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->workDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = "$dir/$entry";
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function makeSourceFile(int $bytes): string
    {
        $path = "{$this->workDir}/source.bin";
        $handle = fopen($path, 'wb');
        // Contenido no repetitivo para que un bug de "siempre corta en el
        // mismo offset" no pase desapercibido por casualidad. Se arma un
        // bloque base de 256 bytes distintos y se repite por chunks (no
        // byte a byte vía fwrite, demasiado lento para el caso de ~90 MiB).
        $block = implode('', array_map('chr', range(0, 255)));
        $remaining = $bytes;
        while ($remaining > 0) {
            $chunk = $remaining >= 256 ? $block : substr($block, 0, $remaining);
            fwrite($handle, $chunk);
            $remaining -= strlen($chunk);
        }
        fclose($handle);

        return $path;
    }

    public function test_split_of_two_hundred_fifty_bytes_into_hundred_byte_parts(): void
    {
        $source = $this->makeSourceFile(250);

        $parts = $this->partitioner->split($source, $this->workDir, 'source.bin', 100);

        $this->assertCount(3, $parts);
        $this->assertSame([100, 100, 50], array_column($parts, 'size_bytes'));
        $this->assertSame(
            ['source.bin.part0001', 'source.bin.part0002', 'source.bin.part0003'],
            array_column($parts, 'filename')
        );
        $this->assertSame([1, 2, 3], array_column($parts, 'index'));

        foreach ($parts as $part) {
            $this->assertFileExists("{$this->workDir}/{$part['filename']}");
            $this->assertSame($part['size_bytes'], filesize("{$this->workDir}/{$part['filename']}"));
            $this->assertSame($part['sha256'], hash_file('sha256', "{$this->workDir}/{$part['filename']}"));
        }
    }

    public function test_all_parts_except_last_are_exactly_part_size(): void
    {
        $source = $this->makeSourceFile(94371840 + 1); // part-size real (90 MiB) + 1 byte

        $parts = $this->partitioner->split($source, $this->workDir, 'source.bin', 94371840);

        $this->assertCount(2, $parts);
        $this->assertSame(94371840, $parts[0]['size_bytes']);
        $this->assertSame(1, $parts[1]['size_bytes']);
        $this->assertLessThanOrEqual(94371840, $parts[1]['size_bytes']);
    }

    public function test_exact_multiple_of_part_size_does_not_produce_empty_trailing_part(): void
    {
        $source = $this->makeSourceFile(200); // exactamente 2 partes de 100

        $parts = $this->partitioner->split($source, $this->workDir, 'source.bin', 100);

        $this->assertCount(2, $parts);
        $this->assertSame([100, 100], array_column($parts, 'size_bytes'));
    }

    public function test_part_names_are_zero_padded_and_sortable(): void
    {
        $source = $this->makeSourceFile(1000); // 100 bytes/parte -> 10 partes

        $parts = $this->partitioner->split($source, $this->workDir, 'source.bin', 100);
        $filenames = array_column($parts, 'filename');
        $sorted = $filenames;
        sort($sorted);

        $this->assertSame($sorted, $filenames, 'Los nombres deben quedar en orden lexicográfico = orden real de las partes.');
        $this->assertSame('source.bin.part0010', end($filenames));
    }

    public function test_splitting_empty_file_throws(): void
    {
        $source = $this->makeSourceFile(0);

        $this->expectException(\Exception::class);
        $this->partitioner->split($source, $this->workDir, 'source.bin', 100);
    }

    public function test_join_reconstructs_byte_identical_file(): void
    {
        $source = $this->makeSourceFile(250);
        $originalHash = hash_file('sha256', $source);

        $parts = $this->partitioner->split($source, $this->workDir, 'source.bin', 100);
        $partPaths = array_map(fn (array $p) => "{$this->workDir}/{$p['filename']}", $parts);

        $joined = "{$this->workDir}/joined.bin";
        $this->partitioner->join($partPaths, $joined);

        $this->assertSame($originalHash, hash_file('sha256', $joined));
        $this->assertSame(250, filesize($joined));
    }

    public function test_streaming_sha256_matches_joined_file_hash_without_writing_it(): void
    {
        $source = $this->makeSourceFile(250);
        $originalHash = hash_file('sha256', $source);

        $parts = $this->partitioner->split($source, $this->workDir, 'source.bin', 100);
        $partPaths = array_map(fn (array $p) => "{$this->workDir}/{$p['filename']}", $parts);

        $this->assertSame($originalHash, $this->partitioner->streamingSha256($partPaths));
    }

    public function test_join_throws_when_a_part_is_missing(): void
    {
        $source = $this->makeSourceFile(250);
        $parts = $this->partitioner->split($source, $this->workDir, 'source.bin', 100);
        $partPaths = array_map(fn (array $p) => "{$this->workDir}/{$p['filename']}", $parts);

        unlink($partPaths[1]); // simula part0002 faltante

        $this->expectException(\Exception::class);
        $this->partitioner->join($partPaths, "{$this->workDir}/joined.bin");
    }
}
