<?php

use ISO9660\Reader;
use PHPUnit\Framework\TestCase;

class ReaderTest extends TestCase
{
    private const isoPath = __DIR__ . '/fixtures/withRockRidge.iso';
    private const isoWithoutRockRidgePath = __DIR__ . '/fixtures/withoutRockRidge.iso';

    public function dataProvider listFiles() : array
    {
        return [
            [
                self::isoPath,
                [
                    '/dir1',
                    '/dir1/dir2',
                    '/dir1/dir2/dir3',
                    '/dir1/dir2/dir3/dir4',
                    '/dir1/dir2/dir3/dir4/dir5',
                    '/dir1/dir2/dir3/dir4/dir5/dir6',
                    '/dir1/dir2/dir3/dir4/dir5/dir6/dir7',
                    '/dir1/dir2/dir3/dir4/dir5/dir6/dir7/dir8',
                    '/dir1/dir2/dir3/dir4/dir5/dir6/dir7/dir8/dir9',
                    '/dir1/dir2/dir3/dir4/dir5/dir6/dir7/dir8/dir9/lorem.txt',
                    '/index.php',
                    '/lorem-symlink.txt',
                    '/Nulla-egestas-orci-eu-facilisis-viverra-augue-quam-ultrices-lectus-nec-ultrices-erat-mauris-at-sapien.txt',
                    '/rr_moved',
                ],
            ],
            [
                self::isoWithoutRockRidgePath,
                [
                    '/DIR1',
                    '/DIR1/DIR2',
                    '/DIR1/DIR2/DIR3',
                    '/DIR1/DIR2/DIR3/DIR4',
                    '/DIR1/DIR2/DIR3/DIR4/DIR5',
                    '/DIR1/DIR2/DIR3/DIR4/DIR5/DIR6',
                    '/DIR1/DIR2/DIR3/DIR4/DIR5/DIR6/DIR7',
                    '/INDEX.PHP',
                    '/NULLA_EG.TXT',
                ]
            ],
        ];
    }
    
    /**
     * @dataProvider dataProvider listFiles
     */
    public function test listFiles(string $isoPath, array $filesExpected)
    {
        $reader = new Reader($isoPath);
        $files = iterator_to_array($reader->listFiles());

        self::assertSame($filesExpected, $files);
    }

    public function test listFiles with prefix and slashes()
    {
        $reader = new Reader(self::isoPath);

        self::assertSame(
            iterator_to_array($reader->listFiles('/dir1/dir2/dir3')),
            iterator_to_array($reader->listFiles('/dir1/dir2/dir3/'))
        );

        self::assertSame(
            iterator_to_array($reader->listFiles('dir1/dir2/dir3')),
            iterator_to_array($reader->listFiles('/dir1/dir2/dir3'))
        );
    }

    public function test listFiles with maxDepth()
    {
        $reader = new Reader(self::isoPath);

        self::assertSame([
            '/dir1',
            '/dir1/dir2',
            '/index.php',
            '/lorem-symlink.txt',
            '/Nulla-egestas-orci-eu-facilisis-viverra-augue-quam-ultrices-lectus-nec-ultrices-erat-mauris-at-sapien.txt',
            '/rr_moved',
        ], iterator_to_array($reader->listFiles('', 2)));

        self::assertSame([
            '/dir1/dir2',
            '/dir1/dir2/dir3',
        ], iterator_to_array($reader->listFiles('/dir1', 2)));
    }

    public function test getFileContent()
    {
        $reader = new Reader(self::isoPath);

        $expected = <<<RAW
<?php

phpinfo();

RAW;

        self::assertSame($expected, $reader->getFileContent('/index.php'));
        self::assertSame($reader->getFileContent('index.php'), $reader->getFileContent('/index.php'));
    }

    public function test getFileContent with File object()
    {
        $reader = new Reader(self::isoPath);

        $expected = <<<RAW
<?php

phpinfo();

RAW;

        self::assertSame($expected, $reader->getFileContent($reader->getFile('index.php')));
    }

    public function test getFileContent with invalid input()
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('You must provide a string or a File object');

        $reader = new Reader(self::isoPath);
        $reader->getFileContent(987654321);
    }

    public function test getFileContent with unknown file()
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('File doest not exist');

        $reader = new Reader(self::isoPath);
        $reader->getFileContent('unknownFile');
    }

    public function test getFile ignores first slash()
    {
        $reader = new Reader(self::isoPath);

        self::assertSame(
            $reader->getFile('index.php')->getFullPath(),
            $reader->getFile('/index.php')->getFullPath()
        );

        self::assertSame(
            $reader->getFile('index.php')->getName(),
            $reader->getFile('/index.php')->getName()
        );
    }

    public function test getFile with unknown file()
    {
        $reader = new Reader(self::isoPath);

        self::assertNull($reader->getFile('unknownFile'));
    }
}
