<?php
namespace NetEase;

use Request\Request;
use Logger\Logger;

class NetEase
{
    public $request;
    public $logger;

    public function __construct()
    {
        include __DIR__ . '/Request.php';

        $this->request  = new Request();
        $this->logger   = new Logger;
    }

    public function getDownloadUrl(string $url) :array
    {
        if (stripos($url, 'song') !== false) { //单曲
            $this->logger->info('单曲');
            return $this->getSong($url);
        } else {
            $this->logger->info('歌单');
            return $this->getPlayList($url);
        }
    }

    public function getSong(string $url)
    {
        $id = $this->getId($url);
        return $this->getSongDetail($id);
    }

    public function getId(string $url) :string
    {
        $id = substr($url, stripos($url, '=') + 1);
        return $id;
    }

    public function getPlayList(string $url) :array
    {
        $this->logger->info('获取歌单详情列表...');

        $playListRequestUrl = 'https://music.163.com/api/v3/playlist/detail';

        $playListId = $this->getId($url);

        $requestParams = [
            'id'    => $playListId,
            's'     => 0,
            'n'     => '1000',
            't'     => '0',
        ];

        $this->request->setUrl($playListRequestUrl);
        $this->request->setRequestType(Request::REQUEST_METHOD_POST);
        $this->request->setPostFields($requestParams);

        $this->request->execute();

        $body = json_decode($this->request->getResponseBody(), true);

        $tracks = $body['playlist']['tracks'];
        $this->logger->info('歌单详情列表获取完成，准备拼装数据');

        $data = [];

        foreach ($tracks as $track) {
            $temp = [];

            $soundId = $track['id'];

            $temp['name']   = $track['name'];
            $temp['id']     = $soundId;
            $temp['ar']     = $track['ar'];
            $temp['al']             = $track['al'];
            $temp['download_url']   = $this->getDownloadRealUrl($soundId);

            $data[] = $temp;
        }

        return $data;
    }

    public function getSongDetail($id)
    {
        $requestUrl = 'http://music.163.com/api/song/detail';

        $requestParams = [
            'ids'   => json_encode([$id]),
        ];

        $this->request->setUrl($requestUrl);
        $this->request->setRequestType(Request::REQUEST_METHOD_POST);
        $this->request->setPostFields($requestParams);
        $this->request->execute();

        $body = json_decode($this->request->getResponseBody(), true);
        $songs = $body['songs'];

        foreach ($songs as $song) {
            if ($song['id'] != $id) {
                continue;
            }

            return [
                [
                    'name'          => $song['name'],
                    'id'            => $song['id'],
                    'ar'            => $song['artists'],
                    'al'            => $song['album'],
                    'download_url'  => $this->getDownloadRealUrl($id)
                ]
            ];
        }
    }
    
    public function getDownloadRealUrl(string $id) :string
    {
        $this->logger->info('Song ID:' . $id .  ' 准备获取歌曲下载的地址...');
        $requestUrl = 'http://music.163.com/api/song/enhance/player/url';

        $requestParams = [
            'ids' => json_encode([$id]),
            'br'  => '320000',
        ];

        $this->request->setUrl($requestUrl);
        $this->request->setRequestType(Request::REQUEST_METHOD_POST);
        $this->request->setPostFields($requestParams);
        $this->request->execute();

        $body = json_decode($this->request->getResponseBody(), true);

        if (!isset($body['data'])) { //发生错误
            return $body['msg'] . $body['code'];
        }

        foreach ($body['data'] as $item) {
            if ($item['id'] == $id) {
                if (!$item['url']) {
                    //todo
                    //url不存在可能是说明 版权问题 不给播放
                    //后期增加使用其他的渠道来获取
                    $this->logger->info('地址不存在，可能是版权问题。');
                    return '';
                } else {
                    $realUrl = $item['url'];
                    $this->logger->info('地址获取成功: ' . $realUrl);
                    return $realUrl;
                }
            }
        }
    }
}
