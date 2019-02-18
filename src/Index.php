<?php
namespace CuminLo;

use CuminLo\Nets\NetEase;
use CuminLo\Api\Bzqll;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use FFMpeg\FFMpeg;
use FFMpeg\Media\Audio;
use FFMpeg\Filters\Audio\AddMetadataFilter;
use FFMpeg\Format\Audio\Mp3;

class Index
{
    private $logger;

    private $executor;

    public $downloadPath;

    public function __construct(string $downloadPath)
    {
        $this->logger = new Logger('name');
        $this->logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));

        $this->downloadPath = $downloadPath;

        $this->logger->info('保存目录 ' . $this->downloadPath);

        if (!is_dir($this->downloadPath)) {
            $this->logger->info('目录不存在，正在创建 ...');
            mkdir($this->downloadPath);
            $this->logger->info('创建完成 ...');
        }
    }

    public function download(array $url)
    {
        $this->logger->info('准备下载');

        foreach ($url as $site) {
            $urlInfo = parse_url($site);

            //暂时先这么写
            if ($urlInfo['host'] === 'music.163.com') {
                $this->logger->info('网易云音乐');
                $this->executor = new NetEase($this->logger);
            } elseif ($urlInfo['host'] === 'y.qq.com') {
                $this->logger->info('QQ音乐');
                $this->executor = new Bzqll($this->logger);
            }
    
            $downloadInfo = $this->preDownload($site);
    
            $this->starDownload($downloadInfo);
        }
    }

    public function preDownload(string $url) :array
    {
        return $this->executor->getDetail($url);
    }

    public function starDownload(array $downloadInfo)
    {
        $total = count($downloadInfo);

        $max = 30; //创建最大进程数
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
                    $id = $info['id'];
                    if (!$info['url']) {
                        $this->logger->info('Song ID: ' . $id . ' 地址无法获取，可能是版权问题。');
                        $this->logger->info('尝试使用其他API获取...');
                        $api = new Bzqll();
                        $otherInfo = $api->getSearch([
                            'title'     => $info['name'],
                            'artists'   => $info['artists'],
                        ]);

                        $info['name']    = $otherInfo['name'] ?? $info['name'];
                        $info['artists'] = isset($otherInfo['singer']) ? [$otherInfo['singer']] : $info['artists'];
                        $info['url']     = $otherInfo['url'];
                    }

                    $downloadUrl = $info['url'];

                    $arName = join(' & ', $info['artists']);

                    $alName     = $info['album'];
                    $musicType  = $info['type'];

                    if ($alName) {
                        $fileName = sprintf('%s - %s - %s', $info['name'], $arName, $alName);
                    } else {
                        $fileName = sprintf('%s - %s', $info['name'], $arName);
                    }

                    $fileName = str_replace([ '/' ], '&', $fileName) . '_1';
                    $this->logger->info($prcessName . ' 开始下载:(临时文件名) ' . $fileName);
                    $filenamePath = sprintf('%s/%s.%s', $this->downloadPath, $fileName, $musicType);

                    $realfileName   = str_replace('_1', '', $fileName);
                    $realfilenamePath = sprintf('%s/%s.%s', $this->downloadPath, $realfileName, $musicType);

                    if (!is_file($realfilenamePath)) {
                        $this->executor->request->setRequestType('GET');
                        $this->executor->request->setUrl($downloadUrl);
                        $this->executor->request->setTimeout(60);
                        $this->executor->request->download($filenamePath);
                        $this->logger->info($prcessName . ' 下载完成: ' . $fileName);
                        $this->logger->info($prcessName . ' 准备添加元数据 ' . $fileName);

                        $ffmpeg = FFMpeg::create();
                        $audio = $ffmpeg->open($filenamePath);
                        $audio->filters()->addMetadata([
                            'title'     => $info['name'],
                            'artist'    => $arName,
                            'album'     => $alName,
                        ]);
                        $audio->save(new Mp3(), $realfilenamePath);
                        $this->logger->info($prcessName . ' 元数据添加完成 (真正文件名): ' . $realfileName);
                        unlink($filenamePath);
                    } else {
                        $this->logger->info($prcessName . ' 当前文件已经存在(真正文件名): ' . $realfilenamePath);
                    }
                }
                exit(0);
            }
        }
    }
}
