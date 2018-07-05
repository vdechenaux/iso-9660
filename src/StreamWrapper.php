<?php

namespace ISO9660;

use ISO9660\Filesystem\File;

final class StreamWrapper
{
    /**
     * @var Reader
     */
    private $reader;

    /**
     * @var string
     */
    private static $lastFileName;

    /**
     * @var array
     */
    private static $lastContextOptions;

    /**
     * @var Reader
     */
    private static $lastReader;

    /**
     * @var File
     */
    private $internalFile;

    /**
     * @var int
     */
    private $readPosition = 0;

    /**
     * @var \Generator
     */
    private $readDirIterator;

    /**
     * @var resource Magically set by PHP
     */
    public $context;

    public static function register()
    {
        if (in_array('iso9660', stream_get_wrappers())) {
            stream_wrapper_unregister('iso9660');
        }

        stream_wrapper_register('iso9660', self::class);
    }

    public function stream_open($path, $mode, $options, &$opened_path)
    {
        if ($mode !== 'r' && $mode !== 'rb') {
            if ($options & STREAM_REPORT_ERRORS) {
                trigger_error('Only reading is supported', E_USER_WARNING);
            }

            return false;
        }

        $this->decodeUrl($path);

        if ($this->internalFile === null) {
            if ($options & STREAM_REPORT_ERRORS) {
                trigger_error('File doest not exist', E_USER_WARNING);
            }

            return false;
        }

        return true;
    }

    public function stream_close()
    {
        $this->internalFile = $this->reader = $this->readDirIterator = null;
    }

    public function stream_read($count)
    {
        $data = $this->reader->getFileContent($this->internalFile, $this->readPosition, $count);
        $this->readPosition += strlen($data);

        return $data;
    }

    public function stream_stat()
    {
        $stat = [];

        $stat[0] = $stat['dev'] = 0;
        $stat[1] = $stat['ino'] = 0;
        $stat[2] = $stat['mode'] = $this->internalFile->getMode();
        $stat[3] = $stat['nlink'] = $this->internalFile->getHardLinksCount();
        $stat[4] = $stat['uid'] = $this->internalFile->getUserId();
        $stat[5] = $stat['gid'] = $this->internalFile->getGroupId();
        $stat[6] = $stat['rdev'] = -1;
        $stat[7] = $stat['size'] = $this->internalFile->getSize();
        $stat[8] = $stat['atime'] = $this->internalFile->getAtime() ? $this->internalFile->getAtime()->getTimestamp() : 0;
        $stat[9] = $stat['mtime'] = $this->internalFile->getMtime() ? $this->internalFile->getMtime()->getTimestamp() : 0;
        $stat[10] = $stat['ctime'] = $this->internalFile->getCtime() ? $this->internalFile->getCtime()->getTimestamp() : 0;
        $stat[11] = $stat['blksize'] = -1;
        $stat[12] = $stat['blocks'] = -1;

        return $stat;
    }

    public function url_stat($path, $flags)
    {
        $this->decodeUrl($path, !($flags & STREAM_URL_STAT_LINK));

        if ($this->internalFile === null) {
            if ($flags & STREAM_URL_STAT_QUIET) {
                return false;
            } else {
                throw new \InvalidArgumentException('File does not exist');
            }
        }

        return $this->stream_stat();
    }

    public function stream_eof()
    {
        return $this->readPosition >= $this->internalFile->getSize();
    }

    public function stream_seek($offset, $whence = SEEK_SET)
    {
        if ($whence == SEEK_SET) {
            $this->readPosition = $offset;
        } elseif ($whence == SEEK_CUR) {
            $this->readPosition += $offset;
        } elseif ($whence == SEEK_END) {
            $this->readPosition = $this->internalFile->getSize() + $offset;
        } else {
            return false;
        }

        return !$this->stream_eof();
    }

    public function stream_tell()
    {
        return $this->readPosition;
    }

    public function dir_opendir($path, $options)
    {
        $this->decodeUrl($path);
        $prefix = $this->internalFile ? $this->internalFile->getFullPath() : '';
        $this->readDirIterator = $this->reader->listFiles($prefix, 1);

        return true;
    }

    public function dir_readdir()
    {
        $data = $this->readDirIterator->current() ?? false;
        $this->readDirIterator->next();

        if ($data !== false) {
            $prefix = $this->internalFile ? $this->internalFile->getFullPath() : '';
            $data = preg_replace('#^'.preg_quote($prefix.'/', '#').'#', '', $data, 1);
        }

        return $data;
    }

    public function dir_closedir()
    {
        $this->internalFile = $this->reader = $this->readDirIterator = null;

        return true;
    }

    public function dir_rewinddir()
    {
        $prefix = $this->internalFile ? $this->internalFile->getFullPath() : '';
        $this->readDirIterator = $this->reader->listFiles($prefix, 1);

        return true;
    }

    private function decodeUrl(string $url, bool $followSymlinks = true)
    {
        // remove custom scheme because it bothers parse_url
        $url = preg_replace('#^iso9660://#', '', $url, 1);
        $url = parse_url($url);

        $isoFile = $url['path'];
        $internalFile = $url['fragment'] ?? '';

        $currentOptions = is_resource($this->context) ? stream_context_get_options($this->context) : [];

        if ($isoFile === self::$lastFileName && self::$lastContextOptions === $currentOptions) {
            // Use the last reader if the file (and options) are the same, to prevent reload all iso file data
            // This is useful and faster, when using recursive directory iterator for example
            $this->reader = self::$lastReader;
        } else {
            $this->reader = new Reader($isoFile, $this->decodeOptions());
            self::$lastReader = $this->reader;
            self::$lastFileName = $isoFile;
            self::$lastContextOptions = $currentOptions;
        }


        if ($internalFile === '/') {
            $internalFile = '';
        }

        if ($internalFile !== '') {
            $this->internalFile = $this->reader->getFile($internalFile, $followSymlinks);
        }
    }

    private function decodeOptions() : ReaderOptions
    {
        if (!is_resource($this->context)) {
            return new ReaderOptions();
        }

        $options = stream_context_get_options($this->context)['iso9660'] ?? [];

        if (empty($options)) {
            return new ReaderOptions();
        }

        $result = new ReaderOptions();

        if (isset($options['showHiddenFiles'])) {
            $result->showHiddenFiles = (bool) $options['showHiddenFiles'];
        }

        return $result;
    }
}
