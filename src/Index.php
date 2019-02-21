<?php
namespace CuminLo;

use CuminLo\Nets\NetEase;
use CuminLo\Api\Bzqll;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use FFMpeg\FFMpeg;
use FFMpeg\Format\Audio\Mp3;

class Index
{
    private $logger;

    private $executor;

    public $downloadPath;

    public $metadata    = false;
    public $process     = 20;

    public function __construct(string $downloadPath)
    {
        $this->logger = new Logger('name');
        $this->logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));

        $this->downloadPath = $downloadPath;

        $this->logger->info('保存目录 ' . $this->downloadPath);

        if (!is_dir($this->downloadPath)) {
            $this->logger->info('目录不存在，正在创建 ...');
            mkdir($this->downloadPath);
            $this->logger->info('创建完成');
        }
    }

    public function setMetadata(bool $metadata) : Index
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function setProcess(int $process) : Index
    {
        if ($process > 0 && $process <= $this->process) {
            $this->process = $process;
        }
        return $this;
    }

    public function download(array $url)
    {
        $this->logger->info('准备下载');

        $downloadInfos = [];
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

            $downloadInfos = array_merge($downloadInfos, $downloadInfo);
        }

        $this->starDownload($downloadInfos);
    }

    public function preDownload(string $url) : array
    {
        return $this->executor->getDetail($url);
    }

    public function downloadFile(string $downloadUrl, string $downloadPath) : void
    {
        $this->executor->request->setRequestType('GET');
        $this->executor->request->setUrl($downloadUrl);
        $this->executor->request->setTimeout(3600); //尽可能的保证在多进程下载能够完成
        $this->executor->request->setConnectTimeout(60);
        $this->executor->request->download($downloadPath);
    }

    public function addMetadata(array $metadata, string $filenamePath)
    {
        try {
            $ffmpeg = FFMpeg::create();
            $audio = $ffmpeg->open($filenamePath);

            $audio->filters()->addMetadata($metadata);
            return $audio;
        } catch (\Exception $e) {
            throw new \Exception();
            unlink($filenamePath);
        }
    }

    public function starDownload(array $downloadInfo)
    {
        $total = count($downloadInfo);

        $max = $this->process;
        if ($total <= $max) {
            $max = $total;
        }

        $task = intval(floor($total / $max));

        $childProcess = [];

        for ($i = 1; $i <= $max; $i++) {
            $this->logger->info('正在创建进程 ' . $i);
            $pid = pcntl_fork();

            if ($pid == -1) {
                $this->logger->info('创建进程失败');
                exit(1);
            }

            if ($pid) { //父进程
                cli_set_process_title('Download Process Master');
                $childProcess[] = $pid;
            } elseif ($pid == 0) { //子进程
                $processName = 'Process ' . $i;
                cli_set_process_title('Download ' . $processName);

                //每个进程处理自己的下载地址
                $offset = ($i - 1) * $task;
                $limit = $task;

                if ($i == $max) { //最后一个直接取到最后
                    $downloadInfo = array_slice($downloadInfo, $offset);
                } else {
                    $downloadInfo = array_slice($downloadInfo, $offset, $limit);
                }

                foreach ($downloadInfo as $info) {
                    $id = $info['id'];
                    $printInfo = sprintf('%s; ID: %s; Song : %s; ', $processName, $id, $info['name']);
                    if (!$info['url']) {
                        $this->logger->info($printInfo . '地址无法获取, 尝试使用其他渠道...');

                        $api = new Bzqll($this->logger);
                        $otherInfo = $api->getSearch([
                            'title'     => $info['name'],
                            'artists'   => $info['artists'],
                        ]);

                        if (!$otherInfo) {
                            $this->logger->info($printInfo . '无法获取歌曲地址', $info);
                            continue;
                        }

                        $this->logger->info($printInfo . '其他渠道获取到音乐地址：' . $otherInfo['url']);

                        $info['name']    = $otherInfo['name'] ?? $info['name'];
                        $info['artists'] = $otherInfo['singer'] ?? $info['artists'];
                        $info['url']     = $otherInfo['url'];
                    }

                    $downloadUrl = $info['url'];

                    if (!$downloadUrl) {
                        $this->logger->info($printInfo . '无法获取歌曲地址', $info);
                        continue;
                    }

                    if (count($info) > 2) { //合唱的人太多 受不了
                        $originArName = join('、', $info['artists']);
                        $arName = join('、', array_slice($info['artists'], 0, 2));
                    } else {
                        $arName = join('、', $info['artists']);
                        $originArName = join('、', $info['artists']);
                    }

                    $alName     = $info['album'];
                    $musicType  = $info['type'];

                    if ($alName) {
                        if ($info['name'] == $alName) {
                            $fileName = sprintf('%s - %s', $info['name'], $arName);
                        } else {
                            $fileName = sprintf('%s - %s - %s', $info['name'], $arName, $alName);
                        }
                    } else {
                        $fileName = sprintf('%s - %s', $info['name'], $arName);
                    }

                    $fileName       = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|', '+'], '&', $fileName); //文件名的特殊字符
                    $filenamePath   = sprintf('%s/%s.%s', $this->downloadPath, $fileName, $musicType);

                    if (is_file($filenamePath)) {
                        $this->logger->info($printInfo . ' 当前文件已经存在: ' . $filenamePath);
                        continue;
                    }

                    if ($this->metadata) {
                        $filenamePath       = sprintf('%s_1_1', $filenamePath);
                        $realfileName       = str_replace('_1_1', '', $fileName);
                        $realfilenamePath   = sprintf('%s/%s.%s', $this->downloadPath, $realfileName, $musicType);

                        $this->logger->info($printInfo . '开始下载:(临时文件名) ' . $filenamePath); //处理元数据新建一个临时文件

                        $this->downloadFile($downloadUrl, $filenamePath);

                        $metadataArr = [
                            'title'     => $info['name'],
                            'artist'    => $originArName,
                            'album'     => $alName,
                        ];

                        $audio = $this->addMetadata($metadataArr, $filenamePath);
                        $audio->save(new Mp3(), $realfilenamePath);
                        $this->logger->info($printInfo . ' 元数据添加完成 (真正文件名): ' . $realfileName);
                        unlink($filenamePath);
                    } else {
                        $this->downloadFile($downloadUrl, $filenamePath);
                        $this->logger->info($printInfo . ' 下载完成: ' . $fileName);
                    }
                    usleep(1000);
                }
                exit(0); //子进程退出循环
            }
        }

        $err = [];
        while (count($childProcess) > 0) {
            foreach ($childProcess as $key => $pid) {
                $res = pcntl_waitpid($pid, $status, WNOHANG);
                if ($res == -1 || $res > 0) {
                    $code = pcntl_wexitstatus($status);
                    if (!pcntl_wifexited($status)) {
                        $err[$key] = '进程异常退出，部分歌曲未下载完成，建议重新下载; 错误码: ' . $code;
                    }
                    if ($status > 0) {
                        $err[$key] = '进程异常退出，部分歌曲未下载完成，建议重新下载; 错误码: ' . $code;
                    }
                    unset($childProcess[$key]);
                    usleep(10000);
                }
            }
            usleep(1000);
        }

        if ($err) {
            $this->logger->info('下载过程中程序发生异常，部分歌曲未下载完成', $err);
        } else {
            $this->logger->info('所有歌曲下载成功！');
        }
    }
}
