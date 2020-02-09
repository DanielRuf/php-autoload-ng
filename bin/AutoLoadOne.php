<?php /** @noinspection HtmlUnknownAttribute */

/** @noinspection PhpUnhandledExceptionInspection */

namespace eftec\AutoLoadOne;

//*************************************************************
use Exception;

if (!defined('_AUTOLOAD_SAVEPARAM')) {
    define('_AUTOLOAD_SAVEPARAM', true);
} // true if you want to save the parameters.
//*************************************************************
ini_set('max_execution_time', 600); // Limit of 10 minutes.

/**
 * Class AutoLoadOne.
 *
 * @copyright Jorge Castro C. MIT License https://github.com/EFTEC/AutoLoadOne
 *
 * @version   1.17 2020-01-26
 * @noautoload
 */
class AutoLoadOne
{
    const VERSION = '1.17';
    const JSON_UNESCAPED_SLASHES = 64;
    const JSON_PRETTY_PRINT = 128;
    const JSON_UNESCAPED_UNICODE = 256;

    public $rooturl = '';
    public $fileGen = '';
    public $savefile = 1;
    public $savefileName = 'autoload.php';
    public $stop = 0;
    public $compression = 1;
    public $button = 0;
    public $excludeNS = '';
    public $excludePath = '';
    public $externalPath = '';
    public $log = '';
    public $logStat = '';
    public $result = '';
    public $current = '';
    public $t1 = 0;
    public $debugMode = false;
    public $statNumClass = 0;
    public $statNumPHP = 0;
    public $statConflict = 0;
    public $statError = 0;
    public $statNameSpaces = [];
    public $statByteUsed = 1024;
    public $statByteUsedCompressed = 1024;
    public $fileConfig = 'autoloadone.json';

    public $extension = '.php';

    private $excludeNSArr;
    private $excludePathArr;
    private $baseGen;

    /**
     * AutoLoadOne constructor.
     */
    public function __construct()
    {
        $this->fileGen = getcwd(); // dirname($_SERVER['SCRIPT_FILENAME']);
        $this->rooturl = getcwd(); // dirname($_SERVER['SCRIPT_FILENAME']);
        $this->t1 = microtime(true);
        $this->fileConfig = basename($_SERVER['SCRIPT_FILENAME']); // the config name shares the same name than the php but with extension .json
        $this->fileConfig = getcwd() . '/' . str_replace($this->extension, '.json', $this->fileConfig);
    }

    private function getAllParametersCli()
    {
        $this->rooturl = $this->fixSeparator($this->getParameterCli('folder'));
        $this->fileGen = $this->fixSeparator($this->getParameterCli('filegen'));
        $this->fileGen = ($this->fileGen == '.') ? $this->rooturl : $this->fileGen;
        $this->savefile = $this->getParameterCli('save');
        $this->savefileName = $this->getParameterCli('savefilename', 'autoload.php');
        $this->stop = $this->getParameterCli('stop');
        $this->compression = $this->getParameterCli('compression');
        $this->current = $this->getParameterCli('current', true);
        $this->excludeNS = $this->getParameterCli('excludens');
        $this->excludePath = $this->getParameterCli('excludepath');
        $this->externalPath = $this->getParameterCli('externalpath');
        $this->debugMode = $this->getParameterCli('debug');
    }

    /**
     * @param        $key
     * @param string $default is the defalut value is the parameter is set without value.
     *
     * @return string
     */
    private function getParameterCli($key, $default = '')
    {
        global $argv;
        $p = array_search('-' . $key, $argv);
        if ($p === false) {
            return '';
        }
        if ($default !== '') {
            return $default;
        }
        if (count($argv) >= $p + 1) {
            return $this->removeTrailSlash($argv[$p + 1]);
        }

        return '';
    }

    private function removeTrailSlash($txt)
    {
        return rtrim($txt, '/\\');
    }

    private function initSapi()
    {
        global $argv;
        $v = $this::VERSION . ' (c) Jorge Castro';
        echo <<<eot


   ___         __         __                 __ ____           
  / _ | __ __ / /_ ___   / /  ___  ___ _ ___/ // __ \ ___  ___ 
 / __ |/ // // __// _ \ / /__/ _ \/ _ `// _  // /_/ // _ \/ -_)
/_/ |_|\_,_/ \__/ \___//____/\___/\_,_/ \_,_/ \____//_//_/\__/  $v

eot;
        echo "\n";
        if (count($argv) < 2) {
            // help
            echo "-current (scan and generates files from the current folder)\n";
            echo "-folder (folder to scan)\n";
            echo '-filegen (folder where autoload' . $this->extension . " will be generate)\n";
            echo "-save (save the file to generate)\n";
            echo "-compression (compress the result)\n";
            echo "-savefilename (the filename to be generated. By default its autoload.php)\n";
            echo "-excludens (namespace excluded)\n";
            echo "-excludepath (path excluded)\n";
            echo "-externalpath (external paths)\n";
            echo "------------------------------------------------------------------\n";
        } else {
            $this->getAllParametersCli();
            $this->fileGen = ($this->fileGen == '') ? getcwd() : $this->fileGen;
            $this->button = 1;
        }
        if ($this->current) {
            $this->rooturl = getcwd();
            $this->fileGen = getcwd();
            $this->savefile = 1;
            $this->savefileName = 'autoload.php';
            $this->stop = 0;
            $this->compression = 1;
            $this->button = 1;
            $this->excludeNS = '';
            $this->externalPath = '';
            $this->excludePath = '';
        }

        echo '-folder ' . $this->rooturl . " (folder to scan)\n";
        echo '-filegen ' . $this->fileGen . ' (folder where autoload' . $this->extension . " will be generate)\n";
        echo '-save ' . ($this->savefile ? 'yes' : 'no') . " (save filegen)\n";
        echo '-compression ' . ($this->compression ? 'yes' : 'no') . " (compress the result)\n";
        echo '-savefilename ' . $this->savefileName . " (save filegen name)\n";
        echo '-excludens ' . $this->excludeNS . " (namespace excluded)\n";
        echo '-excludepath ' . $this->excludePath . " (path excluded)\n";
        echo '-externalpath ' . $this->externalPath . " (path external)\n";
        echo "------------------------------------------------------------------\n";
    }

    public static function encode($data, $options = 448)
    {
        $json = json_encode($data, $options);
        if (false === $json) {
            self::throwEncodeError(json_last_error());
        }
        return $json;
    }

    private static function throwEncodeError($code)
    {
        switch ($code) {
            case JSON_ERROR_DEPTH:
                $msg = 'Maximum stack depth exceeded';
                break;
            case JSON_ERROR_STATE_MISMATCH:
                $msg = 'Underflow or the modes mismatch';
                break;
            case JSON_ERROR_CTRL_CHAR:
                $msg = 'Unexpected control character found';
                break;
            case JSON_ERROR_UTF8:
                $msg = 'Malformed UTF-8 characters, possibly incorrectly encoded';
                break;
            default:
                $msg = 'Unknown error';
        }
        throw new Exception('JSON encoding failed: ' . $msg);
    }

    /**
     * @return bool|int
     */
    private function saveParam()
    {
        if (!_AUTOLOAD_SAVEPARAM) {
            return false;
        }
        $param = [];
        $param['rooturl'] = $this->rooturl;
        $param['fileGen'] = $this->fileGen;
        $param['savefile'] = $this->savefile;
        $param['compression'] = $this->compression;
        $param['savefileName'] = $this->savefileName;
        $param['excludeNS'] = $this->excludeNS;
        $param['excludePath'] = $this->excludePath;
        $param['externalPath'] = $this->externalPath;
        $remote = [];
        $remote['rooturl'] = '';
        $remote['destination'] = $this->fileGen;
        $remote['name'] = '';
        $remoteint = '1';
        $generatedvia = 'AutoloadOne';
        $date = date('Y/m/d h:i');

        return file_put_contents(
            $this->fileConfig,
            $this->encode(
                [
                    'application' => $generatedvia,
                    'generated' => $date,
                    'local' => $param,
                    'remote' => [$remoteint => $remote]
                ]
            )
        );
    }

    /**
     * @return bool
     */
    private function loadParam()
    {
        if (!_AUTOLOAD_SAVEPARAM) {
            return false;
        }
        $txt = file_get_contents($this->fileConfig);
        if ($txt === false) {
            return false;
        }
        $param = json_decode($txt, true);
        $this->fileGen = $param['local']['fileGen'];
        $this->fileGen = ($this->fileGen == '.') ? $this->rooturl : $this->fileGen;
        $this->savefile = $param['local']['savefile'];
        $this->compression = $param['local']['compression'];
        $this->savefileName = $param['local']['savefileName'];
        $this->excludeNS = $param['local']['excludeNS'];
        $this->excludePath = $param['local']['excludePath'];
        $this->externalPath = $param['local']['externalPath'];
        return true;
    }

    /**
     * @param $value
     *
     * @return string
     */
    private function cleanInputFolder($value)
    {
        $v = str_replace("\r\n", "\n", $value); // remove windows line carriage
        $v = str_replace(",\n", "\n", $v); // remove previous ,\n if any and converted into \n. It avoids duplicate ,,\n
        $v = str_replace("\n", ",\n", $v); // we add ,\n again.
        $v = str_replace('\\,', ',', $v); // we remove trailing \
        $v = str_replace('/,', ',', $v); // we remove trailing /
        return $v;
    }

    /**
     * It compresses the paths
     * 
     * @param string[] $paths An associative array with the paths
     *
     * @return array
     */
    public function compress(&$paths) {
        if(!$this->compression) {
            return [];
        } 
        $arr=$paths;
        $foundIndex=0;
        $found=[];
        foreach($arr as $key=>$item) {

            if(strlen($item)>10) { // we compress path of at least 20 characters.
                $maxcount=0;
                $last=strlen($item);
                for($index=$last;$index>10;$index--) { // we compress up to 20 characters.
                    $sum=0;
                    $findme=substr($item,0,$index);
                    foreach($arr as $item2) {
                        if(strpos($item2,$findme)===0) {
                            $sum+=$index; // it counts the number of characters
                        }
                    }
                    if($sum>$maxcount && $sum>=$index*2) { // it must save at least x2 the number of characters compressed
                        $maxcount=$sum;
                        $foundIndex++;
                        $foundKey='|'.$foundIndex.'|';
                        // replace
                        foreach($arr as $k2=>$item2) {
                            if(strpos($item2,$findme)===0) {
                                $arr[$k2]=str_replace($findme,$foundKey,$item2);
                                $sum+=$index; // it counts the number of characters
                            }
                        }
                        $found[$foundIndex]=$findme;
                    }
                }
            }
        }   
        $paths=$arr; 
        return $found;
    }

    public function init()
    {
        $this->log = '';
        $this->logStat = '';

        if (php_sapi_name() == 'cli') {
            $this->initSapi();
        } else {
            echo 'You should run it as a command line parameter.';
            die(1);
        }
    }

    public function genautoload($file, $namespaces, $namespacesAlt, $pathAbsolute, $autoruns)
    {

        
        
        $template = <<<'EOD'
<?php
/**
 * This class is used for autocomplete.
 * Class _AUTOLOAD_
 * @noautoload it avoids to index this class
 * @generated by AutoLoadOne {{version}} generated {{date}}
 * @copyright Copyright Jorge Castro C - MIT License. https://github.com/EFTEC/AutoLoadOne
 */
${{tempname}}__debug = true;

/* @var string[] Where $_arrautoloadCustom['namespace\Class']='folder\file.php' */
${{tempname}}__arrautoloadCustom = [
{{custom}}
];
${{tempname}}__arrautoloadCustomCommon = [
{{customCommon}}
];

/* @var string[] Where $_arrautoload['namespace']='folder' */
${{tempname}}__arrautoload = [
{{include}}
];
${{tempname}}__arrautoloadCommon = [
{{includeCommon}}
];

/* @var boolean[] Where $_arrautoload['namespace' or 'namespace\Class']=true if it's absolute (it uses the full path) */
${{tempname}}__arrautoloadAbsolute = [
{{includeabsolute}} 
];

/**
 * @param $class_name
 * @throws Exception
 */
function {{tempname}}__auto($class_name)
{
    // its called only if the class is not loaded.
    $ns = dirname($class_name); // without trailing
    $ns = ($ns == '.') ? '' : $ns;
    $cls = basename($class_name);
    // special cases
    if (isset($GLOBALS['{{tempname}}__arrautoloadCustom'][$class_name])) {
        {{tempname}}__loadIfExists($GLOBALS['{{tempname}}__arrautoloadCustom'][$class_name]
            , $class_name,'{{tempname}}__arrautoloadCustomCommon');
        return;
    }
    // normal (folder) cases
    if (isset($GLOBALS['{{tempname}}__arrautoload'][$ns])) {
        {{tempname}}__loadIfExists($GLOBALS['{{tempname}}__arrautoload'][$ns] . '/' . $cls . '{{extension}}'
            , $ns,'{{tempname}}__arrautoloadCommon');
        return;
    }
}

/**
 * We load the file.    
 * @param string $filename
 * @param string $key key of the class it could be the full class name or only the namespace
 * @param string $arrayName [optional] it's the name of the arrayname used to replaced |n| values. 
 * @throws Exception
 */
function {{tempname}}__loadIfExists($filename, $key,$arrayName='')
{
    if (isset($GLOBALS['{{tempname}}__arrautoloadAbsolute'][$key])) {
        $fullFile = $filename; // its an absolute path
        if (strpos($fullFile, '../') === 0) { // Or maybe, not, it's a remote-relative path.
            $oldDir = getcwd();  // we copy the current url
            chdir(__DIR__);
        }
    } else {
        $fullFile = __DIR__ . "/" . {{tempname}}__replaceCurlyVariable($filename,$arrayName); // its relative to this path
    }
    /** @noinspection PhpIncludeInspection */
    if ((include $fullFile) === false) {
        if ($GLOBALS['{{tempname}}__debug']) {
            throw  new Exception("AutoLoadOne Error: Loading file [" . __DIR__ . "/" . $filename . "] for class [" . basename($filename) . "]");
        } else {
            throw  new Exception("AutoLoadOne Error: No file found.");
        }
    } else {
        if (isset($oldDir)) {
            chdir($oldDir);
        }
    }
}
function {{tempname}}__replaceCurlyVariable($string,$arrayName) {
    if(strpos($string,'|')===false) return $string; // nothing to replace.
    return preg_replace_callback('/\\|\s?(\w+)\s?\\|/u', function ($matches) use ($arrayName) {
        $item = is_array($matches) ? substr($matches[0], 1, strlen($matches[0]) - 2)
            : substr($matches, 1, strlen($matches) - 2);
        return $GLOBALS[$arrayName][$item];
    }, $string);
}

spl_autoload_register(function ($class_name) {
    {{tempname}}__auto($class_name);
});
// autorun
{{autorun}}

EOD;
        $includeNotCompressed = $this->createArrayPHP($namespaces);
        $customNotCompressed = $this->createArrayPHP($namespacesAlt);
        
        $commonAbsolute=$this->compress($namespacesAlt);
        $commonNameAbs=$this->compress($namespaces);

        $custom = $this->createArrayPHP($namespacesAlt);
        $htmlCommonAbsolute = $this->createArrayPHP($commonAbsolute);
        $include = $this->createArrayPHP($namespaces);
        
        $htmlCommonNameAbs = $this->createArrayPHP($commonNameAbs);
        
        $includeAbsolute = '';
        foreach ($pathAbsolute as $k => $v) {
            if ($v) {
                $includeAbsolute .= "\t'$k' => true,\n";
            }
        }
        $includeAbsolute = rtrim($includeAbsolute, ",\n");
        $autorun = ''; //
        foreach ($autoruns as $k => $v) {
            $autorun .= "include __DIR__.'$v';\n";
        }
        // 1024 is the memory used by code, *1.3 is an overhead, usually it's mess.
        $this->statByteUsedCompressed = (strlen($include) + strlen($includeAbsolute) + strlen($custom)) * 1.3 + 1024;
        $this->statByteUsed = (strlen($includeNotCompressed) + strlen($htmlCommonAbsolute)+strlen($htmlCommonNameAbs)
                + strlen($includeAbsolute) + strlen($customNotCompressed)) * 1.3 + 1024;

        $template = str_replace('{{custom}}', $custom, $template);
        $template = str_replace('{{include}}', $include, $template);
        $template = str_replace('{{customCommon}}', $htmlCommonAbsolute, $template);
        $template = str_replace('{{includeabsolute}}', $includeAbsolute, $template);
        $template = str_replace('{{includeCommon}}', $htmlCommonNameAbs, $template);
        $template = str_replace('{{tempname}}', uniqid('s'), $template);

        $template = str_replace('{{autorun}}', $autorun, $template);
        $template = str_replace('{{version}}', $this::VERSION, $template);
        $template = str_replace('{{extension}}', $this->extension, $template);
        $template = str_replace('{{date}}', date('Y/m/d h:i:s'), $template);

        
        
        if ($this->savefile) {
            $ok = file_put_contents($file, $template);
            if ($ok) {
                $this->addLog("File <b>$file</b> generated", 'info');
            } else {
                $this->addLog("Unable to write file <b>$file</b>. Check the folder and permissions. You could write it manually.",
                    'error');
                $this->statError++;
            }
            $this->addLog('&nbsp;');
        }

        return $template;
    }
    private function createArrayPHP($array) {
        $result = '';
        foreach ($array as $k => $v) {
            if(is_numeric($k)) {
                $result .= "\t$k => '$v',\n";
            } else {
                $result .= "\t'$k' => '$v',\n";    
            }
            
        }
        return rtrim($result, ",\n");
    }

    public function listFolderFiles($dir)
    {
        $arr = [];
        $this->listFolderFilesAlt($dir, $arr);

        return $arr;
    }

    private function fixRelative($path)
    {
        if (strpos($path, '..') !== false) {
            return getcwd() . '/' . $path;
        }
        return $path;
    }

    public function listFolderFilesAlt($dir, &$list)
    {
        if ($dir === '') {
            return [];
        }
        $ffs = scandir($this->fixRelative($dir));
        if ($ffs === false) {
            $this->addLog("\nError: Unable to reader folder [$dir]. Check the name of the folder and the permissions",
                'error');
            $this->statError++;

            return [];
        }
        foreach ($ffs as $ff) {
            if ($ff != '.' && $ff != '..') {
                if (strlen($ff) >= 5 && substr($ff, -4) == $this->extension) {
                    // PHP_OS_FAMILY=='Windows'
                    $list[] = $dir . '/' . $ff;
                }
                if (is_dir($dir . '/' . $ff)) {
                    $this->listFolderFilesAlt($dir . '/' . $ff, $list);
                }
            }
        }

        return $list;
    }

    /**
     * @param        $filename
     * @param string $runMe
     *
     * @return array
     */
    public function parsePHPFile($filename, &$runMe)
    {
        $runMe = '';
        $r = [];

        try {
            if (is_file($this->fixRelative($filename))) {
                $content = file_get_contents($this->fixRelative($filename));
            } else {
                return [];
            }
            if ($this->debugMode) {
                echo $filename . ' trying token...<br>';
            }
            $tokens = token_get_all($content);
        } catch (Exception $ex) {
            echo "Error in $filename\n";
            die(1);
        }
        foreach ($tokens as $p => $token) {
            if (is_array($token) && ($token[0] == T_COMMENT || $token[0] == T_DOC_COMMENT)) {
                if (strpos($token[1], '@noautoload') !== false) {
                    $runMe = '@noautoload';

                    return [];
                }
                if (strpos($token[1], '@autorun') !== false) {
                    if (strpos($token[1], '@autorunclass') !== false) {
                        $runMe = '@autorunclass';
                    } else {
                        if (strpos($token[1], '@autorun first') !== false) {
                            $runMe = '@autorun first';
                        } else {
                            $runMe = '@autorun';
                        }
                    }
                }
            }
        }
        $nameSpace = '';
        $className = '';
        foreach ($tokens as $p => $token) {
            if (is_array($token) && $token[0] == T_NAMESPACE) {
                // We found a namespace
                $ns = '';
                for ($i = $p + 2; $i < $p + 30; $i++) {
                    if (is_array($tokens[$i])) {
                        $ns .= $tokens[$i][1];
                    } else {
                        // tokens[$p]==';' ??
                        break;
                    }
                }
                $nameSpace = $ns;
            }

            $isClass = false;
            // A class is defined by a T_CLASS + an space + name of the class.
            if (
                is_array($token)
                && ($token[0] == T_CLASS
                    || $token[0] == T_INTERFACE
                    || $token[0] == T_TRAIT)
                && is_array($tokens[$p + 1])
                && $tokens[$p + 1][0] == T_WHITESPACE
            ) {
                $isClass = true;
                if (is_array($tokens[$p - 1]) && $tokens[$p - 1][0] == T_PAAMAYIM_NEKUDOTAYIM
                    && $tokens[$p - 1][1] == '::'
                ) {
                    // /namespace/Nameclass:class <-- we skip this case.
                    $isClass = false;
                }
            }

            if ($isClass) {

                // encontramos una clase
                $min = min($p + 30, count($tokens) - 1);
                for ($i = $p + 2; $i < $min; $i++) {
                    if (is_array($tokens[$i]) && $tokens[$i][0] == T_STRING) {
                        $className = $tokens[$i][1];
                        break;
                    }
                }
                $r[] = ['namespace' => trim($nameSpace), 'classname' => trim($className)];
            }
        } // foreach
        return $r;
    }

    public function genPath($path)
    {

        $path = $this->fixSeparator($path);
        

        if (strpos($path, $this->baseGen) == 0) {
            $min1 = strripos($path, '/');
            $min2 = strripos($this->baseGen . '/', '/');
            $min = min($min1, $min2);
            $baseCommon = $min;

            for ($i = 0; $i < $min; $i++) {
                if (substr($path, 0, $i) != substr($this->baseGen, 0, $i)) {
                    $baseCommon = $i - 2;
                    break;
                }
            }
            // moving down the relative path (/../../)
            $c = substr_count(substr($this->baseGen, $baseCommon), '/');
            $r = str_repeat('/..', $c);
            // moving up the relative path
            $r2 = substr($path, $baseCommon);

            return $r . $r2;
        }
        return substr($path, strlen($this->baseGen));
    }

    public function fixSeparator($fullUrl)
    {
        return str_replace('\\', '/', $fullUrl); // replace windows path for linux path.
    }

    /**
     * returns dir name linux way.
     *
     * @param      $url
     * @param bool $ifFullUrl
     *
     * @return mixed|string
     */
    public function dirNameLinux($url, $ifFullUrl = true)
    {
        $url = trim($url);
        $dir = ($ifFullUrl) ? dirname($url) : $url;
        $dir = $this->fixSeparator($dir);
        return rtrim($dir, '/'); // remove trailing /
    }

    public function addLog($txt)
    {
        echo "\t" . $txt . "\n";
    }

    /**
     * returns the name of the filename if the original filename constains .php then it is not added, otherwise
     * it is added.
     *
     * @return string
     */
    public function getFileName()
    {
        if (strpos($this->savefileName, '.php') === false) {
            return $this->savefileName . $this->extension;
        }
        return $this->savefileName;
    }

    public function process()
    {
        $this->rooturl = $this->fixSeparator($this->rooturl);
        $this->fileGen = $this->fixSeparator($this->fileGen);
        if ($this->rooturl) {
            $this->baseGen = $this->dirNameLinux($this->fileGen . '/' . $this->getFileName());
            $files = $this->listFolderFiles($this->rooturl);
            $filesAbsolute = array_fill(0, count($files), false);

            $extPathArr = explode(',', $this->externalPath);
            foreach ($extPathArr as $ep) {
                $ep = $this->dirNameLinux($ep, false);
                $files2 = $this->listFolderFiles($ep);
                foreach ($files2 as $newFile) {
                    $files[] = $newFile;
                    $filesAbsolute[] = true;
                }
            }
            $ns = [];
            $nsAlt = [];
            $pathAbsolute = [];
            $autoruns = [];
            $autorunsFirst = [];
            $this->excludeNSArr = str_replace(["\n", "\r", ' '], '', $this->excludeNS);
            $this->excludeNSArr = explode(',', $this->excludeNSArr);

            $this->excludePathArr = $this->fixSeparator($this->excludePath);
            $this->excludePathArr = str_replace(["\n", "\r"], '', $this->excludePath);
            $this->excludePathArr = explode(',', $this->excludePathArr);
            foreach ($this->excludePathArr as &$item) {
                $item = trim($item);
            }

            $this->result = '';
            if ($this->button) {
                foreach ($files as $key => $f) {
                    $f = $this->fixSeparator($f);
                    $runMe = '';
                    $pArr = $this->parsePHPFile($f, $runMe);

                    $dirOriginal = $this->dirNameLinux($f);
                    if (!$filesAbsolute[$key]) {
                        $dir = $this->genPath($dirOriginal); //folder/subfolder/f1
                        $full = $this->genPath($f); ///folder/subfolder/f1/F1.php
                    } else {
                        $dir = dirname($f); //D:/Dropbox/www/currentproject/AutoLoadOne/examples/folder
                        $full = $f; //D:/Dropbox/www/currentproject/AutoLoadOne/examples/folder/NaturalClass.php
                    }
                    $urlFull = $this->dirNameLinux($full); ///folder/subfolder/f1
                    $basefile = basename($f); //F1.php

                    if ($runMe != '') {
                        switch ($runMe) {
                            case '@autorun first':
                                $autorunsFirst[] = $full;
                                $this->addLog("Adding autorun (priority): <b>$full</b>");
                                break;
                            case '@autorunclass':
                                $autoruns[] = $full;
                                $this->addLog("Adding autorun (class, use future): <b>$full</b>");
                                break;
                            case '@autorun':
                                $autoruns[] = $full;
                                $this->addLog("Adding autorun: <b>$full</b>");
                                break;
                        }
                    }
                    foreach ($pArr as $p) {
                        $nsp = $p['namespace'];
                        $cs = $p['classname'];
                        $this->statNameSpaces[$nsp] = 1;
                        $this->statNumPHP++;
                        if ($cs != '') {
                            $this->statNumClass++;
                        }

                        $altUrl = ($nsp != '') ? $nsp . '\\' . $cs : $cs; // namespace

                        if ($nsp != '' || $cs != '') {
                            if ((!isset($ns[$nsp]) || $ns[$nsp] == $dir) && $basefile == $cs . $this->extension) {
                                // namespace doesn't exist and the class is equals to the name
                                // adding as a folder
                                $exclude = false;
                                if (in_array($nsp, $this->excludeNSArr) && $nsp != '') {
                                    $this->addLog("\tIgnoring namespace (path specified in <b>Excluded NameSpace</b>): <b>$altUrl -> $full</b>",
                                        'warning');
                                    $exclude = true;
                                }
                                if ($this->inExclusion($dir, $this->excludePathArr)) {
                                    $this->addLog("\tIgnoring relative path (path specified in <b>Excluded Path</b>): <b>$altUrl -> $dir</b>",
                                        'warning');
                                    $exclude = true;
                                }
                                if ($this->inExclusion($dirOriginal, $this->excludePathArr)) {
                                    $this->addLog("\tIgnoring full path (path specified in <b>Excluded Path</b>): <b>$altUrl -> $dirOriginal</b>",
                                        'warning');
                                    $exclude = true;
                                }

                                if (!$exclude) {
                                    if ($nsp == '') {
                                        $this->addLog("Adding Full map (empty namespace): <b>$altUrl -> $full</b> to class <i>$cs</i>");
                                        $nsAlt[$altUrl] = $full;
                                        $pathAbsolute[$altUrl] = $filesAbsolute[$key];
                                    } else {
                                        if (isset($ns[$nsp])) {
                                            $this->addLog("\tReusing the folder: <b>$nsp -> $dir</b> to class <i>$cs</i>",
                                                'success');
                                        } else {
                                            $ns[$nsp] = $dir;
                                            $pathAbsolute[$nsp] = $filesAbsolute[$key];
                                            $this->addLog("Adding Folder as namespace: <b>$nsp -> $dir</b> to class <i>$cs</i>");
                                        }
                                    }
                                }
                            } else {
                                // custom namespace 1-1
                                // a) if filename has different name with the class
                                // b) if namespace is already defined for a different folder.
                                // c) multiple namespaces
                                if (isset($nsAlt[$altUrl])) {
                                    $this->addLog("\tError Conflict:Class with name <b>$altUrl -> $dir</b> is already defined. File $f",
                                        'error');
                                    $this->statConflict++;
                                    if ($this->stop) {
                                        die(1);
                                    }
                                } else {
                                    if ((!in_array($altUrl, $this->excludeNSArr) || $nsp == '')
                                        && !$this->inExclusion($urlFull, $this->excludePathArr)
                                    ) {
                                        $this->addLog("Adding Full: <b>$altUrl -> $full</b> to class <i>$cs</i>");
                                        $nsAlt[$altUrl] = $full;
                                        $pathAbsolute[$altUrl] = $filesAbsolute[$key];
                                    }
                                }
                            }
                        }
                    }
                    if (count($pArr) == 0) {
                        $this->statNumPHP++;
                        if ($runMe == '@noautoload') {
                            $this->addLog("\tIgnoring <b>$full</b> Reason: <b>@noautoload</b> found", 'warning');
                        } else {
                            $this->addLog("\tIgnoring <b>$full</b> Reason: No class found on file.", 'warning');
                        }
                    }
                }
                foreach ($autorunsFirst as $auto) {
                    $this->addLog("Adding file <b>$auto</b> Reason: <b>@autoload first</b> found");
                }
                foreach ($autoruns as $auto) {
                    $this->addLog("Adding file <b>$auto</b> Reason: <b>@autoload</b> found");
                }
                $autoruns = array_merge($autorunsFirst, $autoruns);
                $this->result = $this->genautoload($this->fileGen . '/' . $this->getFileName(), $ns, $nsAlt,
                    $pathAbsolute, $autoruns);
            }
            if ($this->statNumPHP === 0) {
                $p = 100;
            } else {
                $p = round((count($ns) + count($nsAlt)) * 100 / $this->statNumPHP, 2);
            }
            if ($this->statNumClass === 0) {
                $pc = 100;
            } else {
                $pc = round((count($ns) + count($nsAlt)) * 100 / $this->statNumClass, 2);
            }
            $this->addLog('Number of Classes: <b>' . $this->statNumClass . '</b>', 'stat');
            $this->addLog('Number of Namespaces: <b>' . count($this->statNameSpaces) . '</b>', 'stat');
            $this->addLog('<b>Number of Maps:</b> <b>' . (count($ns) + count($nsAlt)) . '</b> (you want to reduce it)',
                'stat');
            $this->addLog('Number of PHP Files: <b>' . $this->statNumPHP . '</b>', 'stat');
            $this->addLog('Number of PHP Autorun: <b>' . count($autoruns) . '</b>', 'stat');
            $this->addLog('Number of conflicts: <b>' . $this->statConflict . '</b>', 'stat');
            if ($this->statError) {
                $this->addLog('Number of errors: <b>' . $this->statError . '</b>', 'staterror');
            }

            $this->addLog('Ratio map per file: <b>' . $p . '%  ' . $this->evaluation($p)
                . '</b> (less is better. 100% means one map/one file)', 'statinfo');
            $this->addLog('Ratio map per classes: <b>' . $pc . '% ' . $this->evaluation($pc)
                . '</b> (less is better. 100% means one map/one class)', 'statinfo');
            $this->addLog('Map size: <b>' . round($this->statByteUsed / 1024, 1)
                . " kbytes</b> (less is better, it's an estimate of the memory used by the map)", 'statinfo');
            $this->addLog('Map size Compressed: <b>' . round($this->statByteUsedCompressed / 1024, 1)
                . " kbytes</b> (less is better, it's an estimate of the memory used by the map)", 'statinfo');
            
        } else {
            $this->addLog('No folder specified');
        }
    }

    private function evaluation($percentage)
    {
        if ($percentage === 0) {
            return 'How?';
        }

        switch (true) {
            case $percentage < 10:
                return 'Awesome';
                break;
            case $percentage < 25:
                return 'Good';
                break;
            case $percentage < 40:
                return 'Acceptable';
                break;
            case $percentage < 80:
                return 'Bad.';
                break;
            default:
                return 'The worst';
        }
        
    }

    /**
     * @param string $path
     * @param string[] $exclusions
     *
     * @return bool
     */
    private function inExclusion($path, $exclusions)
    {
        foreach ($exclusions as $ex) {
            if ($ex != '') {
                if (substr($ex, -1, 1) == '*') {
                    $bool = $this->startswith($path, substr($ex, 0, -1));
                    if ($bool) {
                        return true;
                    }
                }
                if (substr($ex, 0, 1) == '*') {
                    $bool = $this->endswith($path, $ex);
                    if ($bool) {
                        return true;
                    }
                }
                if (strpos($ex, '*') === false && $path == $ex) {
                    return true;
                }
            }
        }

        return false;
    }

    public function startswith($string, $test)
    {
        return substr_compare($string, $test, 0, strlen($test)) === 0;
    }

    public function endswith($string, $test)
    {
        return substr_compare($string, $test, -strlen($test)) === 0;
    }

    public function render()
    {
        $t2 = microtime(true);
        echo "\n" . (round(($t2 - $this->t1) * 1000) / 1000) . " sec. Finished\n";
        return;
    }
} // end class AutoLoadOne

$auto = new AutoLoadOne();
$auto->init();
$auto->process();
$auto->render();

// @noautoload
/*
 * @noautoload
 */
