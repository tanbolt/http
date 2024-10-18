<?php
namespace Tanbolt\Http\Request;

use ArrayAccess;
use Tanbolt\Mime\Magic;
use Tanbolt\Filesystem\FilesystemInterface;
use Tanbolt\Http\Exception\UploadFileException;

/**
 * Class UploadedFile: 单个 Request File 对象
 * @package Tanbolt\Http\Request
 */
class UploadedFile implements ArrayAccess
{
    /**
     * 针对上传文件的保存/判断
     * 普通 php-fpm 模式, 可直接使用 is_uploaded_file 判断是否为上传文件 move_uploaded_file 函数保存上传文件
     * 但对于 cli 模式, 这两个函数都不可用
     * 如果直接使用 copy 函数, 有风险, 通过该变量指定文件夹, 只有在指定文件夹下的文件才被认为是上传文件
     * @var string|array
     */
    public static $allowUploadDir;

    /**
     * 是否为单元测试调用
     * @var bool
     */
    private $test;

    /**
     * 上传文件原始数组
     * @var array
     */
    private $file;

    /**
     * 客户端文件名称
     * @var string
     */
    private $clientName;

    /**
     * 客户端文件类型
     * @var string
     */
    private $clientType;

    /**
     * 客户端文件大小
     * @var int
     */
    private $clientSize;

    /**
     * 临时文件路径
     * @var string
     */
    private $tmpName;

    /**
     * 上传文件错误码
     * @var int
     */
    private $errorCode;

    /**
     * 上传文件错误提示
     * @var null
     */
    private $errorMsg;

    /**
     * 由 Mime/Magic 得到的文件 mimeType
     * @var string
     */
    private $mimeType;

    /**
     * 由 $mimeType 猜测的文件后缀
     * @var string
     */
    private $extension;

    /**
     * 绑定的 Filesystem 对象，用于 saveTo 方法
     * @var FilesystemInterface
     */
    private $filesystem;

    /**
     * UploadedFile constructor.
     * @param $upFile
     */
    public function __construct($upFile)
    {
        $this->file = $upFile;
        $this->clientName = static::getName($upFile['name']);
        $this->clientType = $upFile['type'] ?: 'application/octet-stream';
        $this->clientSize = $upFile['size'];
        $this->tmpName = $upFile['tmp_name'];
        $this->errorCode = $upFile['error'];

        $this->mimeType = false;
        $this->extension = false;
        $this->errorMsg = null;
    }

    /**
     * 设置为测试模式 (允许指定一个本地文件模拟上传文件)
     * @param $isTest
     * @return $this
     */
    public function test($isTest)
    {
        $this->test = $isTest;
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
     * 是否上传成功
     * @return bool
     */
    public function ok()
    {
        if ($this->errorCode !== UPLOAD_ERR_OK) {
            return false;
        }
        if (!$this->test && !$this->isUploadFile()) {
            $this->errorCode = UPLOAD_ERR_NO_FILE;
            return false;
        }
        return true;
    }

    /**
     * 是否为合法的上传文件
     * @return bool
     */
    protected function isUploadFile()
    {
        $name = $this->tmpName();
        if (!static::$allowUploadDir) {
            return is_uploaded_file($name);
        }
        if (!is_file($name)) {
            return false;
        }
        if (is_array(static::$allowUploadDir)) {
            foreach (static::$allowUploadDir as $dir) {
                if (0 === strpos($name, $dir)) {
                    return true;
                }
            }
        } elseif (is_string(static::$allowUploadDir) && 0 === strpos($name, static::$allowUploadDir)) {
            return true;
        }
        return false;
    }

    /**
     * 获取错误代码
     * @return int
     */
    public function errorCode()
    {
        if ($this->ok()) {
            return UPLOAD_ERR_OK;
        }
        return $this->errorCode;
    }

    /**
     * 获取错误信息
     * @return string|null
     */
    public function errorMsg()
    {
        if ($this->ok()) {
            return null;
        }
        static $errors = [
            UPLOAD_ERR_INI_SIZE => 'Uploaded file "%s" exceeds the upload_max_filesize(%s) directive in php.ini',
            UPLOAD_ERR_FORM_SIZE => 'Uploaded file "%s" exceeds the MAX_FILE_SIZE directive in the HTML form',
            UPLOAD_ERR_PARTIAL => 'Uploaded file "%s" was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'File uploaded failed: Missing a temporary folder',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension',
        ];
        if (!isset($errors[$this->errorCode])) {
            if (UPLOAD_ERR_CANT_WRITE === $this->errorCode) {
                $msg = $this->errorMsg ?: 'Failed to write file "'.$this->clientName().'" to disk';
            } else {
                $msg = 'Uploaded file '.($this->clientName()!='' ? '"'.$this->clientName().'" ' : '').'failed due to an unknown error';
            }
        } else {
            $uploadMax = 0;
            if (UPLOAD_ERR_INI_SIZE === $this->errorCode) {
                $uploadMax = strtolower(ini_get('upload_max_filesize'));
                if ('' === $uploadMax) {
                    $uploadMax = PHP_INT_MAX;
                }
                $number = ltrim($uploadMax, '+');
                $uploadMax = intval($number) . ' ' .strtoupper(substr($uploadMax, -1));
            }
            $msg = sprintf($errors[$this->errorCode], $this->clientName(), $uploadMax);
        }
        return $msg;
    }

    /**
     * 上传数组；与 $_FILES[field] 一致
     * @return array
     */
    public function file()
    {
        return $this->file;
    }

    /**
     * 临时文件路径
     * @return string
     */
    public function tmpName()
    {
        return $this->tmpName;
    }

    /**
     * 文件原始文件名(即在客户端的文件名称)
     * @return string
     */
    public function clientName()
    {
        return $this->clientName;
    }

    /**
     * 客户端告知的文件后缀格式
     * @return string|null
     */
    public function clientExtension()
    {
        return Magic::getExtension($this->clientName());
    }

    /**
     * 客户端告知的文件 mimeType
     * @return string
     */
    public function clientType()
    {
        return $this->clientType;
    }

    /**
     * 客户端告知的文件大小
     * @return int
     */
    public function clientSize()
    {
        return $this->clientSize;
    }

    /**
     * 服务端 临时文件大小 bytes
     * @return int
     */
    public function size()
    {
        return $this->ok() && ($size = filesize($this->tmpName())) ? $size : 0;
    }

    /**
     * 检测服务端文件内容猜测的 mimeType
     * @return null|string
     * @throws
     */
    public function mimeType()
    {
        if (false !== $this->mimeType) {
            return $this->mimeType;
        }
        return $this->mimeType = Magic::guessMimeTypeByFile($this->tmpName());
    }

    /**
     * 根据 mimeType 判断其可能的格式
     * @param array|string|null $guessExtensions 预置可能的后缀, 可使用 [jpg, jpeg] 数组，也可使用 'jpg,jpeg' 字符串设置多个
     * @param bool $includeLocExtension 是否使用文件名中的后缀作为预置后缀
     * @return ?string
     */
    public function guessExtension($guessExtensions = null, bool $includeLocExtension = true)
    {
        // 使用上次的缓存结果
        $isDefault = (null === $guessExtensions && $includeLocExtension);
        if ($isDefault && false !== $this->extension) {
            return $this->extension;
        }
        $guessExtensions = $guessExtensions ? static::convertToArray($guessExtensions) : [];
        if ($includeLocExtension && !in_array(($myExtension = $this->clientExtension()), $guessExtensions)) {
            $guessExtensions[] = $myExtension;
        }
        $return = Magic::guessExtensionByMimeType($this->mimeType(), $guessExtensions);
        if ($isDefault) {
            $this->extension = $return;
        }
        return $return;
    }

    /**
     * 通过自定义的映射对比,获取文件格式 (自定义映射结构参见 Tanbolt\Mime)
     * @param array $map
     * @param ?array $extensions
     * @return string|null
     */
    public function guessExtensionThroughMap(array $map, array $extensions = null)
    {
        return Magic::guessExtensionThroughMap($this->mimeType(), $map, $extensions);
    }

    /**
     * 是否属于 $types 格式集合
     * @param array|string $types 后缀范围, 可使用 [jpg, jpeg] 数组，也可使用 'jpg,jpeg' 字符串设置多个
     * @param bool $checkMime 是否检测 mimeType
     * @return bool
     */
    public function isType($types, bool $checkMime = true)
    {
        $types = static::convertToArray($types);
        if (!in_array($this->clientExtension(), $types) ) {
            return false;
        }
        if ($checkMime) {
            $extension = $this->guessExtension($types, false);
            return !(null === $extension || !in_array($extension, $types));
        }
        return true;
    }

    /**
     * 是否图片格式
     * @param bool $checkMime
     * @return bool
     */
    public function isImage(bool $checkMime = true)
    {
        return $this->isType([
            'jpg', 'jpeg', 'gif', 'png',
        ], $checkMime);
    }

    /**
     * 是否为常见文档
     * @param bool $checkMime
     * @return bool
     */
    public function isDoc(bool $checkMime = true)
    {
        return $this->isType([
            'doc', 'docx', 'docm', 'dotm', 'rtf', 'wps', 'wpd', 'pdf', 'chm',
            'xls', 'xlsx', 'xlsm', 'xltx', 'xltm', 'xlsb', 'xlam',
            'ots', 'ppt', 'pptx', 'txt',
        ], $checkMime);
    }

    /**
     * 是否媒体文件格式(音视频)
     * @param bool $checkMime
     * @return bool
     */
    public function isMedia(bool $checkMime = true)
    {
        return $this->isType([
            'mp3', 'mpg', 'wav', 'mid', 'wma', 'asf', 'asx', 'ra', 'ogg', 'au', 'aif', 'mp4a', 'm3u', 'm3u8',
            '3g2', '3gp', '3gpp', 'avi', 'flv', 'm4v', 'mov', 'mp4', 'rmvb', 'wmv', 'mkv', 'vob', 'rm',
        ], $checkMime);
    }

    /**
     * 是否Flash
     * @param bool $checkMime
     * @return bool
     */
    public function isFlash(bool $checkMime = true)
    {
        return $this->isType([
            'swf',
        ], $checkMime);
    }

    /**
     * 是否常见压缩包格式
     * @param bool $checkMime
     * @return bool
     */
    public function isCompressedFile(bool $checkMime = true)
    {
        return $this->isType([
            '7z', 'rar', 'zip', 'tar', 'gz', 'bz', 'bz2', 'xz',
        ], $checkMime);
    }

    /**
     * 通过 Filesystem 保存文件
     * @param $directory
     * @param string|true|null $filename string:指定文件名; true:使用客户端文件名; null:自动生成唯一文件名
     * @param string|null $extension string:指定文件后缀;  null:使用客户端文件名后缀
     * @return \Tanbolt\Filesystem\File
     */
    public function saveTo($directory, $filename = null, $extension = null)
    {
        if (!$this->ok()) {
            throw new UploadFileException($this->errorMsg());
        }
        if (!$this->filesystem) {
            throw new UploadFileException('Request filesystem not configure');
        }
        $filename = $this->targetName(static::formatDirectory($directory), $filename, $extension);
        if (!$this->filesystem->put($filename, $fp = fopen($this->tmpName(), 'rb'))) {
            throw new UploadFileException('Save file "'.$filename.'" failed');
        }
        fclose($fp);
        return $this->filesystem->getObject($filename);
    }

    /**
     * 保存上传文件到本地文件系统。
     * 成功则返回文件保存到的路径 失败返回 false; 可使用 error() 获取错误原因
     * @param $directory
     * @param string|true|null $filename string:指定文件名; true:使用客户端文件名; null:自动生成唯一文件名
     * @param string|null $extension string:指定文件后缀;  null:使用客户端文件名后缀
     * @return bool|string
     */
    public function saveToLocal($directory, $filename = null, $extension = null)
    {
        if (!$this->ok() || !($path = $this->targetPath($directory, $filename, $extension)) ) {
            return false;
        }
        $moveFunction = $this->test || static::$allowUploadDir ? 'copy' : 'move_uploaded_file';
        if (!@$moveFunction($this->tmpName(), $path)) {
            $this->errorCode = UPLOAD_ERR_CANT_WRITE;
            $this->errorMsg = ($error = error_get_last()) ? strip_tags($error['message']) : null;
            return false;
        }
        @chmod($path, 0666 & ~umask());
        return $path;
    }

    /**
     * 获取文件保存路径
     * @param $directory
     * @param null $filename
     * @param null $extension
     * @return string
     */
    private function targetPath($directory, $filename = null, $extension = null)
    {
        $directory = static::formatDirectory($directory);
        if (!is_dir($directory) && false === @mkdir($directory, 0777, true)) {
            $this->errorCode = UPLOAD_ERR_CANT_WRITE;
            $this->errorMsg = 'Unable to create the "' . $directory . '" directory';
            return false;
        }
        if (!is_writable($directory)) {
            $this->errorCode = UPLOAD_ERR_CANT_WRITE;
            $this->errorMsg = 'Directory "' . $directory . '" unable to write';
            return false;
        }
        return $this->targetName($directory, $filename, $extension);
    }

    /**
     * 保存路径 + 保存名
     * @param $directory
     * @param null $filename
     * @param null $extension
     * @return string
     */
    protected function targetName($directory, $filename = null, $extension = null)
    {
        if (true === $filename) {
            return $directory . '/' . $this->clientName();
        }
        return $directory . '/' .
            (null === $filename ? md5(uniqid()) : $filename) . '.' .
            (null === $extension ? $this->clientExtension() : $extension);
    }

    /**
     * 格式化一个文件夹路径
     * @param $directory
     * @return string
     */
    private static function formatDirectory($directory)
    {
        if ('\\' === DIRECTORY_SEPARATOR) {
            $directory = str_replace('\\', '/', $directory);
        }
        while (false !== strpos($directory, '//')) {
            $directory = str_replace('//', '/', $directory);
        }
        return rtrim($directory, '/');
    }

    /**
     * 获取一个文件路径中的 文件名
     * @param $path
     * @return string
     */
    private static function getName($path)
    {
        $originalName = str_replace('\\', '/', $path);
        $pos = strrpos($originalName, '/');
        return false === $pos ? $originalName : substr($originalName, $pos + 1);
    }

    /**
     * 转为数组
     * $data 可以为数组, 也可以为逗号分割的字符串
     * @param $data
     * @return array
     */
    private static function convertToArray($data)
    {
        if (!is_array($data)) {
            $segments = explode(',', $data);
            $data = [];
            foreach ($segments as $segment) {
                if ( ($segment = trim($segment))!='' && !in_array($segment,$data)) {
                    $data[] = strtolower($segment);
                }
            }
        }
        return $data;
    }

    /**
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return isset($this->file[$offset]);
    }

    /**
     * @param mixed $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->file[$offset];
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value)
    {
        $this->file[$offset] = $value;
    }

    /**
     * @param mixed $offset
     */
    public function offsetUnset($offset)
    {
        unset($this->file[$offset]);
    }
}
