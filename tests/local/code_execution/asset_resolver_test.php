<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace block_ai_chat\local\code_execution;

/**
 * Tests for the asset resolver of the code execution libraries.
 *
 * @package    block_ai_chat
 * @copyright  2026 ISB Bayern
 * @author     Dr. Peter Mayer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(asset_resolver::class)]
final class asset_resolver_test extends \basic_testcase {
    /** @var string temporary thirdparty directory */
    private string $basedir;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->basedir = make_request_directory() . '/thirdparty';
        mkdir($this->basedir . '/pyodide/sub', 0777, true);
        mkdir($this->basedir . '/other');
        file_put_contents($this->basedir . '/pyodide/pyodide.asm.wasm', 'wasm');
        file_put_contents($this->basedir . '/pyodide/numpy-2.4.6-cp314-cp314-pyemscripten_2026_0_wasm32.whl', 'whl');
        file_put_contents($this->basedir . '/pyodide/LICENSE', 'license');
        file_put_contents($this->basedir . '/pyodide/.hidden.js', 'hidden');
        file_put_contents($this->basedir . '/pyodide/sub/nested.js', 'nested');
        file_put_contents($this->basedir . '/other/file.js', 'other');
        file_put_contents($this->basedir . '/secret.json', 'secret');
    }

    /**
     * Tests that only allowed files of allowed libraries are resolved.
     *
     * @param string $relativepath the requested path
     * @param string|null $expected the expected path relative to the base dir, null if it must not be served
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('resolve_provider')]
    public function test_resolve(string $relativepath, ?string $expected): void {
        $resolver = new asset_resolver($this->basedir);
        $expectedpath = $expected === null ? null : realpath($this->basedir . '/' . $expected);
        $this->assertSame($expectedpath, $resolver->resolve($relativepath));
    }

    /**
     * Data provider for test_resolve.
     *
     * @return array test cases
     */
    public static function resolve_provider(): array {
        return [
            'wasm file' => ['pyodide/pyodide.asm.wasm', 'pyodide/pyodide.asm.wasm'],
            'wheel' => [
                'pyodide/numpy-2.4.6-cp314-cp314-pyemscripten_2026_0_wasm32.whl',
                'pyodide/numpy-2.4.6-cp314-cp314-pyemscripten_2026_0_wasm32.whl',
            ],
            'missing file' => ['pyodide/missing.js', null],
            'extension not allowed' => ['pyodide/LICENSE', null],
            'hidden file' => ['pyodide/.hidden.js', null],
            'nested file' => ['pyodide/sub/nested.js', null],
            'library not allowed' => ['other/file.js', null],
            'traversal' => ['pyodide/../secret.json', null],
            'traversal into library' => ['rdkit/../pyodide/pyodide.asm.wasm', null],
            'absolute path' => ['/pyodide/pyodide.asm.wasm', null],
            'empty' => ['', null],
        ];
    }

    /**
     * Tests the mime type mapping.
     */
    public function test_get_mimetype(): void {
        $this->assertSame('application/wasm', asset_resolver::get_mimetype('pyodide.asm.wasm'));
        $this->assertSame('text/javascript', asset_resolver::get_mimetype('pyodide.asm.mjs'));
        $this->assertSame('application/zip', asset_resolver::get_mimetype('numpy.whl'));
        $this->assertNull(asset_resolver::get_mimetype('index.php'));
        $this->assertNull(asset_resolver::get_mimetype('LICENSE'));
    }

    /**
     * Tests that all bundled library files are served.
     */
    public function test_bundled_files_are_resolvable(): void {
        global $CFG;
        $resolver = new asset_resolver();
        $lock = json_decode(file_get_contents($CFG->dirroot . '/blocks/ai_chat/thirdparty/pyodide/pyodide-lock.json'), true);
        $files = ['pyodide/pyodide.js', 'pyodide/pyodide.asm.mjs', 'pyodide/pyodide.asm.wasm', 'pyodide/python_stdlib.zip',
            'pyodide/pyodide-lock.json', 'rdkit/RDKit_minimal.js', 'rdkit/RDKit_minimal.wasm'];
        foreach ($lock['packages'] as $package) {
            $files[] = 'pyodide/' . $package['file_name'];
        }
        foreach ($files as $file) {
            $this->assertNotNull($resolver->resolve($file), $file);
        }
    }
}
