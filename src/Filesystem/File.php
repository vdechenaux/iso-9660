<?php

namespace ISO9660\Filesystem;

final class File
{
    /**
     * @var string
     */
    private $fullPath;

    /**
     * @var int
     */
    private $size;

    /**
     * @var int|null
     */
    private $mode;

    /**
     * @var int
     */
    private $hardLinksCount = 0;

    /**
     * @var int
     */
    private $userId = 0;

    /**
     * @var int
     */
    private $groupId = 0;

    /**
     * @var string|null
     */
    private $symlinkTarget = null;

    /**
     * @var bool
     */
    private $isDirectory = false;

    /**
     * @var \DateTimeInterface
     */
    private $atime;

    /**
     * @var \DateTimeInterface
     */
    private $mtime;

    /**
     * @var \DateTimeInterface
     */
    private $ctime;

    /**
     * @internal
     */
    public function __construct(string $fullPath, bool $isDirectory, array $additionalData)
    {
        $this->fullPath = $fullPath;
        $this->isDirectory = $isDirectory;

        $this->size = $this->isDirectory() ? 0 : $additionalData['ExtentSize'];

        if (isset($additionalData['AdditionalDataFileMode'])) {
            $this->mode = $additionalData['AdditionalDataFileMode']['FileMode'];
            $this->hardLinksCount = $additionalData['AdditionalDataFileMode']['FileLinks'];
            $this->userId = $additionalData['AdditionalDataFileMode']['FileUserID'];
            $this->groupId = $additionalData['AdditionalDataFileMode']['FileGroupID'];
        }

        if (isset($additionalData['Symlink'])) {
            $this->symlinkTarget = $additionalData['Symlink'];
        }

        if (isset($additionalData['AdditionalDataTimestamps'])) {
            $this->ctime = $additionalData['AdditionalDataTimestamps']['Creation'];
            $this->mtime = $additionalData['AdditionalDataTimestamps']['Modify'];
            $this->atime = $additionalData['AdditionalDataTimestamps']['Access'];
        }
    }

    public function getName() : string
    {
        return basename($this->fullPath);
    }

    public function getFullPath() : string
    {
        return $this->fullPath;
    }

    public function getSize() : int
    {
        return $this->size;
    }

    public function getMode() : int
    {
        return $this->mode ?? ($this->isDirectory() ? 0040777 : 0100777);
    }

    public function getHardLinksCount() : int
    {
        return $this->hardLinksCount;
    }

    public function getUserId() : int
    {
        return $this->userId;
    }

    public function getGroupId() : int
    {
        return $this->groupId;
    }

    public function isSymlink() : bool
    {
        return $this->symlinkTarget !== null;
    }

    public function getSymlinkTarget() : ?string
    {
        return $this->symlinkTarget;
    }

    public function isDirectory() : bool
    {
        return $this->isDirectory;
    }

    public function getAtime(): ?\DateTimeInterface
    {
        return $this->atime;
    }

    public function getMtime(): ?\DateTimeInterface
    {
        return $this->mtime;
    }

    public function getCtime(): ?\DateTimeInterface
    {
        return $this->ctime;
    }
}
