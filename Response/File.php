<?php
namespace Tanbolt\Http\Response;

use Tanbolt\Mime\Magic;
use Tanbolt\Http\Request;
use Tanbolt\Http\Response;
use Tanbolt\Filesystem\File as SystemFile;
use Tanbolt\Filesystem\FilesystemInterface;
use Tanbolt\Http\Exception\HttpServiceUnavailableException;

class File implements FactoryInterface
{
    const TYPE_LOCAL_PATH = 0;
    const TYPE_RESOURCE = 1;
    const TYPE_BINARY = 2;
    const TYPE_SYSTEM_FILE = 3;
    const TYPE_SYSTEM_PATH = 4;

    /**
     * 绑定的 Filesystem 对象，用于读取文件
     * @var FilesystemInterface
     */
    protected $filesystem;

    /**
     * 文件 (文件路径 / handle / binary data)
     * @var string|resource|SystemFile
     */
    protected $file;

    /**
     * 参数类型
     * 0: (string)     文件路径;
     * 1: (resource)   文件 handle;
     * 2: (string)     文件内容;
     * 3: (SystemFile) Filesystem 下的 File 对象;
     * 4: (string)     Filesystem 下的文件路径;
     * @var int
     */
    protected $fileType;

    /**
     * 文件大小
     * @var int
     */
    protected $fileSize;

    /**
     * 文件 mimeType
     * @var null
     */
    protected $fileMimeType;

    /**
     * 保存文件名
     * @var string
     */
    protected $fileName;

    /**
     * 文件格式(后缀)
     * @var null
     */
    protected $fileExtension;

    /**
     * 文件 stream
     * @var resource|false
     */
    protected $fileStream;

    /**
     * 文件是否弹出下载
     * @var bool
     */
    protected $attachment;

    /**
     * 是否可验证, 会自动设置 header (Etag, Last-Modified)
     * @var bool
     */
    protected $verifiable;

    /**
     * 是否禁止断点续传
     * @var bool
     */
    protected $forbidRange;

    /**
     * 所有文件分段
     * @var array
     */
    protected $allRange;

    /**
     * 当前要读取的分段
     * @var int
     */
    private $currentRange;

    /**
     * Iterator position
     * @var int
     */
    private $position = 0;

    /**
     * 每次读取文件 KB
     * @var int
     */
    private static $chunkSize = 8192;

    /**
     * @inheritDoc
     */
    public function reset()
    {
        $this->file = null;
        $this->fileType = 0;
        $this->fileSize = 0;
        $this->fileMimeType = null;
        $this->fileName = null;
        $this->fileExtension = null;
        $this->attachment = null;
        $this->verifiable = null;
        $this->forbidRange = null;
        $this->allRange = null;
        $this->position = 0;
        return $this->closeStream();
    }

    /**
     * 关闭已打开的 stream
     * @return $this
     */
    protected function closeStream()
    {
        if (is_resource($this->fileStream)) {
            fclose($this->fileStream);
        }
        $this->fileStream = null;
        return $this;
    }

    /**
     * 设置/获取 Filesystem 对象
     * @param ?FilesystemInterface $filesystem
     * @return $this|FilesystemInterface|null
     */
    public function filesystem(FilesystemInterface $filesystem = null)
    {
        if (func_num_args()) {
            $this->filesystem = $filesystem;
            return $this;
        }
        return $this->filesystem;
    }

    /**
     * Response body: 输出文件
     * @param SystemFile|resource|string|mixed $content 可以是 文件路径或内容(string)、文件指针(resource)、或 Filesystem\File
     * @param bool|null $local 在 path 为 $content 时, true:本地文件; false:Filesystem下的文件路径; null:文件内容
     * @return $this
     */
    public function setContent($content, ?bool $local = true)
    {
        if (is_resource($content)) {
            $this->file = $content;
            $this->fileType = self::TYPE_RESOURCE;
            return $this;
        }
        if ($content instanceof SystemFile) {
            $this->file = $content;
            $this->fileType = self::TYPE_SYSTEM_FILE;
            return $this;
        }
        if (false === $local && !$this->filesystem) {
            throw new HttpServiceUnavailableException('Response filesystem not configure');
        }
        $this->file = (string) $content;
        $this->fileType = null === $local
            ? self::TYPE_BINARY
            : ($local ? self::TYPE_LOCAL_PATH : self::TYPE_SYSTEM_PATH);
        return $this->resolveFile();
    }

    /**
     * 更新 header
     * @return $this
     * @throws
     */
    protected function resolveFile()
    {
        $path = null;
        switch ($this->fileType) {
            // file 为系统文件路径
            case self::TYPE_LOCAL_PATH:
                $path = $this->file;
                $this->fileSize = filesize($this->file);
                $this->fileMimeType = Magic::guessMimeTypeByFile($this->file);
                break;
            // file 为 resource
            case self::TYPE_RESOURCE:
                fseek($this->file, 0, SEEK_END);
                $this->fileSize = ftell($this->file);
                fseek($this->file, 0);
                $this->fileMimeType = Magic::guessMimeTypeByContent(fread($this->file, 128));
                break;
            // file 为 raw content
            case self::TYPE_BINARY:
                $this->fileSize = strlen($this->file);
                $this->fileMimeType = Magic::guessMimeTypeByContent($this->file);
                break;
            // file 为 SystemFile 对象
            case self::TYPE_SYSTEM_FILE:
                $path = $this->file->path;
                $this->fileSize = $this->file->size;
                $this->fileMimeType = $this->file->mimeType;
                break;
            // file 为 Filesystem 的文件路径
            case self::TYPE_SYSTEM_PATH:
                $path = $this->file;
                $this->fileSize = $this->filesystem->getSize($path);
                $this->fileMimeType = $this->filesystem->getMimeType($path);
                break;
        }
        // 自动设置文件名
        if ($path && null === $this->fileName) {
            $this->fileName = pathinfo($path, PATHINFO_FILENAME);
        }
        // 自动设置文件后缀
        if (null === $this->fileExtension) {
            if ($path) {
                $this->fileExtension = Magic::getExtension($path);
            } elseif ($this->fileMimeType) {
                $this->fileExtension = Magic::guessExtensionByMimeType($this->fileMimeType);
            }
        }
        // 关闭 stream
        return $this->closeStream();
    }

    /**
     * 设置文件保存名
     * @param ?string $fileName
     * @return $this
     */
    public function setFileName(?string $fileName)
    {
        $fileName = $fileName ? str_replace([';', '"', "\n", "\r"], '-', $fileName) : null;
        if (empty($fileName)) {
            $this->fileName = null;
        } else {
            $extension = Magic::getExtension($fileName);
            if (null !== $extension) {
                $this->fileExtension = $extension;
                $this->fileName = substr($fileName, 0, -1 - strlen($extension));
            } else {
                $this->fileName = $fileName;
            }
        }
        return $this;
    }

    /**
     * 获取原始 data
     * @return resource|string|SystemFile|null
     */
    public function file()
    {
        return $this->file;
    }

    /**
     * file 类型
     * @return int
     */
    public function fileType()
    {
        return $this->fileType;
    }

    /**
     * 获取文件全名 (包括后缀)
     * @return ?string
     */
    public function fileFullName()
    {
        if (null !== $this->fileName) {
            return $this->fileName . (null === $this->fileExtension ? '' : '.' . $this->fileExtension);
        }
        return null;
    }

    /**
     * 获取保存名 (不包括后缀)
     * @return ?string
     */
    public function fileName()
    {
        return $this->fileName;
    }

    /**
     * 获取文件格式
     * @return ?string
     */
    public function fileExtension()
    {
        return $this->fileExtension;
    }

    /**
     * 当前文件 size 大小
     * @return int
     */
    public function fileSize()
    {
        return $this->fileSize;
    }

    /**
     * 获取文件 mimeType
     * @return ?string
     */
    public function fileMimeType()
    {
        return $this->fileMimeType;
    }

    /**
     * 获取文件 hash 摘要
     * @return ?string
     */
    public function fileHash()
    {
        if ($this->file) {
            switch ($this->fileType) {
                case self::TYPE_LOCAL_PATH:
                    return md5_file($this->file) ?: null;
                case self::TYPE_RESOURCE:
                    /** @var \HashContext|resource $context */
                    $context = hash_init('md5');
                    hash_update_stream($context, $this->file);
                    return hash_final($context) ?: null;
                case self::TYPE_BINARY:
                    return md5($this->file);
                case self::TYPE_SYSTEM_FILE:
                    return $this->file->hash;
                case self::TYPE_SYSTEM_PATH:
                    return $this->filesystem->getHash($this->file);
            }
        }
        return null;
    }

    /**
     * 获取文件的最后修改时间
     * @return false|int
     */
    public function fileMtime()
    {
        if ($this->file) {
            switch ($this->fileType) {
                case self::TYPE_LOCAL_PATH:
                    return filemtime($this->file);
                case self::TYPE_SYSTEM_FILE:
                    return $this->file->lastModified ?: false;
                case self::TYPE_SYSTEM_PATH:
                    return $this->filesystem->getLastModified($this->file) ?: false;
            }
        }
        return false;
    }

    /**
     * 设置是否强制为下载模式
     * @param bool $forceDownload
     * @return $this
     */
    public function setDownload(bool $forceDownload = true)
    {
        $this->attachment = $forceDownload;
        return $this;
    }

    /**
     * 是否为下载文件的 header 头
     * @return bool
     */
    public function isDownload()
    {
        return (bool) $this->attachment;
    }

    /**
     * 是否可验证, 会自动设置 header (Etag, Last-Modified)
     * @param bool $verifiable
     * @return $this
     */
    public function setVerifiable(bool $verifiable = true)
    {
        $this->verifiable = $verifiable;
        return $this;
    }

    /**
     * 是否可验证
     * @return bool
     */
    public function isVerifiable()
    {
        return $this->verifiable;
    }

    /**
     * 禁用断点续传功能
     * @param bool $forbidden
     * @return $this
     */
    public function forbidRange(bool $forbidden = true)
    {
        $this->forbidRange = $forbidden;
        return $this;
    }

    /**
     * 是否支持断点续传
     * @return bool
     */
    public function supportRange()
    {
        return !$this->forbidRange;
    }

    /**
     * 设置 Response Header, 通过 Accept-Ranges 支持断点(需使用 1.1 协议才支持)
     * @param Response $response
     * @param Request|null $request
     * @return $this
     */
    public function prepare(Response $response, Request $request = null)
    {
        if (!($fileSize = $this->fileSize)) {
            return $this;
        }
        // 获取请求范围
        if (false === $range = $this->prepareRange($response, $request)) {
            return $this;
        }
        $multipart = count($range);
        $contentType = $this->fileMimeType ?: 'application/octet-stream';
        if ($multipart > 1) {
            // 多段
            $rangeSize = 0;
            $fileRange = [];
            $boundary = uniqid('part');
            foreach ($range as $bytes) {
                list($start, $end) = $bytes;
                $header = ($rangeSize ? "\n" : '') .
                    "--$boundary\nContent-Type: $contentType\nContent-Range: bytes $start-$end/$fileSize\n\n";
                $rangeSize += $end - $start + 1 + strlen($header);
                $fileRange[] = $header;
                $fileRange[] = $bytes;
            }
            $fileRange[] = $header = "\n--$boundary--\n";
            $rangeSize += strlen($header);
            $this->allRange = $fileRange;
            $response->setStatus(206)->setProtocolVersion(1.1)
                ->setHeader('Content-Length', $rangeSize)
                ->setHeader('Content-Type', 'multipart/byteranges; boundary='.$boundary);
        } elseif ($multipart > 0) {
            // 单段
            $this->allRange = $range;
            list($start, $end) = $range[0];
            $response->setStatus(206)->setProtocolVersion(1.1)
                ->setHeader('Content-Length', $end - $start + 1)
                ->setHeader('Content-Range', "bytes $start-$end/$fileSize")
                ->setHeader('Content-Type', $contentType);
        } else {
            // 未分段
            $this->allRange = null;
            $response->setStatus(200)
                ->setHeader('Content-Length', $this->fileSize)
                ->setHeader('Content-Type', $contentType);
        }

        // 文件打开方式 (attachment 弹出下载  inline 由浏览器决定)
        if ($this->attachment) {
            $response->setHeader('Content-Description', 'File Transfer');
            $disposition = 'attachment';
        } else {
            $disposition = 'inline';
        }
        if (null !== $attachmentName = $this->fileFullName()) {
            $disposition .= "; filename=\"$attachmentName\"";
        }
        $response->setHeader('Content-Disposition', $disposition);

        //可验证，自动添加验证首部
        if ($this->verifiable) {
            if (!$response->eTag() && $hash = $this->fileHash()) {
                $response->setETag($hash);
            }
            if (!$response->lastModified() && $mTime = $this->fileMtime()) {
                $response->setLastModified($mTime);
            }
        }
        return $this;
    }

    /**
     * 是否为断点传输, 获取请求范围
     * @param Response $response
     * @param Request|null $request
     * @return array|false
     */
    protected function prepareRange(Response $response, Request $request = null)
    {
        $method = $request ? $request->method() : null;
        $accept = $response->getHeader($key = 'Accept-Ranges');
        $acceptNone = $accept && 'none' === $accept;

        // 强制禁止 或 Method 不符合, 不支持断点传输
        if ($this->forbidRange || $acceptNone || ('HEAD' !== $method && 'GET' !== $method)) {
            if (!$acceptNone) {
                $response->header->remove($key);
            }
            $accept = false;
        } else {
            $response->setHeader($key, 'bytes');
            $accept = true;
        }
        // 不支持 或 不包含 Range 请求首部
        if (!$accept || !($range = $request->header->getLast('Range')) || 0 !== strpos($range, 'bytes=')) {
            return [];
        }
        // 有 If-Range 首部, 但首部未通过验证
        if (($ifRange = $request->header->get('If-Range')) && $response->eTag() !== $ifRange &&
            (!($lastModified = $response->lastModified()) || $lastModified->format(DATE_RFC7231) !== $ifRange)
        ) {
            return [];
        }
        // 提取 range 范围
        $bytes = [];
        $maxEnd = $this->fileSize - 1;
        $range = explode(',', substr($range, 6));
        foreach ($range as $item) {
            list($start, $end) = explode('-', trim($item), 2);
            if ('' === $start && '' === $end) {
                $bytes = false;
                break;
            }
            if ('' === $end) {
                $end = $maxEnd;
            } elseif (!ctype_digit($end)) {
                $bytes = false;
                break;
            } else {
                $end = (int) $end;
            }
            if ('' === $start) {
                $start = $this->fileSize - $end;
                $end = $maxEnd;
            } elseif (!ctype_digit($start)) {
                $bytes = false;
                break;
            } else {
                $start = (int) $start;
            }
            if (0 === $start && $end === $maxEnd) {
                $bytes = true;
                break;
            } elseif ($start < 0 || $end < 0 || $start >= $end || $end > $maxEnd) {
                $bytes = false;
                break;
            }
            $bytes[] = [$start, $end];
        }
        // 请求区间有误
        if (false === $bytes) {
            $response->setStatus(416)->setHeader('Content-Range', 'bytes */' . $this->fileSize);
            return false;
        }
        // 请求区间为完整 file
        if (true === $bytes) {
            return [];
        }
        return $bytes;
    }

    /**
     * 以下为 Iterator 接口实现
     * @return $this
     */
    public function rewind()
    {
        // 虽然也可以将 string file 通过如 php://memory 转为 stream, 但这样就占用了双份内存, 所以还是程序上麻烦一点吧
        if (self::TYPE_BINARY !== $this->fileType &&
            $this->file &&
            false !== $this->fileStream &&
            !is_resource($this->fileStream)
        ) {
            $stream = false;
            switch ($this->fileType) {
                case self::TYPE_LOCAL_PATH:
                    $stream = fopen($this->file, 'rb');
                    break;
                case self::TYPE_RESOURCE:
                    $stream = $this->file;
                    break;
                case self::TYPE_SYSTEM_FILE:
                    $stream = $this->file->stream;
                    break;
                case self::TYPE_SYSTEM_PATH:
                    $stream = $this->filesystem->getStream($this->file);
                    break;
            }
            $this->fileStream = $stream && is_resource($stream) ? $stream : false;
        }
        $this->currentRange = $this->position = 0;
        if ($this->allRange) {
            $this->position = is_string($this->allRange[0]) ? 'h0' : $this->allRange[0][0];
        }
        return $this;
    }

    /**
     * @return bool
     */
    public function valid()
    {
        // rewind 获取 stream 失败
        if (self::TYPE_BINARY !== $this->fileType && !$this->fileStream) {
            return false;
        }
        // 发送整个文件
        if (!$this->allRange) {
            return $this->position < $this->fileSize - 1;
        }
        // 分段发送
        return $this->currentRange < count($this->allRange);
    }

    /**
     * @return int
     */
    public function key()
    {
        return $this->position;
    }

    /**
     * @return string
     */
    public function current()
    {
        // rewind 获取 stream 失败
        if (self::TYPE_BINARY !== $this->fileType && !$this->fileStream) {
            return null;
        }
        // 发送整个文件
        if (!$this->allRange) {
            if ($this->position >= $this->fileSize - 1) {
                return null;
            }
            // string file, 直接一次性返回
            if (self::TYPE_BINARY === $this->fileType) {
                return $this->file;
            }
            // stream file, 逐步读取分段
            fseek($this->fileStream, $this->position);
            return fread($this->fileStream, static::$chunkSize);
        }
        // 分段发送是否已结束
        if ($this->currentRange >= count($this->allRange)) {
            return null;
        }
        $current = $this->allRange[$this->currentRange];

        // header 分段
        if (is_string($current)) {
            return $current;
        }
        // content 分段
        $maxSize = $current[1] - $this->position + 1;

        // string file, $this->position 就是 start, 所以直接输出到 end 发送整个分段
        if (self::TYPE_BINARY === $this->fileType) {
            return substr($this->file, $this->position, $maxSize);
        }
        // stream file, 逐步读取分段
        fseek($this->fileStream, $this->position);
        return fread($this->fileStream, min(static::$chunkSize, $maxSize));
    }

    /**
     * @return $this
     */
    public function next()
    {
        // 发送整个文件
        if (!$this->allRange) {
            $this->position += self::TYPE_BINARY === $this->fileType ? $this->fileSize : static::$chunkSize;
            return $this;
        }
        // 确认分段发送是否已结束
        if ($this->currentRange >= $count = count($this->allRange)) {
            return $this;
        }
        $toNext = false;
        $current = $this->allRange[$this->currentRange];
        if (is_string($current) || self::TYPE_BINARY === $this->fileType) {
            // 本次正好在读取 header 分段, 或为 string file, 直接跳到下一个分段
            $toNext = true;
        } else {
            // 本次读取为 stream content 分段
            $this->position += static::$chunkSize;
            if ($this->position >= $current[1]) {
                $toNext = true;
            }
        }
        // 跳到下一个 分段
        if ($toNext) {
            $this->currentRange++;
            if ($this->currentRange < $count) {
                $next = $this->allRange[$this->currentRange];
                $this->position = is_string($next) ? 'h'.$this->currentRange : $next[0];
            }
        }
        return $this;
    }
}
