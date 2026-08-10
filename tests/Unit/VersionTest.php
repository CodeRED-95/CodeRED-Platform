<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Version;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class VersionTest extends TestCase
{
    /** @return array<string, array{0: string, 1: string, 2: string}> */
    public static function bumpProvider(): array
    {
        return [
            'patch para correcciones' => ['3.4.0', 'patch', '3.4.1'],
            'minor reinicia patch' => ['3.4.7', 'minor', '3.5.0'],
            'major reinicia minor y patch' => ['3.4.7', 'major', '4.0.0'],
            'patch sobre cero' => ['0.0.0', 'patch', '0.0.1'],
            'minor con dos digitos' => ['1.19.3', 'minor', '1.20.0'],
            'major con dos digitos' => ['9.9.9', 'major', '10.0.0'],
        ];
    }

    /**
     * @dataProvider bumpProvider
     */
    public function test_bump_follows_semver(string $current, string $type, string $expected): void
    {
        $this->assertSame($expected, Version::bump($current, $type));
    }

    public function test_bump_rejects_unknown_type(): void
    {
        $this->expectException(RuntimeException::class);
        Version::bump('3.4.0', 'mayor');
    }

    public function test_bump_rejects_non_semver_input(): void
    {
        $this->expectException(RuntimeException::class);
        Version::bump('3.4', 'patch');
    }

    /** @return array<string, array{0: string, 1: bool}> */
    public static function validationProvider(): array
    {
        return [
            'basica' => ['3.4.0', true],
            'con prerelease' => ['3.4.0-beta.1', true],
            'con build' => ['3.4.0+20260810', true],
            'sin patch' => ['3.4', false],
            'con prefijo v' => ['v3.4.0', false],
            'vacia' => ['', false],
            'texto' => ['tres.cuatro.cero', false],
        ];
    }

    /**
     * @dataProvider validationProvider
     */
    public function test_validation_accepts_only_semver(string $version, bool $expected): void
    {
        $this->assertSame($expected, Version::isValid($version));
    }

    public function test_parts_discards_prerelease_and_build_metadata(): void
    {
        $this->assertSame([3, 4, 0], Version::parts('3.4.0-beta.1+build5'));
    }

    public function test_write_rejects_invalid_version_without_touching_the_file(): void
    {
        $before = (string) file_get_contents(Version::sourcePath());

        try {
            Version::write('no-es-semver');
            $this->fail('Se esperaba una excepción para una versión inválida.');
        } catch (RuntimeException) {
            // esperado
        }

        $this->assertSame($before, (string) file_get_contents(Version::sourcePath()));
    }

    public function test_current_reads_composer_json(): void
    {
        Version::forget();

        $composer = json_decode((string) file_get_contents(Version::sourcePath()), true);

        $this->assertIsArray($composer);
        $this->assertSame($composer['extra']['version'], Version::current());
    }
}
