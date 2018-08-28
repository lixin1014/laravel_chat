<?php

namespace App\Http\Controllers;

use App\Http\Model\FriendsModel;
use App\Http\Model\NewsModel;
use App\Http\Model\UserModel;
use App\Http\Tools\Common;
use Illuminate\Http\Request;

use ErrorException;

class NewsController extends Controller
{
    private $user_model;
    private $news_model;
    private $friend_model;
    private $common;
    private $news_type = [
        '10100001' => ' 想添加您为好友',
        '10100002' => ' 已同意您的好友申请',
        '10100003' => ' 已拒绝您的好友申请',
    ];

    public function __construct()
    {
        $this->user_model = new UserModel();
        $this->news_model = new NewsModel();
        $this->friend_model = new FriendsModel();
        $this->common = new Common();
    }

    /**
     * 新增消息
     * @param Request $request
     * @return string
     */
    public function store(Request $request)
    {
        $send_to_user = $this->user_model->getUserInfo(['id' => $request->send_to_id]);

        if ($send_to_user) {
            $login_user = $request->session()->get('userInfo');

            try {
                $id = $this->news_model->insertNews([
                    'send_by' => $login_user->id,
                    'send_to' => $request->send_to_id,
                    'news_type' => $request->news_type,
                    'content' => $login_user->username . $this->news_type[$request->news_type],
                    'created_at' => time()
                ]);

                if ($id) {
                    return json_encode([
                        'code' => 200,
                        'message' => 'OK',
                        'data' => [
                            'send_by_id' => $login_user->id,
                            'send_by_username' => $login_user->username,
                            'send_to_id' => $request->send_to_id,
                            'send_to_username' => $send_to_user->username,
                            'content' => $login_user->username . $this->news_type[$request->news_type]
                        ]
                    ]);
                } else {
                    return json_encode(['code' => 401, 'message' => '消息添加失败']);
                }
            } catch (ErrorException $e) {
                return json_encode(['code' => 402, 'message' => '参数错误']);
            }
        } else {
            return json_encode(['code' => 400, 'message' => '该用户不存在']);
        }
    }

    /**
     * 获取消息列表
     * @param Request $request
     * @return string
     */
    public function index(Request $request)
    {
        $login_user = $request->session()->get('userInfo');
        $news = $this->news_model->getNews(['send_to' => $login_user->id]);
        foreach ($news as $k => $item) {
            $news[$k]->created_at = $this->common->convertTime($item->created_at);
        }

        $unread = $this->news_model->getNews(['send_to' => $login_user->id, 'status' => '0'], ['*'], true);

        return json_encode([
            'code' => 200,
            'message' => 'OK',
            'data' => [
                'unread' => $unread,
                'list' => $news->toArray(),
            ]
        ]);
    }

    /**
     * 读消息
     * @param $id
     * @return string
     */
    public function read($id)
    {
        $news = $this->news_model->getNews(['id' => $id, 'status' => '0']);
        if (count($news)) {
            $this->news_model->updateNews(['id' => $news[0]->id], ['status' => '1']);
        }
        return json_encode(['code' => 200, 'message' => 'OK']);
    }

    /**
     * 处理同意/拒绝好友请求
     * @param Request $request
     * @return string
     */
    public function process(Request $request)
    {
        try {
            $login_user = $request->session()->get('userInfo');
            $all_request_news = $this->news_model->getNews([
                'send_by' => $request->send_by_id,
                'send_to' => $login_user->id,
                'status' => '0'
            ],
                ['id']
            );
            $update_result = $this->news_model->updateNews([
                'in' => ['id' => $all_request_news->toArray()]],
                ['status' => '1']
            );
            $id = $this->news_model->insertNews([
                'send_by' => $login_user->id,
                'send_to' => $request->send_by_id,
                'content' => $login_user->username . $this->news_type[$request->news_type],
                'news_type' => $request->news_type,
                'created_at' => time()
            ]);
            if ($update_result && $id) {
                //  判断是否同意加为好友
                if ($request->news_type == 10100002) {
                    $id1 = $this->friend_model->newFriend([
                        'user1' => $login_user->id,
                        'user2' => $request->send_by_id,
                        'created_at' => time()
                    ]);

                    $id2 = $this->friend_model->newFriend([
                        'user1' => $request->send_by_id,
                        'user2' => $login_user->id,
                        'created_at' => time()
                    ]);

                    if ($id1 && $id2) {
                        return json_encode(['code' => 200, 'message' => 'OK']);
                    } else {
                        $this->friend_model->delRecord(['user1' => $request->send_by_id, 'user2' => $login_user->id]);
                        $this->friend_model->delRecord(['user1' => $login_user->id, 'user2' => $request->send_by_id]);
                        return json_encode(['code' => 402, 'message' => '好友添加失败']);
                    }
                }
                return json_encode(['code' => 200, 'message' => 'OK']);
            } else {
                $this->news_model->updateNews([
                    'in' => ['id' => $all_request_news->toArray()]],
                    ['status' => '0']
                );
                return json_encode(['code' => 401, 'message' => '消息插入失败']);
            }

        } catch (ErrorException $e) {
            return json_encode(['code' => 400, 'message' => '参数错误']);
        }
    }
}
