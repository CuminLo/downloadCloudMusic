<?php
namespace CuminLo\Nets;

use CuminLo\MusicInterface;
use Metowolf\Meting;

class NetEase implements MusicInterface
{
    public $request;
    public $logger;
    public $api;

    public function __construct($logger)
    {
        $this->request  = new \CuminLo\Request();
        $this->request->setReferer('https://music.163.com/');

        $this->logger   = $logger;
        $this->api      = new Meting('netease');
        $this->api->format(false);
    }

    public function playListUrl() :string
    {
        return '';
    }

    public function songUrl() :string
    {
        return '';
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
            return $this->getSong($url);
        } elseif (stripos($url, 'artist')) { //歌手
            return $this->getArtist($url);
        } elseif (stripos($url, 'album')) { //专辑
            return $this->getAlbum($url);
        } else {
            return $this->getPlayList($url);
        }
    }

    public function getSong(string $url) :array
    {
        $id = $this->getId($url);

        return [$this->getSongDetail($id)];
    }

    public function getId(string $url) :string
    {
        $id = substr($url, stripos($url, '=') + 1);
        return $id;
    }

    public function makeSongs(string $songs) :array
    {
        $songs = json_decode($songs, true);
        $data = [];
        foreach ($songs as $song) {
            $id = $song['id'];
            $data[] = $this->getSongDetail($id);
        }
        return $data;
    }

    public function makeSongDetail(array $song)
    {
        $id = $song['id'];

        $artists = []; //歌手
        if (isset($song['ar'])) {
            foreach ($song['ar'] as $v) {
                $artists[] = $v['name'];
            }
        }
        if (isset($song['artist'])) {
            $artists = $song['artist'];
        }

        $album = $song['al'] ?? $song['album'] ?? '';
        $album = $album['name'] ?? '';

        $musicInfo = $song['h'] ?? $song['m'] ?? $song['l'] ?? '';

        $br = $musicInfo['bitrate'] ?? '320000';

        return [
            'name'          => $song['name'],
            'id'            => $song['id'],
            'artists'       => $artists,
            'album'         => $album,
            'bitrate'       => $br,
            'type'          => $musicInfo['extension'] ?? 'mp3',
            'url'           => $this->getDownloadRealUrl($id, $br)
        ];
    }
    
    public function getArtist(string $url) :array
    {
        $this->logger->info('获取歌手热门列表...');

        $id = $this->getId($url);

        $songs = $this->api->format(true)->artist($id);

        $this->logger->info('歌手列表获取完成');

        return $this->makeSongs($songs);
    }

    public function getAlbum(string $url) :array
    {
        $this->logger->info('获取专辑列表...');

        $id = $this->getId($url);

        $songs = $this->api->format(true)->album($id);

        $this->logger->info('专辑详情列表获取完成');

        return $this->makeSongs($songs);
    }

    public function getPlayList(string $url) :array
    {
        $this->logger->info('获取歌单详情列表...');

        $playListId = $this->getId($url);

        $songs = $this->api->format(true)->playlist($playListId);

        $this->logger->info('歌单详情列表获取完成');

        return $this->makeSongs($songs);
    }

    public function getSongDetail(string $id)
    {
        $this->api->format(false);
        
        $songs = $this->api->song($id);

        $songs = json_decode($songs, true)['songs'];

        foreach ($songs as $song) {
            if ($song['id'] != $id) {
                continue;
            }

            $artists = []; //歌手
            foreach ($song['ar'] as $v) {
                $artists[] = $v['name'];
            }

            $album = $song['al']['name']; //专辑

            $musicInfo = $song['h'] ?? $song['m'] ?? $song['l'] ?? '';

            $br = $musicInfo['bitrate'] ?? '320000';

            return $this->makeSongDetail($song);
        }
    }

    public function getDownloadRealUrl(string $id, int $br = 320000) :string
    {
        $this->logger->info('Song ID: ' . $id .  ' 准备获取歌曲下载的地址...');

        $body = $this->api->url($id);

        $body = json_decode($body, true);

        if (!isset($body['data'])) { //发生错误
            $this->logger->info('Song ID: ' . $id . ' 地址无法获取');
            return '';
        }

        foreach ($body['data'] as $item) {
            if ($item['id'] == $id) {
                if (!$item['url']) {
                    $this->logger->info('Song ID: ' . $id . ' 地址无法获取');
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
