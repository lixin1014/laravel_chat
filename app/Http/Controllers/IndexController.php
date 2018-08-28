<?php

namespace App\Http\Controllers;


use App\Http\Model\FriendsModel;
use App\Http\Model\NewsModel;
use App\Http\Tools\Common;
use Illuminate\Http\Request;

class IndexController extends Controller
{
    private $friend_model;
    private $news_model;

    private $common;

    public function __construct()
    {
        $this->friend_model = new FriendsModel();
        $this->news_model = new NewsModel();

        $this->common = new Common();
    }

    public function index(Request $request)
    {
        //  查询当前用户的好友
        $login_user = $request->session()->get('userInfo');
        $my_friends = $this->friend_model->getFriends(['user1' => $login_user->id, 'friends.status' => '1']);

        return view('index', ['my_friends' => $my_friends,]);
    }
}
