<?php
/**
 * @author    Semenov Alexander <semenov@skeeks.com>
 * @link      http://skeeks.com/
 * @copyright 2010 SkeekS (СкикС)
 * @date      05.08.2015
 */
namespace mikolazp\yii2\assetsAuto;

use yii\helpers\FileHelper;
use yii\base\BootstrapInterface;
use yii\base\Component;
use yii\base\Event;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\web\Application;
use yii\web\JsExpression;
use yii\web\Response;
use yii\web\View;

/**
 * Class AssetsAutoCompressComponent
 * @package skeeks\yii2\assetsAuto
 */
class AssetsAutoCompressComponent extends Component implements BootstrapInterface
{
    /**
     * @var bool Включение выключение механизма компиляции
     */
    public $enabled = true;



    /*########## CSS ##########*/

    /**
     * @var bool Включение объединения css файлов
     */
    public $cssFileCompile = true;
    /**
     * @var bool
     */
    public $cssCompress = true;
    /**
     * @var bool Включить сжатие и обработку css перед сохранением в файл
     */
    public $cssFileCompress = false;
    /**
     * @var bool Files, that don't need to compile
     */
    public $CssCompileIgnoreFiles = [];


    /**
     * @var bool Пытаться получить файлы css к которым указан путь как к удаленному файлу, скчать его к себе.
     */
    public $cssFileRemouteCompile = false;
    /**
     * @var bool Перенос css файлов вниз страницы
     */
    public $cssFileBottom = false;
    /**
     * @var bool Перенос css файлов вниз страницы и их подгрузка при помощи js
     */
    public $cssFileBottomLoadOnJs = false;




    /*########## JAVASCRIPT ##########*/

    /**
     * @var bool Включение объединения js файлов
     */
    public $jsFileCompile = true;
    /**
     * @var bool compress js code that directly in page
     */
    public $jsCompress = true;
    /**
     * @var bool Включить сжатие и обработку js перед сохранением в файл
     */
    public $jsFileCompress = true;
    /**
     * @var bool Files, that don't need to compile
     */
    public $JsCompileIgnoreFiles = [];


    /**
     * @var bool Выризать комментарии при обработке js
     */
    public $jsCompressFlaggedComments = true;
    /**
     * @var bool Пытаться получить файлы js к которым указан путь как к удаленному файлу, скчать его к себе.
     */
    public $jsFileRemouteCompile = false;
    /**
     * @var bool Выризать комментарии при обработке js
     */
    public $jsFileCompressFlaggedComments = true;



    /**
     * @param \yii\web\Application $app
     */
    public function bootstrap($app)
    {


        // otherwise had problems with ajax
        if ($this->enabled && \Yii::$app->request->isAjax) {
            \yii\base\Event::on(\yii\web\View::className(), \yii\web\View::EVENT_AFTER_RENDER, function ($e) {
                unset($e->sender->assetBundles['yii\web\JqueryAsset']);
            });
        }


        if ($app instanceof Application) {
            //Response::EVENT_AFTER_SEND,
            //$content = ob_get_clean();
            $app->view->on(View::EVENT_END_PAGE, function (Event $e) {
                /**
                 * @var $view View
                 */
                $view = $e->sender;

                if ($this->enabled && $view instanceof View && \Yii::$app->response->format == Response::FORMAT_HTML) {
                    \Yii::beginProfile('Compress assets');
                    $this->_processing($view);
                    \Yii::endProfile('Compress assets');
                }
            });
        }
    }


    /**
     * @return string
     */
    public function getSettingsHash()
    {
        return serialize((array)$this);
    }

    /**
     * @param View $view
     */
    protected function _processing(View $view)
    {

        //Компиляция файлов js в один.
        if ($view->jsFiles && $this->jsFileCompile) {
            \Yii::beginProfile('Compress js files');
            foreach ($view->jsFiles as $pos => $files) {
                if ($files) {
                    $view->jsFiles[$pos] = $this->_processingJsFiles($files);
                }
            }
            \Yii::endProfile('Compress js files');
        }

        //Компиляция js кода который встречается на странице
        if ($view->js && $this->jsCompress) {
            \Yii::beginProfile('Compress js code');
            foreach ($view->js as $pos => $parts) {
                if ($parts) {
                    $view->js[$pos] = $this->_processingJs($parts);
                }
            }
            \Yii::endProfile('Compress js code');
        }


        //Компиляция css файлов который встречается на странице
        if ($view->cssFiles && $this->cssFileCompile) {
            \Yii::beginProfile('Compress css files');

            $view->cssFiles = $this->_processingCssFiles($view->cssFiles);
            \Yii::endProfile('Compress css files');
        }

        //Компиляция css файлов который встречается на странице
        if ($view->css && $this->cssCompress) {
            \Yii::beginProfile('Compress css code');

            $view->css = $this->_processingCss($view->css);

            \Yii::endProfile('Compress css code');
        }
        //Компиляция css файлов который встречается на странице
        if ($view->css && $this->cssCompress) {
            \Yii::beginProfile('Compress css code');

            $view->css = $this->_processingCss($view->css);

            \Yii::endProfile('Compress css code');
        }


        //Перенос файлов css вниз страницы, где файлы js View::POS_END
        if ($view->cssFiles && $this->cssFileBottom) {
            \Yii::beginProfile('Moving css files bottom');

            if ($this->cssFileBottomLoadOnJs) {
                \Yii::beginProfile('load css on js');

                $cssFilesString = implode("", $view->cssFiles);
                $view->cssFiles = [];

                $script = Html::script(new JsExpression(<<<JS
        document.write('{$cssFilesString}');
JS
                ));

                if (ArrayHelper::getValue($view->jsFiles, View::POS_END)) {
                    $view->jsFiles[View::POS_END] = ArrayHelper::merge($view->jsFiles[View::POS_END], [$script]);

                } else {
                    $view->jsFiles[View::POS_END][] = $script;
                }


                \Yii::endProfile('load css on js');
            } else {
                if (ArrayHelper::getValue($view->jsFiles, View::POS_END)) {
                    $view->jsFiles[View::POS_END] = ArrayHelper::merge($view->cssFiles, $view->jsFiles[View::POS_END]);

                } else {
                    $view->jsFiles[View::POS_END] = $view->cssFiles;
                }

                $view->cssFiles = [];
            }


            \Yii::endProfile('Moving css files bottom');
        }


    }

    /**
     * @param $parts
     *
     * @return array
     * @throws \Exception
     */
    protected function _processingJs($parts)
    {
        $result = [];

        if ($parts) {
            foreach ($parts as $key => $value) {
                $result[$key] = \JShrink\Minifier::minify($value, ['flaggedComments' => $this->jsCompressFlaggedComments]);
            }
        }

        return $result;
    }

    /**
     * @param array $files
     *
     * @return array
     */
    protected function _processingJsFiles($files = [])
    {
        $fileName = md5(implode(array_keys($files)) . $this->getSettingsHash()) . '.js';
        $publicUrl = \Yii::getAlias('@web/assets/js-compress/' . $fileName);

        $rootDir = \Yii::getAlias('@webroot/assets/js-compress');
        $rootUrl = $rootDir . '/' . $fileName;

        if (file_exists($rootUrl)) {
            $resultFiles = [];

            foreach ($files as $fileCode => $fileTag) {
                if (!Url::isRelative($fileCode) || $this->isIgnoreJs($fileCode)) {
                    $resultFiles[$fileCode] = $fileTag;
                } else {
                    if ($this->jsFileRemouteCompile) {
                        $resultFiles[$fileCode] = $fileTag;
                    }
                }
            }

            $publicUrl = $publicUrl . "?v=" . filemtime($rootUrl);
            $resultFiles[$publicUrl] = Html::jsFile($publicUrl);
            return $resultFiles;
        }

        $resultContent = [];
        $resultFiles = [];
        foreach ($files as $fileCode => $fileTag) {
            if (Url::isRelative($fileCode) && !$this->isIgnoreJs($fileCode)) {
                $resultContent[] = trim(file_get_contents(Url::to(\Yii::getAlias('@web' . $fileCode), true)));
            } else {
                if ($this->jsFileRemouteCompile) {
                    //Пытаемся скачать удаленный файл
                    $resultContent[] = trim(file_get_contents($fileCode));
                } else {
                    $resultFiles[$fileCode] = $fileTag;
                }
            }

        }

        if ($resultContent) {
            $content = implode($resultContent, ";\n");
            if (!is_dir($rootDir)) {
                if (!FileHelper::createDirectory($rootDir, 0777)) {
                    return $files;
                }
            }

            if ($this->jsFileCompress) {
                $content = \JShrink\Minifier::minify($content, ['flaggedComments' => $this->jsFileCompressFlaggedComments]);
            }

            $file = fopen($rootUrl, "w");
            fwrite($file, $content);
            fclose($file);
        }


        if (file_exists($rootUrl)) {
            $publicUrl = $publicUrl . "?v=" . filemtime($rootUrl);
            $resultFiles[$publicUrl] = Html::jsFile($publicUrl);
            return $resultFiles;
        } else {
            return $files;
        }
    }

    /**
     * @param array $files
     *
     * @return array
     */
    protected function _processingCssFiles($files = [])
    {
        $fileName = md5(implode(array_keys($files)) . $this->getSettingsHash()) . '.css';
        $publicUrl = \Yii::getAlias('@web/assets/css-compress/' . $fileName);

        $rootDir = \Yii::getAlias('@webroot/assets/css-compress');
        $rootUrl = $rootDir . '/' . $fileName;


        if (file_exists($rootUrl)) {
            $resultFiles = [];

            foreach ($files as $fileCode => $fileTag) {
                if (!Url::isRelative($fileCode) || $this->isIgnoreCss($fileCode)) {
                    $resultFiles[$fileCode] = $fileTag;
                } else {
                    if ($this->cssFileRemouteCompile) {
                        $resultFiles[$fileCode] = $fileTag;
                    }
                }
            }

            $publicUrl = $publicUrl . "?v=" . filemtime($rootUrl);
            $resultFiles[$publicUrl] = Html::cssFile($publicUrl);
            return $resultFiles;
        }

        $resultContent = [];
        $resultFiles = [];
        foreach ($files as $fileCode => $fileTag) {
            if (Url::isRelative($fileCode) && !$this->isIgnoreCss($fileCode)) {
                $contentTmp = trim(file_get_contents(Url::to(\Yii::getAlias('@web' . $fileCode), true)));

                $fileCodeTmp = explode("/", $fileCode);
                unset($fileCodeTmp[count($fileCodeTmp) - 1]);
                $prependRelativePath = implode("/", $fileCodeTmp) . "/";

                if ($this->cssFileCompress) {
                    $contentTmp = \Minify_CSS::minify($contentTmp, [
                        "prependRelativePath" => $prependRelativePath,

                        'compress'            => true,
                        'removeCharsets'      => true,
                        'preserveComments'    => true,
                    ]);
                }

                //$contentTmp = \CssMin::minify($contentTmp);

                $resultContent[] = $contentTmp;
            } else {
                if ($this->cssFileRemouteCompile) {
                    //Пытаемся скачать удаленный файл
                    $resultContent[] = trim(file_get_contents($fileCode));
                } else {
                    $resultFiles[$fileCode] = $fileTag;
                }
            }

        }

        if ($resultContent) {
            $content = implode($resultContent, "\n");
            if (!is_dir($rootDir)) {
                if (!FileHelper::createDirectory($rootDir, 0777)) {
                    return $files;
                }
            }

            if ($this->cssFileCompress) {
                $content = \CssMin::minify($content);
            }

            $file = fopen($rootUrl, "w");
            fwrite($file, $content);
            fclose($file);
        }


        if (file_exists($rootUrl)) {
            $publicUrl = $publicUrl . "?v=" . filemtime($rootUrl);
            $resultFiles[$publicUrl] = Html::cssFile($publicUrl);
            return $resultFiles;
        } else {
            return $files;
        }
    }


    /**
     * @param array $css
     *
     * @return array
     */
    protected function _processingCss($css = [])
    {
        $newCss = [];

        foreach ($css as $code => $value) {
            $newCss[] = preg_replace_callback('/<style\b[^>]*>(.*)<\/style>/is', function ($match) {
                return $match[1];
            }, $value);
        }

        $css = implode($newCss, "\n");
        $css = \CssMin::minify($css);
        return [md5($css) => "<style>" . $css . "</style>"];
    }


    /**
     * @param $name
     *
     * @return bool
     */
    protected function isIgnoreCss($name){
        if(empty($this->CssCompileIgnoreFiles)){
            return false;
        }


        foreach ((array)$this->CssCompileIgnoreFiles as $file) {
            if(strpos($name, $file) !== false){
                return true;
            }
        }

        return false;
    }

    /**
     * @param $name
     *
     * @return bool
     */
    protected function isIgnoreJs($name){
        if(empty($this->JsCompileIgnoreFiles)){
            return false;
        }


        foreach ((array)$this->JsCompileIgnoreFiles as $file) {
            if(strpos($name, $file) !== false){
                return true;
            }
        }

        return false;
    }

}