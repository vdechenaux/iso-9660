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
    private $primaryVolumeDescriptor;

    private const VOLUME_DESCRIPTOR_PVD             = 0x01;
    private const VOLUME_DESCRIPTOR_SET_TERMINATOR  = 0xFF;

    public function __construct(string $filename)
    {
        $this->stream = fopen($filename, 'r');

        // Skip MBR / GPT / APM / ...
        fseek($this->stream, 16 * 2048); // 32 KiB / 16 blocks

        $this->loadPrimaryVolumeDescriptor();

        $rootDir = $this->readDirectory($this->primaryVolumeDescriptor['RootDirectoryEntry']);

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

        $offset = $this->primaryVolumeDescriptor['LogicalBlockSize'] * $this->fileAddresses[$file];
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
    private function loadPrimaryVolumeDescriptor(): void
    {
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

            if ($volumeDescriptorType === self::VOLUME_DESCRIPTOR_PVD) {
                if ($volumeDescriptorIdentifier !== 'CD001' && $volumeDescriptorVersion !== 0x01) {
                    throw new PrimaryVolumeDescriptorMalformed();
                }

                $format =
                    'x/' .
                    'a32SystemIdentifier/' .
                    'a32VolumeIdentifier/' .
                    'x8/' .
                    'VVolumeSpaceSize/NVolumeSpaceSizeBigEndian/' .
                    'x32/' .
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

                $this->primaryVolumeDescriptor = $data;

                return;
            }
        } while ($volumeDescriptorType !== self::VOLUME_DESCRIPTOR_SET_TERMINATOR);

        throw new PrimaryVolumeDescriptorNotFound();
    }

    private function readDirectory($rawData): array
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
            Decoder::decodeData($rawDataAdditional, $directoryData, $this->stream, $this->primaryVolumeDescriptor['LogicalBlockSize']);
        }

        return $directoryData;
    }

    private function loadFiles(int $lbaAddress, string $pathPrefix = '') : void
    {
        fseek($this->stream, $this->primaryVolumeDescriptor['LogicalBlockSize'] * $lbaAddress);

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
