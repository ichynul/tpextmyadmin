<?php
namespace tpext\myadmin\admin\controller;

use think\Controller;
use tpext\builder\common\Builder;
use tpext\myadmin\admin\model\AdminRole;
use tpext\myadmin\admin\model\AdminUser;

class Admin extends Controller
{
    protected $dataModel;
    protected $roleModel;

    protected function initialize()
    {
        $this->dataModel = new AdminUser;
        $this->roleModel = new AdminRole;
    }

    public function index()
    {
        $builder = Builder::getInstance('用户管理', '列表');

        $form = $builder->form();

        $form->text('name', '姓名', 3)->maxlength(20);
        $form->text('phone', '手机号', 3)->maxlength(20);
        $form->text('email', '邮箱', 3)->maxlength(20);
        $form->select('role_id', '角色组', 3)->options($this->getRoleList());

        $form->html('', '', 4);

        $table = $builder->table();

        $table->searchForm($form);

        $table->show('id', 'ID');
        $table->show('username', '登录帐号');
        $table->text('name', '姓名')->autoPost()->getWapper()->addStyle('max-width:80px');
        $table->show('role_name', '角色');
        $table->show('email', '电子邮箱')->default('无');
        $table->show('phone', '手机号')->default('无');
        $table->show('errors', '登录失败');
        $table->show('create_time', '添加时间')->getWapper()->addStyle('width:180px');
        $table->show('update_time', '修改时间')->getWapper()->addStyle('width:180px');

        $pagezise = 10;

        $page = input('__page__/d', 1);

        $page = $page < 1 ? 1 : $page;

        $searchData = request()->only([
            'name',
            'email',
            'phone',
            'role_id',
        ], 'post');

        $where = [];

        if (!empty($searchData['name'])) {
            $where[] = ['name', 'like', '%' . $searchData['name'] . '%'];
        }

        if (!empty($searchData['phone'])) {
            $where[] = ['phone', 'like', '%' . $searchData['phone'] . '%'];
        }

        if (!empty($searchData['email'])) {
            $where[] = ['email', 'like', '%' . $searchData['email'] . '%'];
        }

        if (!empty($searchData['role_id'])) {
            $where[] = ['role_id', 'eq', $searchData['role_id']];
        }

        $data = $this->dataModel->where($where)->order('id desc')->limit(($page - 1) * $pagezise, $pagezise)->select();

        foreach ($data as &$d) {
            $d['__h_del__'] = $d['id'] == 1;
        }

        $table->data($data);
        $table->paginator($this->dataModel->where($where)->count(), $pagezise);

        $table->getActionbar()->mapClass([
            'delete' => ['hidden' => '__h_del__'],
        ]);

        if (request()->isAjax()) {
            return $table->partial()->render(false);
        }

        return $builder->render();
    }

    public function add()
    {
        if (request()->isPost()) {
            return $this->save();
        } else {
            return $this->form('添加');
        }
    }

    public function edit($id)
    {
        if (request()->isPost()) {
            return $this->save($id);
        } else {
            $data = $this->dataModel->get($id);
            if (!$data) {
                $this->error('数据不存在');
            }

            return $this->form('编辑', $data);
        }
    }

    private function save($id = 0)
    {
        $data = request()->only([
            'name',
            'role_id',
            'avatar',
            'username',
            'password',
            'email',
            'phone',
        ], 'post');

        $result = $this->validate($data, [
            'role_id|角色组' => 'require',
            'username|登录帐号' => 'require',
            'name|姓名' => 'require',
            'email|电子邮箱' => 'email',
            'phone|手机号' => 'mobile',
        ]);

        if (true !== $result) {

            $this->error($result);
        }

        if (!empty($data['password'])) {
            $len = mb_strlen($data['password']);

            if ($len < 6 || $len > 20) {
                $this->error('密码长度6～20');
            }

            $password = $this->dataModel->passCrypt($data['password']);

            $data['password'] = $password[0];
            $data['salt'] = $password[1];
        } else {
            unset($data['password']);
        }

        if (!empty($data['phone']) && !preg_match('/^1[3-9]\d{9}$/', $data['phone'])) {
            $this->error('手机号码格式错误');
        }

        if ($id) {
            $data['update_time'] = date('Y-m-d H:i:s');
            $res = $this->dataModel->where(['id' => $id])->update($data);
        } else {
            if (!isset($data['password']) || empty($data['password'])) {
                $this->error('请输入密码');
            }
            $res = $this->dataModel->create($data);
        }

        if (!$res) {
            $this->error('保存失败');
        }

        return Builder::getInstance()->layer()->closeRefresh(1, '保存成功');

    }

    private function getRoleList()
    {
        $list = $this->roleModel->all();
        $roles = [
            '' => '请选择',
        ];

        foreach ($list as $row) {
            $roles[$row['id']] = $row['name'];
        }

        return $roles;
    }

    private function form($title, $data = [])
    {
        $isEdit = isset($data['id']);

        $builder = Builder::getInstance('用户管理', $title);

        $form = $builder->form();

        $form->hidden('id');
        $form->text('username', '登录帐号')->required()->beforSymbol('<i class="mdi mdi-account-key"></i>');
        $form->select('role_id', '角色组')->required()->options($this->getRoleList());
        $form->password('password', '密码')->required(!$isEdit)->beforSymbol('<i class="mdi mdi-lock"></i>')->help($isEdit ? '不修改则留空（6～20位）' : '添加用户，密码必填（6～20位）');
        $form->text('name', '姓名')->required()->beforSymbol('<i class="mdi mdi-rename-box"></i>');
        $form->image('avatar', '头像')->default('/assets/lightyearadmin/images/no-avatar.jpg');
        $form->text('email', '电子邮箱')->beforSymbol('<i class="mdi mdi-email-variant"></i>');
        $form->text('phone', '手机号')->beforSymbol('<i class="mdi mdi-cellphone-iphone"></i>');

        if ($isEdit) {

            $data['password'] = '';

            $form->show('create_time', '添加时间');
            $form->show('update_time', '修改时间');
        }

        $form->fill($data);

        return $builder->render();
    }

    public function autopost()
    {
        $id = input('id/d', '');
        $name = input('name', '');
        $value = input('value', '');

        if (empty($id) || empty($name)) {
            $this->error('参数有误');
        }

        $allow = ['phone', 'name', 'email'];

        if (!in_array($name, $allow)) {
            $this->error('不允许的操作');
        }

        $res = $this->dataModel->where(['id' => $id])->update([$name => $value]);

        if ($res) {
            $this->success('修改成功');
        } else {
            $this->error('修改失败');
        }
    }

    public function delete()
    {
        $ids = input('ids');

        $ids = array_filter(explode(',', $ids), 'strlen');

        if (empty($ids)) {
            $this->error('参数有误');
        }

        $res = 0;

        foreach ($ids as $id) {
            if ($id == 1) {
                continue;
            }
            if ($this->dataModel->destroy($id)) {
                $res += 1;
            }
        }

        if ($res) {
            $this->success('成功删除' . $res . '条数据');
        } else {
            $this->error('删除失败');
        }
    }
}