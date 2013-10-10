<?php

/**
 * 图片上传类
 * 
 * 试用案例：
 * 
 * //调用方法
 * require_once 'uploadImg.php'; 
 * $_uploadImg = new UploadImg();
 * 
 * //上传文件, 返回文件上传结果
 * $FILE = $_uploadImg->upload($_FILES['file'], array(388), uploadImg::BRANDS );
 * 
 * //如果没有报错，返回路径数组 $FILE['path'] 
 * if( $FILE['error'] == 0){
 *      
 * }
 * 
 * @author Garrison zhang 
 * 
 */
class UploadImg {

    const CUSTOMER = 'customer';   //消费者目录
    const BRANDS = 'brands';       //品牌目录
    const MANUFACTURER = 'manufacturer';       //厂家目录
    const COUPON = 'coupon';        //优惠卷目录

    private $_dirTypeArr = array();

    /**
     * 初始化类uploadImg， 赋值可选目录
     */
    function __construct() {
        $this->_dirTypeArr[] = uploadImg::BRANDS;
        $this->_dirTypeArr[] = uploadImg::COUPON;
        $this->_dirTypeArr[] = uploadImg::CUSTOMER;
        $this->_dirTypeArr[] = uploadImg::MANUFACTURER;
    }

    /**
     * 调用入口
     * 
     * @param array $FILE 上传的文件，例如：文件变量$_FILES['imgfile']
     * @param array $sizeArr 裁剪压缩的目标大小, 例如： $sizeArr = array(120, 380, 480)
     * @param const $dirType 只能是指定目录名称
     * 
     * @return array  文件目录, http://img.feifei.com 开头
     */
    public function upload($FILE, $sizeArr = array(), $dirType) {
        //参数合法性检查
        if (!in_array($dirType, $this->_dirTypeArr)) {
            return 'selected dir Type error';
        }
        $FILE = $this->check($FILE);
        if ($FILE['error'] != 0) {
            return 'invalid piture file';
        }
        $FILE = $this->rename($FILE);

        $result = array();
        $dirString = $this->CreateImgFloder($dirType);
        if ($dirString) {
            $fileFullName = $dirString . time() . $FILE['name'];
            move_uploaded_file($FILE['tmp_name'], $fileFullName);
            $result[] = $fileFullName;

            //当$fileFullName 不是一个图片文件时，会报错一个异常！
            try {
                foreach ($sizeArr as $size) {
                    $newSizePath = $this->newImgSize($fileFullName, $size);
                    $result[] = $newSizePath;
                }
            } catch (Exception $exc) {
                $FILE['error'] = 9;
                $FILE['errMsg'] = "Imgick failed";
                return $FILE;
            }
        }

        //更改路径 为http://img.feifei.com/......
        foreach ($result as &$path) {
            $path = preg_replace("/.*\/img.feifei.com/", 'http://img.feifei.com', $path);
        }
        unset($path);

        $FILE['path'] = $result;
        return $FILE;
    }

    /**
     * 检查上传图片是否合法 
     * 
     */
    private function check($FILE) {
        $fileinfo = $this->uploadConditions($FILE, array('gif', 'jpg', 'png'));
        return $fileinfo[0];
    }

    /**
     * 修改名字
     */
    private function rename($FILE) {
        $str = md5($FILE['tmp_name']);
        $FILE['name'] = substr($str, -5, 5) . '.' . $FILE['ext'];

        return $FILE;
    }

    /**
     * 创建图片目录
     */
    private function CreateImgFloder($dirName) {
        $year = date('Y');
        $month = date('m');
        $day = date('d');

        $img_dir = preg_replace("/(\w+)\.feifei.com/", "img.feifei.com", BASE_DIR);
        $dir = $img_dir . 'static/' . $dirName . '/' . $year . '/' . $month . '/' . $day . '/';
        $this->CreateFlolder($dir);

        return $dir;
    }

    /**
     * 递归创建目录
     * @param string $path 目录路径, 例如： /var/www/test/test/
     */
    private function CreateFlolder($path) {
        if (!file_exists($path)) {
            $this->CreateFlolder(dirname($path));
            if (mkdir($path, 0777)) {
                chmod($path, 0777);
            } else {
                return FALSE;
            }
        }
    }

    /**
     * 裁剪图片大小 用 Imagick 扩展进行裁剪。
     * 
     * @param string $fileString 文件路径字符串，文件名只匹配 xxxx.xxx格式
     *                  例如： /var/www/dophp/img.feifei.com/static/customer/2013/09/27/13802818693.jpg
     * @param int $size 目标尺寸大小
     */
    private function newImgSize($fileString, $size = '120') {

        //创建新目录
        preg_match('/(\w+)\.(\w+)$/', $fileString, $matchs);
        $newDir = str_replace($matchs[0], $size . 'x/', $fileString);
        $this->CreateFlolder($newDir);

        $newfileStr = $newDir . $matchs[0];

        //Imagick类处理图片
        require_once 'doImagick';
        $_image = new doImagick(array($fileString));
        $_image->setDstImage($newfileStr);
        $_image->thumbImageScaleFill($size, $size);

        return $newfileStr;
    }
    
    /**
     * 文件检测
     * 
     * @access public
     * @param mixed $FILES
     * @param array $allowExt 外部用限定文件类型array('gif','jpg')
     * @param int $maxSize 初始设定允许文件大小为2M,以字节计算
     * @return array
     */
    private function uploadConditions($FILES, $allowExt = array(), $maxSize = 2097152) {
        $length = count($FILES['name']);
        for ($i = 0; $i < $length; $i++) {
            foreach ($FILES as $key => $data) {
                if ($length == 1)
                    $file[$i][$key] = $data;
                else
                    $file[$i][$key] = $data[$i];
            }
            if (isset($file[$i]['name']) && $file[$i]['name']) {
                $tempArr = pathinfo($file[$i]['name']);
                $file[$i]['ext'] = strtolower($tempArr["extension"]);
                if (!empty($allowExt) && (!in_array($file[$i]['ext'], $allowExt)))
                    $file[$i]['error'] = 8;
                if ($maxSize && $file[$i]['size'] > $maxSize)
                    $file[$i]['error'] = 2;
                if ($file[$i]['ext'] == 'gif') {
                    $gif = file_get_contents($file[$i]["tmp_name"]);
                    $rs = preg_match('/<\/?(script){1}>/i', $gif);
                    if ($rs)
                        $file[$i]['error'] = 9;
                }
            }
        }
        return $file;
    }

}

?>
