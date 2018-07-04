<?php

namespace ISO9660;

use ISO9660\Exception\InvalidIsoFile;
use ISO9660\Exception\PrimaryVolumeDescriptorMalformed;
use ISO9660\Exception\PrimaryVolumeDescriptorNotFound;
use ISO9660\Filesystem\File;
use ISO9660\RockRidge\Decoder;
use ISO9660\Util\DecDatetimeDecoder;

final class Reader
{
    /**
     * @var resource
     */
    private $stream;

    /**
     * @var File[]
     */
    private $files;

    /**
     * @var string[]
     */
    private $fileAddresses;

    /**
     * @var array
     */
    private $volumeDescriptor;

    /**
     * @var bool
     */
    private $isJolietVolumeDescriptor = false;

    private const VOLUME_DESCRIPTOR_PRIMARY         = 0x01;
    private const VOLUME_DESCRIPTOR_SUPPLEMENTARY   = 0x02;
    private const VOLUME_DESCRIPTOR_SET_TERMINATOR  = 0xFF;

    public function __construct(string $filename)
    {
        $this->stream = fopen($filename, 'r');

        // Skip MBR / GPT / APM / ...
        fseek($this->stream, 16 * 2048); // 32 KiB / 16 blocks

        [$primaryVolumeDescriptor, $jolietVolumeDescriptor] = $this->loadVolumeDescriptors();

        $this->volumeDescriptor = $primaryVolumeDescriptor;
        $rootDir = $this->readDirectory($this->volumeDescriptor['RootDirectoryEntry']);

        if ($jolietVolumeDescriptor !== null) {
            // Joliet is available. We check if the PVD provides Rock Ridge.
            // If RR is available, we prefer use the PVD with RR rather than Joliet.
            fseek($this->stream, $this->volumeDescriptor['LogicalBlockSize'] * $rootDir['ExtentLBA']);
            $directoryRecordLength = ord(fread($this->stream, 1));
            fseek($this->stream, -1, SEEK_CUR);
            $topLevelDir = $this->readDirectory(fread($this->stream, $directoryRecordLength));

            if (!($topLevelDir['AdditionalDataFlags'] & RockRidge\Flags::FLAG_ROCK_RIDGE)) {
                // RR not available, we use Joliet
                $this->volumeDescriptor = $jolietVolumeDescriptor;
                $this->isJolietVolumeDescriptor = true;
                $rootDir = $this->readDirectory($this->volumeDescriptor['RootDirectoryEntry']);
            }
        }

        $this->files = [];
        $this->fileAddresses = [];
        $this->loadFiles($rootDir['ExtentLBA']);
    }

    public function getFile(string $filename, bool $followSymlinks = true) : ?File
    {
        if (strpos($filename, '/') !== 0) {
            $filename = '/'.$filename;
        }

        if (isset($this->files[$filename])) {
            if ($followSymlinks && $this->files[$filename]->isSymlink()) {
                return $this->getFile($this->files[$filename]->getSymlinkTarget());
            }

            return $this->files[$filename];
        }

        return null;
    }

    /**
     * @param File|string $file
     */
    public function getFileContent($file, int $pos = 0, int $length = null)
    {
        if ($file instanceof File) {
            $file = $file->getFullPath();
        } elseif (is_string($file)) {
            if (strpos($file, '/') !== 0) {
                $file = '/'.$file;
            }
        } else {
            throw new \InvalidArgumentException('You must provide a string or a File object');
        }


        if (!isset($this->files[$file])) {
            throw new \InvalidArgumentException('File doest not exist');
        }

        if ($pos < 0 || $pos >= $this->files[$file]->getSize()) {
            return false;
        }

        $offset = $this->volumeDescriptor['LogicalBlockSize'] * $this->fileAddresses[$file];
        $offset += $pos;

        if ($length === null || ($pos+$length) > $this->files[$file]->getSize()) {
            $length = $this->files[$file]->getSize() - $pos;
        }

        fseek($this->stream, $offset);

        return fread($this->stream, $length);
    }

    /**
     * @return string[]
     */
    public function listFiles(string $prefix = '', int $maxDepth = null) : \Generator
    {
        if (substr($prefix, -1) === '/') {
            $prefix = substr($prefix, 0, -1);
        }

        if (strlen($prefix) > 0 && strpos($prefix, '/') !== 0) {
            $prefix = '/'.$prefix;
        }

        $depth = substr_count($prefix, '/');

        foreach ($this->files as $filename => $file) {
            if (strlen($prefix) === 0 || strpos($filename, $prefix.'/') === 0) {
                if ($maxDepth && substr_count($filename, '/') > $depth+$maxDepth) {
                    continue;
                }

                yield $filename;
            }
        }
    }

    /**
     * @throws InvalidIsoFile
     */
    private function loadVolumeDescriptors(): array
    {
        $primaryVolumeDescriptor = null;
        $jolietVolumeDescriptor = null;

        // Joliet escape sequences padding (field is 32 long, data is 3 long, fill the 29 others with zeros)
        $padding = str_repeat(chr(0), 29);

        // Volume descriptor set
        do {
            if (feof($this->stream)) {
                // Nothing to read? => PVD not found
                throw new PrimaryVolumeDescriptorNotFound();
            }

            $volumeDescriptorType = ord(fread($this->stream, 1)); // Type
            $volumeDescriptorIdentifier = fread($this->stream, 5); // Identifier
            $volumeDescriptorVersion = ord(fread($this->stream, 1)); // Version
            $volumeData = fread($this->stream, 2041);

            if (
                $volumeDescriptorType === self::VOLUME_DESCRIPTOR_PRIMARY ||
                $volumeDescriptorType === self::VOLUME_DESCRIPTOR_SUPPLEMENTARY
            ) {
                if ($volumeDescriptorIdentifier !== 'CD001' && $volumeDescriptorVersion !== 0x01) {
                    if ($volumeDescriptorType === self::VOLUME_DESCRIPTOR_PRIMARY) {
                        throw new PrimaryVolumeDescriptorMalformed();
                    } else {
                        continue;
                    }
                }

                $data = $this->decodePrimaryOrSupplementaryVolumeDescriptor($volumeData);

                if ($volumeDescriptorType === self::VOLUME_DESCRIPTOR_PRIMARY) {
                    $primaryVolumeDescriptor = $data;
                } elseif (
                    $data['EscapeSequences'] === '%/@'.$padding ||
                    $data['EscapeSequences'] === '%/C'.$padding ||
                    $data['EscapeSequences'] === '%/E'.$padding
                ) {
                    $jolietVolumeDescriptor = $data;
                }
            }
        } while ($volumeDescriptorType !== self::VOLUME_DESCRIPTOR_SET_TERMINATOR);

        if ($primaryVolumeDescriptor !== null) {
            return [$primaryVolumeDescriptor, $jolietVolumeDescriptor];
        }

        throw new PrimaryVolumeDescriptorNotFound();
    }

    private function decodePrimaryOrSupplementaryVolumeDescriptor(string $volumeData) : array
    {
        $format =
            'x/' .
            'a32SystemIdentifier/' .
            'a32VolumeIdentifier/' .
            'x8/' .
            'VVolumeSpaceSize/NVolumeSpaceSizeBigEndian/' .
            'a32EscapeSequences/' .
            'vVolumeSetSize/x2/' .
            'vVolumeSequenceNumber/x2/' .
            'vLogicalBlockSize/x2/' .
            'VPathTableSize/x4/' .
            'VLPathTableLBA/' .
            'VLOptionalPathTableLBA/' .
            'VMPathTableLBA/' .
            'VMOptionalPathTableLBA/' .
            'a34RootDirectoryEntry/' .
            'a128VolumeSetIdentifier/' .
            'a128PublisherIdentifier/' .
            'a128DataPreparerIdentifier/' .
            'a128ApplicationIdentifier/' .
            'a38CopyrightFileIdentifier/' .
            'a36AbstractFileIdentifier/' .
            'a37BibliographicFileIdentifier/'.
            'a17VolumeCreationDate/' .
            'a17VolumeModificationDate/' .
            'a17VolumeExpirationDate/' .
            'a17VolumeEffectiveDate';

        $data = unpack($format, $volumeData);

        $data['VolumeCreationDate'] = DecDatetimeDecoder::decode($data['VolumeCreationDate']);
        $data['VolumeModificationDate'] = DecDatetimeDecoder::decode($data['VolumeModificationDate']);
        $data['VolumeExpirationDate'] = DecDatetimeDecoder::decode($data['VolumeExpirationDate']);
        $data['VolumeEffectiveDate'] = DecDatetimeDecoder::decode($data['VolumeEffectiveDate']);

        if (
            $data['VolumeSpaceSize'] === 0 ||
            $data['VolumeSpaceSize'] !== $data['VolumeSpaceSizeBigEndian']
        ) {
            throw new PrimaryVolumeDescriptorMalformed();
        }

        unset($data['VolumeSpaceSizeBigEndian']);

        return $data;
    }

    private function readDirectory(string $rawData): array
    {
        $format =
            'CDirectoryRecordLength/' .
            'CExtendedAttributeRecordLength/' .
            'VExtentLBA/x4/' .
            'VExtentSize/x4/' .
            'x7/' .
            'CFileFlags/' .
            'CFileUnitSize/' .
            'CInterleaveGapSize/' .
            'vVolumeSequenceNumber/x2/' .
            'CFileNameLength';

        $directoryData = unpack($format, $rawData);
        $directoryData['FileName'] = substr($rawData, 33, $directoryData['FileNameLength']);

        // We have basic data, now try to read additional data ( SUSP / RRIP )
        // If length of file identifier is even : read a padding field
        $paddingFieldLength = $directoryData['FileNameLength'] % 2 === 0 ? 1 : 0;

        // 33 is length of all static fields. The only dynamic field is file name and we know its length
        $directoryDataEnd = 33 + $directoryData['FileNameLength'] + $paddingFieldLength;

        $directoryData['AdditionalDataFlags'] = 0;

        if (strlen($rawData) > $directoryDataEnd) {
            // There is additional data, read it
            $rawDataAdditional = substr($rawData, $directoryDataEnd);
            Decoder::decodeData($rawDataAdditional, $directoryData, $this->stream, $this->volumeDescriptor['LogicalBlockSize']);
        }

        return $directoryData;
    }

    private function loadFiles(int $lbaAddress, string $pathPrefix = '') : void
    {
        fseek($this->stream, $this->volumeDescriptor['LogicalBlockSize'] * $lbaAddress);

        $i = 0;
        while ($directoryRecordLength = ord(fread($this->stream, 1))) {
            fseek($this->stream, -1, SEEK_CUR);
            $dir = $this->readDirectory(fread($this->stream, $directoryRecordLength));

            if ($dir['AdditionalDataFlags'] & RockRidge\Flags::FLAG_RELOCATED) {
                continue;
            }

            // Skip . and ..
            if ($i < 2) {
                $i++;
                continue;
            }

            // Skip hidden files
            if ($dir['FileFlags'] & Flags::FLAG_HIDDEN_FILE) {
                continue;
            }

            $isDir = (bool) ($dir['FileFlags'] & Flags::FLAG_DIRECTORY);

            if ($this->isJolietVolumeDescriptor) {
                $dir['FileName'] = mb_convert_encoding($dir['FileName'], 'UTF-8', 'UCS-2');
            }

            // remove potential ";1" if RockRidge is not available
            if (!$isDir && $separatorPosition = strrpos($dir['FileName'], ';')) {
                $dir['FileName'] = substr($dir['FileName'], 0, $separatorPosition);
            }
            $filename = $dir['FileName'];
            $fullPath = $pathPrefix.'/'.$filename;

            $this->fileAddresses[$fullPath] = $dir['ExtentLBA'];

            $this->files[$fullPath] = new File($fullPath, $isDir, $dir);

            if ($isDir) {
                // Load children
                $currentPos = ftell($this->stream);
                $this->loadFiles($dir['ExtentLBA'], $fullPath);
                fseek($this->stream, $currentPos);
            }
        }
    }
}
