<?php
namespace Face;

use NetEase\NetEase;
use Logger\Logger;

class Face
{
    private $own;
    private $logger;

    public $downloadPath;


    public function __construct(string $downloadPath)
    {
        include __DIR__ . '/Logger.php';
        $this->logger = new Logger();

        $this->downloadPath = $downloadPath;

        $this->logger->info('保存目录 ' . $this->downloadPath);

        if (!is_dir($this->downloadPath)) {
            $this->logger->info('目录不存在，正在创建 ...');
            mkdir($this->downloadPath);
            $this->logger->info('创建完成 ...');
        }
    }

    public function download(string $url)
    {
        $urlInfo = parse_url($url);

        $this->logger->info('准备下载');

        //暂时先这么写
        if ($urlInfo['host'] === 'music.163.com') {
            $this->logger->info('网易云音乐');
            include __DIR__ . '/NetEase.php';
            $this->own = new NetEase();
        }

        //todo... 根据url不同来预下载 只是为了获取下载url
        $downloadInfo = $this->preDownload($url);

        $this->starDownload($downloadInfo);
    }

    public function preDownload(string $url) :array
    {
        return $this->own->getDownloadUrl($url);
    }

    public function starDownload(array $downloadInfo)
    {
        //这个工具不想使用太多的第三方代码
        //这里使用PHP原生进程来做多进程
        //其实使用Swoole的协程来做会更好

        //创建10个进程 根据下载的总数 几个进程平均分
        $total = count($downloadInfo);

        $max = 10; //创建最大进程数
        if ($total <= $max) {
            $max = 1;
        }

        $task = intval(floor($total / $max));

        for ($i = 1; $i <= $max; $i ++) {
            $this->logger->info('正在创建进程 ' . $i);
            $pid = pcntl_fork();

            if ($pid == -1) {
                $this->logger->info('创建进程失败');
                exit(1);
            }

            if ($pid) { //父进程
                cli_set_process_title('Download Process Master');

                pcntl_signal(SIGHUP, function () use ($pid) {
                    $this->logger->info('收到子进程退出的信号');
                    pcntl_waitpid($pid, $status, WNOHANG);
                });
            } elseif ($pid == 0) { //子进程
                cli_set_process_title('Download Process ' . $i);
                $prcessName = 'Process ' . $i;

                //每个进程处理自己的下载地址
                $offset = ($i - 1) * $task;
                $limit  = $task;

                if ($i == $max) { //最后一个直接取到最后
                    $downloadInfo = array_slice($downloadInfo, $offset);
                } else {
                    $downloadInfo = array_slice($downloadInfo, $offset, $limit);
                }
                
                foreach ($downloadInfo as $info) {
                    if (!$info['download_url']) {
                        continue;
                    }
                    $downloadUrl = $info['download_url'];

                    $fielName = $info['name'];
                    $fielName = str_replace([
                        '/'
                    ], '&', $fielName);

                    $this->logger->info($prcessName . ' 开始下载: ' . $fielName);

                    $filenamePath = sprintf('%s/%s.mp3', $this->downloadPath, $fielName);

                    if (!is_file($filenamePath)) {
                        $this->own->request->setRequestType('GET');
                        $this->own->request->setUrl($downloadUrl);
                        $this->own->request->setTimeout(60);
                        $this->own->request->download($filenamePath);

                        $this->logger->info($prcessName . ' 下载完成: ' . $fielName);
                    } else {
                        $this->logger->info($prcessName . ' 当前文件已经存在: ' . $fielName);
                    }
                }
                exit(0);
            }
        }
    }
}
