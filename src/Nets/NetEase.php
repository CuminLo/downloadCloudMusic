<?php
namespace CuminLo\Nets;

use CuminLo\MusicInterface;

class NetEase implements MusicInterface
{
    public $request;
    public $logger;

    public function __construct($logger)
    {
        $this->request  = new \CuminLo\Request();
        $this->logger   = $logger;
    }

    public function playListUrl() :string
    {
        return 'https://music.163.com/api/v3/playlist/detail';
    }

    public function songUrl() :string
    {
        return 'http://music.163.com/api/song/detail';
    }

    public function searchUrl(array $params) :string
    {
        return '';
    }

    public function getSearch(array $params): array
    {
        return [];
    }

    public function getDetail(string $url) :array
    {
        if (stripos($url, 'song') !== false) { //单曲
            $this->logger->info('单曲');
            return $this->getSong($url);
        } else {
            $this->logger->info('歌单');
            return $this->getPlayList($url);
        }
    }

    public function getSong(string $url) :array
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

        $playListRequestUrl = $this->playListUrl();

        $playListId = $this->getId($url);

        $requestParams = [
            'id'    => $playListId,
            's'     => 0,
            'n'     => '1000',
            't'     => '0',
        ];

        $this->request->setUrl($playListRequestUrl);
        $this->request->setRequestType(\CuminLo\Request::REQUEST_METHOD_POST);
        $this->request->setPostFields($requestParams);

        $this->request->execute();

        $body = json_decode($this->request->getResponseBody(), true);

        $tracks = $body['playlist']['tracks'];
        $this->logger->info('歌单详情列表获取完成，准备拼装数据');

        $data = [];

        foreach ($tracks as $track) {
            $temp = [];

            $soundId = $track['id'];

            $artists = []; //歌手
            foreach ($track['ar'] as $v) {
                $artists[] = $v['name'];
            }

            $album  = $track['al']['name'];
            $picUrl = $track['al']['picUrl'];

            $musicInfo = $track['h'];

            $temp['name']    = $track['name'];
            $temp['id']      = $soundId;
            $temp['type']    = 'mp3';
            $temp['artists'] = $artists;
            $temp['bitrate'] = $musicInfo['br'];
            $temp['album']   = $album;
            $temp['pic']     = $picUrl;
            $temp['url']     = $this->getDownloadRealUrl($soundId);

            $data[] = $temp;
        }

        return $data;
    }

    public function getSongDetail($id)
    {
        $requestUrl = $this->songUrl();

        $requestParams = [
            'ids'   => json_encode([$id]),
        ];

        $this->request->setUrl($requestUrl);
        $this->request->setRequestType(\CuminLo\Request::REQUEST_METHOD_POST);
        $this->request->setPostFields($requestParams);
        $this->request->execute();

        $body = json_decode($this->request->getResponseBody(), true);
        $songs = $body['songs'];

        foreach ($songs as $song) {
            if ($song['id'] != $id) {
                continue;
            }

            $artists = []; //歌手
            foreach ($song['artists'] as $v) {
                $artists[] = $v['name'];
            }

            $album = $song['album']['name']; //专辑

            $musicInfo = $song['hMusic'];

            return [
                [
                    'name'          => $song['name'],
                    'id'            => $song['id'],
                    'artists'       => $artists,
                    'album'         => $album,
                    'bitrate'       => $musicInfo['bitrate'],
                    'type'          => $musicInfo['extension'],
                    'pic'           => $song['album']['picUrl'],
                    'url'           => $this->getDownloadRealUrl($id)
                ]
            ];
        }
    }
    
    public function getDownloadRealUrl(string $id) :string
    {
        $this->logger->info('Song ID: ' . $id .  ' 准备获取歌曲下载的地址...');
        $requestUrl = 'http://music.163.com/api/song/enhance/player/url';

        $requestParams = [
            'ids' => json_encode([$id]),
            'br'  => '320000',
        ];

        $this->request->setUrl($requestUrl);
        $this->request->setRequestType(\CuminLo\Request::REQUEST_METHOD_POST);
        $this->request->setPostFields($requestParams);
        $this->request->execute();

        $body = json_decode($this->request->getResponseBody(), true);

        if (!isset($body['data'])) { //发生错误
            return $body['msg'] . $body['code'];
        }

        foreach ($body['data'] as $item) {
            if ($item['id'] == $id) {
                if (!$item['url']) {
                    $this->logger->info('Song ID: ' . $id . ' 地址无法获取，可能是版权问题。');
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
