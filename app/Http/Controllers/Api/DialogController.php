<?php

namespace App\Http\Controllers\Api;

use App\Models\ProjectTask;
use App\Models\ProjectTaskFile;
use App\Models\User;
use App\Models\WebSocketDialog;
use App\Models\WebSocketDialogMsg;
use App\Models\WebSocketDialogMsgRead;
use App\Models\WebSocketDialogUser;
use App\Module\Base;
use Request;

/**
 * @apiDefine dialog
 *
 * 对话
 */
class DialogController extends AbstractController
{
    /**
     * 对话列表
     *
     * @apiParam {Number} [page]            当前页，默认:1
     * @apiParam {Number} [pagesize]        每页显示数量，默认:100，最大:200
     */
    public function lists()
    {
        $user = User::auth();
        //
        $list = WebSocketDialog::select(['web_socket_dialogs.*'])
            ->join('web_socket_dialog_users as u', 'web_socket_dialogs.id', '=', 'u.dialog_id')
            ->where('u.userid', $user->userid)
            ->orderByDesc('web_socket_dialogs.last_at')
            ->paginate(Base::getPaginate(200, 100));
        $list->transform(function (WebSocketDialog $item) use ($user) {
            return WebSocketDialog::formatData($item, $user->userid);
        });
        //
        return Base::retSuccess('success', $list);
    }

    /**
     * 获取单个会话信息
     *
     * @apiParam {Number} dialog_id         对话ID
     */
    public function one()
    {
        $user = User::auth();
        //
        $dialog_id = intval(Request::input('dialog_id'));
        //
        $item = WebSocketDialog::select(['web_socket_dialogs.*'])
            ->join('web_socket_dialog_users as u', 'web_socket_dialogs.id', '=', 'u.dialog_id')
            ->where('web_socket_dialogs.id', $dialog_id)
            ->where('u.userid', $user->userid)
            ->first();
        if ($item) {
            $item = WebSocketDialog::formatData($item, $user->userid);
        }
        //
        return Base::retSuccess('success', $item);
    }

    /**
     * 打开会话
     *
     * @apiParam {Number} userid         对话会员ID
     */
    public function open__user()
    {
        $user = User::auth();
        //
        $userid = intval(Request::input('userid'));
        if ($userid == $user->userid) {
            return Base::retError('不能对话自己');
        }
        //
        $dialog = WebSocketDialog::checkUserDialog($user->userid, $userid);
        if (empty($dialog)) {
            return Base::retError('打开会话失败');
        }
        $data = WebSocketDialog::formatData(WebSocketDialog::find($dialog->id), $user->userid);
        if (empty($data)) {
            return Base::retError('打开会话错误');
        }
        return Base::retSuccess('success', $data);
    }

    /**
     * 获取消息列表
     *
     * @apiParam {Number} dialog_id         对话ID
     *
     * @apiParam {Number} [page]            当前页，默认:1
     * @apiParam {Number} [pagesize]        每页显示数量，默认:50，最大:100
     */
    public function msg__lists()
    {
        $user = User::auth();
        //
        $dialog_id = intval(Request::input('dialog_id'));
        //
        $dialog = WebSocketDialog::checkDialog($dialog_id);
        //
        $list = WebSocketDialogMsg::whereDialogId($dialog_id)->orderByDesc('id')->paginate(Base::getPaginate(100, 50));
        $list->transform(function (WebSocketDialogMsg $item) use ($user) {
            $item->is_read = $item->userid === $user->userid || WebSocketDialogMsgRead::whereMsgId($item->id)->whereUserid($user->userid)->value('read_at');
            return $item;
        });
        //
        if ($dialog->type == 'group' && $dialog->group_type == 'task') {
            $user->task_dialog_id = $dialog->id;
            $user->save();
        }
        //
        $data = $list->toArray();
        if ($list->currentPage() === 1) {
            $data['dialog'] = WebSocketDialog::formatData($dialog, $user->userid);
        }
        return Base::retSuccess('success', $data);
    }

    /**
     * 未读消息
     */
    public function msg__unread()
    {
        $unread = WebSocketDialogMsgRead::whereUserid(User::userid())->whereReadAt(null)->count();
        return Base::retSuccess('success', [
            'unread' => $unread,
        ]);
    }

    /**
     * 发送消息
     *
     * @apiParam {Number} dialog_id         对话ID
     * @apiParam {String} text              消息内容
     */
    public function msg__sendtext()
    {
        $user = User::auth();
        //
        $chat_nickname = Base::settingFind('system', 'chat_nickname');
        if ($chat_nickname == 'required') {
            $nickname = User::select(['nickname as nickname_original'])->whereUserid($user->userid)->value('nickname_original');
            if (empty($nickname)) {
                return Base::retError('请设置昵称', [], -2);
            }
        }
        //
        $dialog_id = intval(Request::input('dialog_id'));
        $text = trim(Request::input('text'));
        //
        if (mb_strlen($text) < 1) {
            return Base::retError('消息内容不能为空');
        } elseif (mb_strlen($text) > 20000) {
            return Base::retError('消息内容最大不能超过20000字');
        }
        //
        WebSocketDialog::checkDialog($dialog_id);
        //
        $msg = [
            'text' => $text
        ];
        //
        return WebSocketDialogMsg::sendMsg($dialog_id, 'text', $msg, $user->userid);
    }

    /**
     * {post}文件上传
     *
     * @apiParam {Number} dialog_id         对话ID
     * @apiParam {String} [filename]        post-文件名称
     * @apiParam {String} [image64]         post-base64图片（二选一）
     * @apiParam {File} [files]             post-文件对象（二选一）
     */
    public function msg__sendfile()
    {
        $user = User::auth();
        //
        $dialog_id = Base::getPostInt('dialog_id');
        //
        $dialog = WebSocketDialog::checkDialog($dialog_id);
        //
        $path = "uploads/chat/" . $user->userid . "/";
        $image64 = Base::getPostValue('image64');
        $fileName = Base::getPostValue('filename');
        if ($image64) {
            $data = Base::image64save([
                "image64" => $image64,
                "path" => $path,
                "fileName" => $fileName,
            ]);
        } else {
            $data = Base::upload([
                "file" => Request::file('files'),
                "type" => 'file',
                "path" => $path,
                "fileName" => $fileName,
            ]);
        }
        //
        if (Base::isError($data)) {
            return Base::retError($data['msg']);
        } else {
            $fileData = $data['data'];
            $fileData['thumb'] = Base::unFillUrl($fileData['thumb']);
            $fileData['size'] *= 1024;
            //
            if ($dialog->type === 'group') {
                if ($dialog->group_type === 'task') {
                    $task = ProjectTask::whereDialogId($dialog->id)->first();
                    if ($task) {
                        $file = ProjectTaskFile::createInstance([
                            'project_id' => $task->project_id,
                            'task_id' => $task->id,
                            'name' => $fileData['name'],
                            'size' => $fileData['size'],
                            'ext' => $fileData['ext'],
                            'path' => $fileData['path'],
                            'thumb' => $fileData['thumb'],
                            'userid' => $user->userid,
                        ]);
                        $file->save();
                    }
                }
            }
            //
            $result = WebSocketDialogMsg::sendMsg($dialog_id, 'file', $fileData, $user->userid);
            if (Base::isSuccess($result)) {
                if (isset($task)) {
                    $result['data']['task_id'] = $task->id;
                }
            }
            return $result;
        }
    }

    /**
     * 获取消息阅读情况
     *
     * @apiParam {Number} msg_id            消息ID（需要是消息的发送人）
     */
    public function msg__readlist()
    {
        $user = User::auth();
        //
        $msg_id = intval(Request::input('msg_id'));
        //
        $msg = WebSocketDialogMsg::whereId($msg_id)->whereUserid($user->userid)->first();
        if (empty($msg)) {
            return Base::retError('不是发送人');
        }
        //
        $read = WebSocketDialogMsgRead::whereMsgId($msg_id)->get();
        return Base::retSuccess('success', $read ?: []);
    }
}
