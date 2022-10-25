<?php

use ISO9660\Exception\PrimaryVolumeDescriptorNotFound;
use ISO9660\StreamWrapper;
use PHPUnit\Framework\Error\Warning;
use PHPUnit\Framework\TestCase;

class StreamWrapperTest extends TestCase
{
    private const isoPath = __DIR__ . '/fixtures/withRockRidge.iso';
    private const isoWithoutRockRidgePath = __DIR__ . '/fixtures/withoutRockRidge.iso';
    private const isoPathWithScheme = 'iso9660://'.self::isoPath;
    private const isoWithoutRockRidgePathWithScheme = 'iso9660://'.self::isoWithoutRockRidgePath;

    public static function setUpBeforeClass(): void
    {
        StreamWrapper::register();
    }

    public function test I can register the stream wrapper()
    {
        stream_wrapper_unregister('iso9660');
        self::assertFalse(in_array('iso9660', stream_get_wrappers()));

        StreamWrapper::register();
        self::assertTrue(in_array('iso9660', stream_get_wrappers()));

        StreamWrapper::register();
        self::assertTrue(in_array('iso9660', stream_get_wrappers()));
    }

    public function dataProvider list all files with recursive directory iterator() : array
    {
        return [
            [
                self::isoPath,
                <<<RAW
|-iso9660://fixtures/file.iso#/dir1
| \-iso9660://fixtures/file.iso#/dir1/dir2
|   \-iso9660://fixtures/file.iso#/dir1/dir2/dir3
|     \-iso9660://fixtures/file.iso#/dir1/dir2/dir3/dir4
|       \-iso9660://fixtures/file.iso#/dir1/dir2/dir3/dir4/dir5
|         \-iso9660://fixtures/file.iso#/dir1/dir2/dir3/dir4/dir5/dir6
|           \-iso9660://fixtures/file.iso#/dir1/dir2/dir3/dir4/dir5/dir6/dir7
|             \-iso9660://fixtures/file.iso#/dir1/dir2/dir3/dir4/dir5/dir6/dir7/dir8
|               \-iso9660://fixtures/file.iso#/dir1/dir2/dir3/dir4/dir5/dir6/dir7/dir8/dir9
|                 \-iso9660://fixtures/file.iso#/dir1/dir2/dir3/dir4/dir5/dir6/dir7/dir8/dir9/lorem.txt
|-iso9660://fixtures/file.iso#/index.php
|-iso9660://fixtures/file.iso#/lorem-symlink.txt
|-iso9660://fixtures/file.iso#/Nulla-egestas-orci-eu-facilisis-viverra-augue-quam-ultrices-lectus-nec-ultrices-erat-mauris-at-sapien.txt
\-iso9660://fixtures/file.iso#/rr_moved
RAW
            ],
            [
                self::isoWithoutRockRidgePath,
                <<<RAW
|-iso9660://fixtures/file.iso#/DIR1
| \-iso9660://fixtures/file.iso#/DIR1/DIR2
|   \-iso9660://fixtures/file.iso#/DIR1/DIR2/DIR3
|     \-iso9660://fixtures/file.iso#/DIR1/DIR2/DIR3/DIR4
|       \-iso9660://fixtures/file.iso#/DIR1/DIR2/DIR3/DIR4/DIR5
|         \-iso9660://fixtures/file.iso#/DIR1/DIR2/DIR3/DIR4/DIR5/DIR6
|           \-iso9660://fixtures/file.iso#/DIR1/DIR2/DIR3/DIR4/DIR5/DIR6/DIR7
|-iso9660://fixtures/file.iso#/INDEX.PHP
\-iso9660://fixtures/file.iso#/NULLA_EG.TXT
RAW
            ],
        ];
    }

    /**
     * @dataProvider dataProvider list all files with recursive directory iterator
     */
    public function test list all files with recursive directory iterator(string $isoPath, string $expected)
    {
        StreamWrapper::register();

        $iterator = new RecursiveTreeIterator(new RecursiveDirectoryIterator('iso9660://'.$isoPath.'#'));
        $treeLines = iterator_to_array($iterator);

        $expected = str_replace('fixtures/file.iso', $isoPath, $expected);
        self::assertEquals($expected, implode("\n", $treeLines));
    }

    public function test file_get_contents()
    {
        StreamWrapper::register();

        $expected = <<<RAW
<?php

phpinfo();

RAW;

        self::assertEquals($expected, file_get_contents(self::isoPathWithScheme.'#index.php'));
    }

    public function test file_get_contents on hidden file()
    {
        StreamWrapper::register();

        $expected = <<<RAW
I'm hidden!

RAW;

        $opts = [
            'iso9660' => [
                'showHiddenFiles'  => true,
            ]
        ];

        $context  = stream_context_create($opts);

        self::assertEquals($expected, file_get_contents(self::isoPathWithScheme.'#hiddenFile', false, $context));

        // Same file, without custom context : error
        $this->expectWarning();
        file_get_contents(self::isoPathWithScheme.'#hiddenFile');
    }

    public function test file_get_contents on symlink()
    {
        StreamWrapper::register();

        $expected = <<<RAW
Lorem ipsum dolor sit amet

RAW;

        self::assertEquals($expected, file_get_contents(self::isoPathWithScheme.'#lorem-symlink.txt'));
    }

    public function test file_get_contents on unknown file()
    {
        StreamWrapper::register();

        $this->expectWarning();
        file_get_contents(self::isoPathWithScheme.'#unknown.mp3');
    }

    public function test stream wrapper with non supported mode()
    {
        StreamWrapper::register();

        $this->expectWarning();
        fopen(self::isoPathWithScheme.'#index.php', 'w');
    }

    public function test stream wrapper with non supported function()
    {
        StreamWrapper::register();

        $this->expectWarning();
        mkdir(self::isoPathWithScheme.'#index.php');
    }

    public function test is_link()
    {
        StreamWrapper::register();

        self::assertFalse(is_link(self::isoPathWithScheme.'#index.php'));
        self::assertTrue(is_link(self::isoPathWithScheme.'#lorem-symlink.txt'));
    }

    public function test filesize()
    {
        StreamWrapper::register();

        self::assertEquals(18, filesize(self::isoPathWithScheme.'#index.php'));
        self::assertEquals(27, filesize(self::isoPathWithScheme.'#lorem-symlink.txt'));
    }

    public function test file_exists()
    {
        StreamWrapper::register();

        self::assertTrue(file_exists(self::isoPathWithScheme.'#index.php'));
        self::assertTrue(file_exists(self::isoPathWithScheme.'#lorem-symlink.txt'));
        self::assertFalse(file_exists(self::isoPathWithScheme.'#unknown.mp3'));
    }

    public function test stat()
    {
        StreamWrapper::register();

        self::assertEquals([
            0  => 0,            'dev'       => 0,
            1  => 0,            'ino'       => 0,
            2  => 0100664,      'mode'      => 0100664,
            3  => 1,            'nlink'     => 1,
            4  => 1000,         'uid'       => 1000,
            5  => 1000,         'gid'       => 1000,
            6  => -1,           'rdev'      => -1,
            7  => 18,           'size'      => 18,
            8  => 1530799053,   'atime'     => 1530799053,
            9  => 1529599503,   'mtime'     => 1529599503,
            10 => 0,            'ctime'     => 0,
            11 => -1,           'blksize'   => -1,
            12 => -1,           'blocks'    => -1,
        ], stat(self::isoPathWithScheme.'#index.php'));

        self::assertEquals([
            0  => 0,            'dev'       => 0,
            1  => 0,            'ino'       => 0,
            2  => 0100664,      'mode'      => 0100664,
            3  => 1,            'nlink'     => 1,
            4  => 1000,         'uid'       => 1000,
            5  => 1000,         'gid'       => 1000,
            6  => -1,           'rdev'      => -1,
            7  => 27,           'size'      => 27,
            8  => 1530704780,   'atime'     => 1530704780,
            9  => 1529474632,   'mtime'     => 1529474632,
            10 => 0,            'ctime'     => 0,
            11 => -1,           'blksize'   => -1,
            12 => -1,           'blocks'    => -1,
        ], stat(self::isoPathWithScheme.'#lorem-symlink.txt'));
    }

    public function test lstat()
    {
        StreamWrapper::register();

        self::assertEquals([
            0  => 0,            'dev'       => 0,
            1  => 0,            'ino'       => 0,
            2  => 0100664,      'mode'      => 0100664,
            3  => 1,            'nlink'     => 1,
            4  => 1000,         'uid'       => 1000,
            5  => 1000,         'gid'       => 1000,
            6  => -1,           'rdev'      => -1,
            7  => 18,           'size'      => 18,
            8  => 1530799053,   'atime'     => 1530799053,
            9  => 1529599503,   'mtime'     => 1529599503,
            10 => 0,            'ctime'     => 0,
            11 => -1,           'blksize'   => -1,
            12 => -1,           'blocks'    => -1,
        ], lstat(self::isoPathWithScheme.'#index.php'));

        self::assertEquals([
            0  => 0,            'dev'       => 0,
            1  => 0,            'ino'       => 0,
            2  => 0120777,      'mode'      => 0120777,
            3  => 1,            'nlink'     => 1,
            4  => 1000,         'uid'       => 1000,
            5  => 1000,         'gid'       => 1000,
            6  => -1,           'rdev'      => -1,
            7  => 0,            'size'      => 0,
            8  => 1530795122,   'atime'     => 1530795122,
            9  => 1529474706,   'mtime'     => 1529474706,
            10 => 0,            'ctime'     => 0,
            11 => -1,           'blksize'   => -1,
            12 => -1,           'blocks'    => -1,
        ], lstat(self::isoPathWithScheme.'#lorem-symlink.txt'));
    }

    public function test stat on unknown file()
    {
        StreamWrapper::register();

        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('File does not exist');
        stat(self::isoPathWithScheme.'#unknown.mp3');
    }

    public function test fopen and basic operations on stream()
    {
        StreamWrapper::register();

        $f = fopen(self::isoPathWithScheme.'#index.php', 'r');
        self::assertTrue(is_resource($f));
        self::assertFalse(feof($f));

        self::assertEquals('<?php', fread($f, 5));
        self::assertEquals(5, ftell($f));

        fseek($f, 2, SEEK_CUR); // skip 2 line break

        self::assertEquals('phpinfo();', fread($f, 10));

        rewind($f);

        self::assertEquals(0, ftell($f));
        self::assertEquals('<?php', fread($f, 5));
        self::assertEquals(5, ftell($f));

        self::assertFalse(feof($f));
        fseek($f, 0, SEEK_END); // go to the end
        self::assertTrue(feof($f));

        self::assertTrue(fclose($f));
    }

    public function test stream wrapper with non compliant file()
    {
        StreamWrapper::register();

        self::expectException(PrimaryVolumeDescriptorNotFound::class);
        fopen('iso9660://'.__FILE__, 'r');
    }

    public function test I can open two different files at the same time()
    {
        StreamWrapper::register();

        $one = fopen(self::isoWithoutRockRidgePathWithScheme.'#INDEX.PHP', 'r');
        $oneBis = fopen(self::isoWithoutRockRidgePathWithScheme.'#INDEX.PHP', 'r');
        $two = fopen(self::isoPathWithScheme.'#lorem-symlink.txt', 'r');

        self::assertSame('<?php', fread($one, 5));
        self::assertSame('<?php', fread($oneBis, 5));
        self::assertSame('Lorem', fread($two, 5));

        fclose($one);
        fclose($oneBis);
        fclose($two);
    }
}
