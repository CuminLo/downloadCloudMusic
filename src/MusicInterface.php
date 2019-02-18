<?php
namespace CuminLo;

interface MusicInterface
{
    /**
     * 获取请求的歌单链接地址
     */
    public function playListUrl() :string;

    /**
     * 获取歌单详情
     */
    public function getPlayList(string $url) :array;

    /**
     * 获取请求单曲的链接地址
     */
    public function songUrl() :string;

    /**
     * 获取单曲详情
     */
    public function getSong(string $url) :array;

    /**
     * 获取请求的搜索链接地址
     */
    public function searchUrl(array $params) :string;

    /**
     * 获取搜索结果 根据params直接匹配返回url
     */
    public function getSearch(array $params) :array;

    /**
     * 不管是歌曲还是歌单
     * [
            [
                'id'    => '123456',
                'url'   => 'http://', 'music url',
                'name'  => '名称',
                'pic'   => '',
                'lrc'   => '歌词',
                'singer'=> '歌手',
                'type'  => '音乐格式',
                'album' => '专辑',
            ]
            ...
        ]
     */
    public function getDetail(string $url) :array;
}
