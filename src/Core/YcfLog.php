<?php
namespace Ycf\Core;

class YcfLog
{
    const LEVEL_TRACE   = 'trace';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR   = 'error';
    const LEVEL_INFO    = 'info';
    const LEVEL_PROFILE = 'profile';
    const MAXLOGS       = 10000;
    //单个类型log
    private $logs     = array();
    private $logCount = 0;
    private $logPath  = '';

    public function formatLogMessage($message, $level, $category, $time)
    {
        return @date('Y/m/d H:i:s', $time) . " [$level] [$category] $message\n";
    }

    public function log($message, $level = 'info', $category = 'application', $flush = false)
    {
        $this->logs[$category][] = array($message, $level, $category, microtime(true));
        $this->logCount++;
        if ($this->logCount >= YcfLog::MAXLOGS || true == $flush) {
            $this->flush($category);
        }
    }

    public function processLogs()
    {
        $this->log("[" . $_SERVER['REQUEST_URI'] . "] " . "[runing time]: " . (microtime(true) - YCF_BEGIN_TIME) . "\n");
        foreach ((array) $this->logs as $key => $logs) {
            $logsAll[$key] = '';
            foreach ((array) $logs as $log) {
                $logsAll[$key] .= $this->formatLogMessage($log[0], $log[1], $log[2], $log[3]);
            }
        }
        //file_put_contents(ROOT_PATH . 'src/runtime/swoole.log', serialize($logsAll));
        //var_dump($logsAll);
        return $logsAll;
    }
    /**
     *
     * 写日志到文件
     */
    public function flush()
    {

        if ($this->logCount <= 0) {
            return false;
        }
        $logsAll = $this->processLogs();
        $this->write($logsAll);
        $this->logs     = array();
        $this->logCount = 0;
    }
    //异步任务写日志
    public function sendTask()
    {
        $logsAll = $this->processLogs();
        if (empty($logsAll)) {
            return false;
        }
        $param = array(
            'action'  => 'flushLog',
            'name'    => '日志处理',
            'content' => $logsAll,
        );
        $taskId = YcfCore::$httpServer->task(json_encode($param));

    }
    /**
     * [write 根据日志类型写到不同的日志文件]
     * @return [type] [description]
     */
    public function write($logsAll)
    {
        if (empty($logsAll)) {
            return;
        }

        $this->logPath = ROOT_PATH . 'src/runtime/';
        if (!is_dir($this->logPath)) {
            self::mkdir($this->logPath, array(), true);
        }
        foreach ($logsAll as $key => $value) {
            if (empty($key)) {
                continue;
            }
            $fileName = $this->logPath . $key . '.log';
            $fp2      = @fopen($fileName, "a+") or YcfUtils::exitMsg("Log fatal Error !");
            @fwrite($fp2, $value);
            @fclose($fp2);
        }

    }

    /**
     * Shared environment safe version of mkdir. Supports recursive creation.
     * For avoidance of umask side-effects chmod is used.
     *
     * @param string $dst path to be created
     * @param array $options newDirMode element used, must contain access bitmask
     * @param boolean $recursive whether to create directory structure recursive if parent dirs do not exist
     * @return boolean result of mkdir
     * @see mkdir
     */
    private static function mkdir($dst, array $options, $recursive)
    {
        $prevDir = dirname($dst);
        if ($recursive && !is_dir($dst) && !is_dir($prevDir)) {
            self::mkdir(dirname($dst), $options, true);
        }

        $mode = isset($options['newDirMode']) ? $options['newDirMode'] : 0777;
        $res  = mkdir($dst, $mode);
        @chmod($dst, $mode);
        return $res;
    }

}
