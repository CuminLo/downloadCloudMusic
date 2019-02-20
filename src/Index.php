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
use FFMpeg\FFProbe;

class Index
{
    private $logger;

    private $executor;

    public $downloadPath;

    public $metadata = false;
    public $precess  = 10;

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

    public function setMetadata(bool $metadata) :Index
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function setPrecess(int $precess) :Index
    {
        if ($precess > 0) {
            $this->precess = $precess;
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

    public function preDownload(string $url) :array
    {
        return $this->executor->getDetail($url);
    }

    public function downloadFile(string $downloadUrl, string $downloadPath) :void
    {
        $this->executor->request->setRequestType('GET');
        $this->executor->request->setUrl($downloadUrl);
        $this->executor->request->setTimeout(60);
        $this->executor->request->download($downloadPath);
    }

    public function starDownload(array $downloadInfo)
    {
        $total = count($downloadInfo);

        $max = $this->precess;
        if ($total < $max) {
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
            } elseif ($pid == 0) { //子进程
                cli_set_process_title('Download Process ' . $i);
                $precessName = 'Process ' . $i;

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
                    $printInfo = sprintf('%s; ID: %s; Song : %s; ', $precessName, $id, $info['name']);
                    if (!$info['url']) {
                        $this->logger->info($printInfo . '地址无法获取, 尝试使用其他渠道...');

                        $api = new Bzqll($this->logger);
                        $otherInfo = $api->getSearch([
                            'title'     => $info['name'],
                            'artists'   => $info['artists'],
                        ]);

                        $info['name']    = $otherInfo['name'] ?? $info['name'];
                        $info['artists'] = $otherInfo['singer'] ?? $info['artists'];
                        $info['url']     = $otherInfo['url'];
                    }

                    $downloadUrl = $info['url'];

                    if (count($info) > 2) { //有的合唱的人太多 受不了
                        $originArName = join('、', $info['artists']);
                        $arName       = join('、', array_slice($info['artists'], 0, 2));
                    } else {
                        $arName         = join('、', $info['artists']);
                        $originArName   = join('、', $info['artists']);
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

                    $fileName = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '&', $fileName);

                    if ($this->metadata) {
                        $filenamePath       = sprintf('%s/%s.%s_1_1', $this->downloadPath, $fileName, $musicType);
                        $realfileName       = str_replace('_1_1', '', $fileName);
                        $realfilenamePath   = sprintf('%s/%s.%s', $this->downloadPath, $realfileName, $musicType);
                        
                        $this->logger->info($printInfo . '开始下载:(临时文件名) ' . $fileName . '_1_1');
    
                        if (!is_file($realfilenamePath)) {
                            $this->downloadFile($downloadUrl, $filenamePath);
                            $this->logger->info($printInfo . ' 数据准备完成，获取元数据: ' . $fileName);
    
                            $ffprobe    = FFProbe::create();
                            try {
                                $audioInfo  = $ffprobe->format($filenamePath);
                            } catch (\Exception $e) { //我怀疑是ffmpeg这个操作有点问题 先这么试一下 发生异常的时候再来一次
                                $audioInfo  = $ffprobe->format($filenamePath);
                            }
                            $tags       = $audioInfo->get('tags');
                            $tagTitle   = $tags['title'] ?? '';
    
                            $audio = false;
                            if (!$tagTitle) {
                                $this->logger->info($printInfo . ' 准备添加元数据 ' . $fileName);
                                $ffmpeg = FFMpeg::create();
                                $audio = $ffmpeg->open($filenamePath);
        
                                $audio->filters()->addMetadata([
                                    'title'     => $info['name'],
                                    'artist'    => $originArName,
                                    'album'     => $alName,
                                ]);
                            }
    
                            if ($audio) {
                                $audio->save(new Mp3(), $realfilenamePath);
                                $this->logger->info($printInfo . ' 元数据添加完成 (真正文件名): ' . $realfileName);
                                unlink($filenamePath);
                            } else {
                                $this->logger->info($printInfo . ' 下载完成(真正文件名): ' . $realfileName);
                                rename($filenamePath, $realfilenamePath);
                            }
                        } else {
                            $this->logger->info($printInfo . ' 当前文件已经存在(真正文件名): ' . $realfilenamePath);
                        }
                    } else {
                        $filenamePath = sprintf('%s/%s.%s', $this->downloadPath, $fileName, $musicType);
                        if (!is_file($filenamePath)) {
                            $this->downloadFile($downloadUrl, $filenamePath);
                            $this->logger->info($printInfo . ' 下载完成: ' . $fileName);
                        } else {
                            $this->logger->info($printInfo . ' 当前文件已经存在: ' . $filenamePath);
                        }
                    }
                }
                exit(0);
            }
        }

        //进程信号通讯不熟悉 先暂时这样好不好...
        $precessNum = 0;
        while (true) {
            if ($precessNum == $max) {
                $this->logger->info('所有歌曲下载完成...');
                exit(0);
            }
            pcntl_waitpid($pid, $status, WUNTRACED);
            if (pcntl_wifexited($status)) {
                $precessNum++;
            }
        }
    }
}
