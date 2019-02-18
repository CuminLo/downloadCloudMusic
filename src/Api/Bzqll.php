<?php
namespace CuminLo\Api;

use CuminLo\MusicInterface;
use CuminLo\Request;
use CuminLo\Nets\NetEase;

class Bzqll implements MusicInterface
{
    const REQUEST_BASE_URL = 'https://api.bzqll.com/music/tencent/';

    private $logger;
    private $key = '579621905';

    public $request;

    public function __construct($logger)
    {
        $this->request = new Request();
        $this->logger  = $logger;
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

    public function playListUrl(): string
    {
        return self::REQUEST_BASE_URL . '/songList?';
    }

    public function getPlayList(string $url): array
    {
        $this->logger->info('获取歌单详情列表...');

        $requestUrl = $this->playListUrl();
        $playListId = $this->getId($url);

        $requestParams = [
            'key'   => $this->key,
            'id'    => $playListId,
        ];

        $requestUrl = sprintf('%s%s', $requestUrl, http_build_query($requestParams));

        $this->request->setUrl($requestUrl);
        $this->request->setRequestType(\CuminLo\Request::REQUEST_METHOD_GET);
        $this->request->execute();

        $body = json_decode($this->request->getResponseBody(), true);

        if ($body['code'] != 200) {
            return [];
        }

        $data = [];
        foreach ($body['data']['songs'] as $song) {
            $temp = [];

            $soundId = $song['id'];

            $artists = [$song['singer']]; //歌手
            $album  = '';
            $picUrl = $song['pic'];

            $temp['name']    = $song['name'];
            $temp['id']      = $soundId;
            $temp['type']    = 'mp3';
            $temp['artists'] = $artists;
            $temp['bitrate'] = '';
            $temp['album']   = $album;
            $temp['pic']     = $picUrl;
            $temp['url']     = $song['url'];

            $data[] = $temp;
        }

        return $data;
    }

    public function songUrl(): string
    {
        return self::REQUEST_BASE_URL . '/song?';
    }

    public function getSong(string $url): array
    {
        $id = $this->getId($url);

        return $this->getSongDetail($id);
    }

    public function getSongDetail($id)
    {
        $requestUrl = $this->songUrl();

        $requestParams = [
            'key'   => $this->key,
            'id'    => $id,
        ];

        $requestUrl = sprintf('%s%s', $requestUrl, http_build_query($requestParams));

        $this->request->setUrl($requestUrl);
        $this->request->setRequestType(\CuminLo\Request::REQUEST_METHOD_GET);
        $this->request->execute();

        $body = json_decode($this->request->getResponseBody(), true);

        if ($body['code'] != 200) {
            return [];
        }

        $song = $body['data'];

        return [
            [
                'name'          => $song['name'],
                'id'            => $song['id'],
                'artists'       => [$song['singer']],
                'album'         => '',
                'bitrate'       => '',
                'type'          => 'mp3',
                'pic'           => $song['pic'],
                'url'           => $song['url'],
            ]
        ];
    }

    public function getId(string $url) :string
    {
        $url = substr($url, strripos($url, '/') + 1);
        $id  = substr($url, 0, strripos($url, '.'));
        return $id;
    }

    public function searchUrl(array $params) :string
    {
        $title  = $params['title'];

        $params = [
            'key'   => '579621905',
            's'     => $title,
            'type'  => 'song',
            'limit' => '50',
            'offset'=> '0'
        ];

        $param = http_build_query($params);
        //https://api.bzqll.com/music/tencent/search?key=579621905&s=莫斯科没有眼泪&limit=10&offset=0&type=song
        return sprintf('%s/search?%s', self::REQUEST_BASE_URL, $param);
    }

    public function getSearch(array $params) :array
    {
        $requestUrl = $this->searchUrl($params);

        $this->request->setRequestType('GET');
        $this->request->setUrl($requestUrl);
        $this->request->execute();

        $response = $this->request->getResponseBody();
        $response = json_decode($response, true);

        if ($response['code'] != 200) {
            return '';
        }

        $data = $response['data'];

        $firstValue = [];
        foreach ($data as $item) {
            if (!$firstValue) {
                $firstValue = $item;
            }
            if ($item['name'] == $params['title']) {
                $artists = join(' ', $params['artists']);
                if (stripos($artists, $item['singer']) !== false) {
                    $apiUrl = $item['url'];
                    return [
                        'url' => $apiUrl
                    ];
                    // $this->request->setUrl($apiUrl);
                    // $this->request->execute();
                    // $this->request->setRequestType('HEAD');
                    // return 'https://dl.stream.qqmusic.qq.com/M50000333KqO2oqLez.mp3?vkey=4638D5F19FE51F2A0989C9155337EE31319D7E16BF29AF7F23A46D7E6B7D4E58AC35D441204EB71C08FBF7C5345A2F61795A0D0DB0C62771&guid=1550464128&uin=0&fromtag=53';
                }
            }
        }
        //暂时这样 后面增加了其他的API就使用其他的API接口
        return [
            'url' => $firstValue['url'],
            'name'=> $firstValue['name'],
            'singer'=> $firstValue['singer'],
        ];
    }
}
