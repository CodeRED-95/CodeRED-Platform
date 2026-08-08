<?php

namespace RucTool\Tests;

use PHPUnit\Framework\TestCase;
use RucTool\Services\BackupPartitioner;
use RucTool\Services\ManifestService;

class ManifestServiceTest extends TestCase
{
    private ManifestService $manifestService;
    private BackupPartitioner $partitioner;
    private string $workDir;

    protected function setUp(): void
    {
        $this->manifestService = new ManifestService();
        $this->partitioner = new BackupPartitioner();
        $this->workDir = sys_get_temp_dir() . '/ruc_tool_manifest_test_' . uniqid();
        mkdir($this->workDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->workDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
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

    /** Genera un split real de 250 bytes / partes de 100 y su manifest, para reutilizar en varios tests. */
    private function makeValidManifest(): array
    {
        $sourcePath = "{$this->workDir}/source.bin";
        file_put_contents($sourcePath, str_repeat('a', 200) . str_repeat('b', 50));
        $checksum = hash_file('sha256', $sourcePath);

        $parts = $this->partitioner->split($sourcePath, $this->workDir, 'source.bin', 100);

        return $this->manifestService->build('source.bin', 12345, 250, 100, $checksum, $parts, '2.3.0-test');
    }

    public function testValidManifestPassesValidation(): void
    {
        $manifest = $this->makeValidManifest();

        $errors = $this->manifestService->validate($manifest, $this->workDir, $this->partitioner);

        $this->assertSame([], $errors);
    }

    public function testWriteAndReadRoundTrip(): void
    {
        $manifest = $this->makeValidManifest();
        $path = "{$this->workDir}/test.manifest.json";

        $this->manifestService->write($path, $manifest);
        $read = $this->manifestService->read($path);

        $this->assertSame($manifest, $read);
        $this->assertSame([], $this->manifestService->validate($read, $this->workDir, $this->partitioner));
    }

    public function testManifestNeverContainsCredentials(): void
    {
        $manifest = $this->makeValidManifest();
        $json = json_encode($manifest);

        foreach (['password', 'PGPASSWORD', 'host', 'username', 'token', '.env'] as $sensitiveKey) {
            $this->assertStringNotContainsStringIgnoringCase($sensitiveKey, $json);
        }
    }

    public function testMissingPartIsDetected(): void
    {
        $manifest = $this->makeValidManifest();
        unlink("{$this->workDir}/{$manifest['parts'][1]['filename']}");

        $errors = $this->manifestService->validate($manifest, $this->workDir, $this->partitioner);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Falta', $errors[0]);
        $this->assertStringContainsString($manifest['parts'][1]['filename'], $errors[0]);
    }

    public function testCorruptedPartChecksumIsDetected(): void
    {
        $manifest = $this->makeValidManifest();
        $partPath = "{$this->workDir}/{$manifest['parts'][0]['filename']}";
        // Corromper sin cambiar el tamaño, para aislar específicamente la
        // detección por checksum (no por tamaño incorrecto).
        $contents = file_get_contents($partPath);
        $contents[0] = $contents[0] === 'x' ? 'y' : 'x';
        file_put_contents($partPath, $contents);

        $errors = $this->manifestService->validate($manifest, $this->workDir, $this->partitioner);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Checksum incorrecto', $errors[0]);
    }

    public function testIncompleteLastPartIsDetectedByFinalShaMismatch(): void
    {
        $manifest = $this->makeValidManifest();
        $lastPart = $manifest['parts'][count($manifest['parts']) - 1];
        $partPath = "{$this->workDir}/{$lastPart['filename']}";
        // Truncar la última parte (parte "incompleta"): el tamaño ya no
        // coincide con el manifest, que es justo lo que debe detectarse.
        file_put_contents($partPath, substr(file_get_contents($partPath), 0, -5));

        $errors = $this->manifestService->validate($manifest, $this->workDir, $this->partitioner);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Tamaño incorrecto', $errors[0]);
    }

    public function testWrongOrderIsDetected(): void
    {
        $manifest = $this->makeValidManifest();
        // Duplicar el index 1 y quitar el 2: rompe la secuencia 1..N.
        $manifest['parts'][1]['index'] = 1;

        $errors = $this->manifestService->validate($manifest, $this->workDir, $this->partitioner);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('orden correcto', $errors[0]);
    }

    public function testCorruptManifestMissingKeyIsDetected(): void
    {
        $manifest = $this->makeValidManifest();
        unset($manifest['sha256']);

        $errors = $this->manifestService->validate($manifest, $this->workDir, $this->partitioner);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('sha256', $errors[0]);
    }

    public function testUnsupportedFormatVersionIsRejected(): void
    {
        $manifest = $this->makeValidManifest();
        $manifest['format_version'] = 999;

        $errors = $this->manifestService->validate($manifest, $this->workDir, $this->partitioner);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('format_version', $errors[0]);
    }

    public function testTotalPartsMismatchIsDetected(): void
    {
        $manifest = $this->makeValidManifest();
        $manifest['total_parts'] = count($manifest['parts']) + 5;

        $errors = $this->manifestService->validate($manifest, $this->workDir, $this->partitioner);

        $this->assertNotEmpty($errors);
    }

    public function testReassembledFileMatchesOriginalShaEndToEnd(): void
    {
        $sourcePath = "{$this->workDir}/source.bin";
        file_put_contents($sourcePath, random_bytes(1000));
        $originalSha = hash_file('sha256', $sourcePath);

        $parts = $this->partitioner->split($sourcePath, $this->workDir, 'source.bin', 137); // tamaño "raro" a propósito
        $manifest = $this->manifestService->build('source.bin', 0, 1000, 137, $originalSha, $parts, '2.3.0-test');

        $this->assertSame([], $this->manifestService->validate($manifest, $this->workDir, $this->partitioner));

        $joined = "{$this->workDir}/joined.bin";
        $this->partitioner->join(
            array_map(fn (array $p) => "{$this->workDir}/{$p['filename']}", $manifest['parts']),
            $joined
        );
        $this->assertSame($originalSha, hash_file('sha256', $joined));
    }
}
