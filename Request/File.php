<?php
namespace Tanbolt\Http\Request;

use ErrorException;
use Tanbolt\Filesystem\FilesystemInterface;
use Tanbolt\Http\Exception\UploadFileException;

/**
 * Class File: Request 上传文件合集
 * @package Tanbolt\Http\Request
 * @method UploadedFile[]|array all() 获取所有上传文件
 * @method UploadedFile|UploadedFile[] get($key, $default = null) 获取指定的上传文件
 */
class File extends Parameter
{

    /**
     * 绑定的 Filesystem 对象
     * @var FilesystemInterface
     */
    private $filesystem;

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
     * 设置 file value 时，将 value 转为 UploadFile 对象
     * @param string $key
     * @param mixed $value
     * @return UploadedFile
     * @throws
     */
    protected function preparedSetValue(string $key, $value)
    {
        if (!$value instanceof UploadedFile) {
            $value = $this->getFileObj($key, $value);
        }
        return $value;
    }

    /**
     * @param string|array $key
     * @param mixed $file
     * @return UploadedFile|UploadedFile[]
     * @throws ErrorException
     */
    private function getFileObj($key, $file)
    {
        if (is_array($file) && !count(array_diff(['error','name','size','tmp_name','type'], array_keys($file)))) {
            if (is_array($file['name'])) {
                $segments = [];
                foreach ($file['name'] as $k => $name) {
                    $segments[$k] = $this->getFileObj($key, [
                        'name'     => $name,
                        'type'     => $file['type'][$k],
                        'size'     => $file['size'][$k],
                        'tmp_name' => $file['tmp_name'][$k],
                        'error'    => $file['error'][$k],
                    ]);
                }
                return $segments;
            } else {
                return (new UploadedFile($file))->filesystem($this->filesystem);
            }
        }
        throw new UploadFileException('unable to get uploaded tmp file: \'$' . $key .'\'');
    }
}
