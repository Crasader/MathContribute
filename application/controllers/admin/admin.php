<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Admin extends CI_Controller {
	public function __construct() {
		parent::__construct();
		$id = $this->session->userdata('id');
		$admin_name = $this->session->userdata('admin_name');
		$identity = $this->session->userdata('identity');
		if (empty($id) || empty($admin_name) || empty($identity)) {
			header('location:' . site_url('admin/login'));
		}
		$this->load->model('admin_model');
	}
	/*
		后台首页
	*/
	public function index() {
		$this->load->view('admin/index.html');
	}
	public function copy() {
		$this->load->view('admin/copy.html');
	}

	public function edit_password() {
		$this->load->view('admin/edit_password.html');
	}
	public function do_edit_password() {
		$username = $this->session->userdata('admin_name');
		$old_password = $this->input->post('old_password');
		$new_password = $this->input->post('new_password');
		$password2 = $this->input->post('password2');
		if ($new_password != $password2) {
			alert_msg('两次输入密码不一致！');
		}
		if (empty($old_password) || empty($new_password)) {
			alert_msg('密码不能为空');
		}
		$status = $this->admin_model->get_admin_info($username, md5($old_password));
		if (empty($status)) {
			alert_msg('旧密码不正确');
		}

		$admin_id = $status[0]['id'];
		$status = $this->db->update('admin', array('password' => md5($new_password)), array('id' => $admin_id));
		if ($status) {
			$this->session->unset_userdata(array('id', 'admin_name'));
			$this->session->sess_destroy();
			alert_msg('密码修改成功，请重新登录', 'go', site_url('admin/login'));
			header('location:' . site_url('admin/login'));
		} else {
			alert_msg('修改失败，请重试');
		}
	}

	/*
		用户管理
	*/
	public function user_list($type, $offset = 0) {
		$view_html = 'admin/user/user_list.html';
		switch ($type) {
		case 'author':
		case 'specialist':
		case 'editorial':
			$where_arr = array('identity' => $type);
			break;

		//未认证
		case 'ac_author':
		case 'ac_specialist':
		case 'ac_editorial':
			$where_arr = array('identity' => mb_substr($type, 3), 'status' => 0);
			$view_html = 'admin/user/ac_user_list.html';
			break;
		default:
			$where_arr = array();
			break;
		}
		//分页配置
		$per_page = 10;
		$offset_uri_segment = 5;
		$total_rows = $this->db->where($where_arr)->count_all_results('user');
		$this->load->library('myclass');
		$page_url = site_url('admin/admin/user_list/' . $type);
		$data['link'] = $this->myclass->fenye($page_url, $total_rows, $offset_uri_segment, $per_page);
		$data['user'] = $this->admin_model->get_user_list($where_arr, $offset, $per_page);
		$data['view_html'] = $view_html;
		$this->load->view($view_html, $data);
	}

	/*
		查看用户信息
	 */
	public function user_info($action, $user_id) {
		if (!is_numeric($user_id)) {
			alert_msg('该用户不存在');
		}
		$user = $this->admin_model->get_user_info(array('user_id' => $user_id));

		if (empty($user)) {
			alert_msg('该用户不存在');
		}
		$data = $user[0];
		if ($action == 'ac') {
			$view_html = 'admin/user/user_info_ac.html';
		} else {
			$view_html = 'admin/user/user_info_see.html';
		}
		$this->load->view($view_html, $data);
	}

	/*
		对用户的操作
	 */
	public function user_action($action, $user_id) {
		if (!is_numeric($user_id)) {
			alert_msg('该用户不存在');
		}
		switch ($action) {
		case 'ban': //封号
			$status = $this->db->update('user', array('status' => 0), array('user_id' => $user_id));
			break;
		case 'recover': //恢复登录
			$status = $this->db->update('user', array('status' => 1), array('user_id' => $user_id));
			break;
		default:
			# code...
			break;
		}
		if ($status) {
			alert_msg('操作成功');
		} else {
			alert_msg('操作失败，请检查您的网络！');
		}
	}

	/*
		用户搜索
	 */
	public function user_search($view_type = '') {
		$msg = $this->input->post('search');
		if ($view_type == 'ac') {
			$where_arr = array('status' => 0);
			$view_html = 'admin/user/ac_user_list.html';
		} else {
			$where_arr = array();
			$view_html = 'admin/user/user_list.html';
		}
		$data['user'] = $this->admin_model->get_user_search($where_arr, $msg);
		$data['link'] = '';
		$this->load->view($view_html, $data);
	}
	/****************** 用户相关 END *******************/

	/****************** 稿件相关  BEGIN  *******************/

	//文章列表
	public function article_list($type, $offset = 0) {
		switch ($type) {
		case 'use': //录用稿件
			$where_arr = array('check_status' => 3);
			break;
		case 'wait_check': //待核稿件
			$where_arr = array('check_status <' => 3, 'check_status !=' => -1);
			break;
		case 'refuses': //被拒稿件
			$where_arr = array('check_status' => -1);
			break;
		default: //全部稿件
			$where_arr = array();
			break;
		}
		$per_page = 10;
		$offset_uri_segment = 5;
		$page_url = site_url('admin/admin/article_list/');
		$total_rows = $this->db->where($where_arr)->count_all_results('article');
		$this->load->library('myclass');
		$data['link'] = $this->myclass->fenye($page_url, $total_rows, $offset_uri_segment, $per_page);
		$data['article'] = $this->admin_model->get_article_list($where_arr, $offset);
		$this->load->view('admin/article/article_list.html', $data);
	}

	//查看文章
	public function article_info($article_id) {
		if (!is_numeric($article_id)) {
			alert_msg('该稿件不存在！');
		}

		$where_arr = array('article_id' => $article_id);
		$article = $this->admin_model->get_article_info($where_arr);
		if (empty($article)) {
			alert_msg('该稿件不存在！');
		}

		$data['article'] = $article[0];

		//获取用户信息
		$user_info = $this->admin_model->get_user_info(array('user_id' => $data['article']['user_id']));
		empty($user_info) ? alert_msg('该稿件不存在！') : $data['user'] = $user_info[0];

		//获取评论信息
		$data['suggest'] = $this->admin_model->get_suggest_info(array('article_id' => $article_id));

		$this->load->view('admin/article/article_info.html', $data);
	}

	//下载稿件
	public function download($article_id) {
		if (!is_numeric($article_id)) {
			alert_msg('该稿件不存在！');
		}
		$article = $this->admin_model->get_article_info(array('article_id' => $article_id));
		empty($article) ? alert_msg('该稿件不存在') : '';
		$this->load->helper('download');
		$data = file_get_contents($this->config->item('MYPATH') . $article[0]['attachment_url']);

		//匹配文件后缀名，重命名下载
		preg_match('/.\w+$/', $article[0]['attachment_url'], $matches);
		force_download($article[0]['title'] . $matches[0], $data);
	}

	/****************** 稿件相关  END  *******************/

	/****************** 前台管理  Begin  *******************/

	//内容展示 包括：季刊介绍 编委会 作者中心 期刊订阅
	public function content($action, $content_id) {
		if (!is_numeric($content_id)) {
			alert_msg('该栏目不存在');
		}
		$content = $this->admin_model->get_content_info(array('id' => $content_id));
		if ($action == 'edit') {
			$content = $_POST['content'];
			if (empty($content)) {
				$status = $this->db->insert('content', array('id' => $content_id, 'content' => $_POST['content']));
			} else {
				$status = $this->db->update('content', array('content' => $_POST['content']), array('id' => $content_id));
			}
			if ($status) {
				alert_msg('修改成功！');
			} else {
				alert_msg('修改失败，请检查您的网络！');
			}
		} else {
			$data['content'] = $content[0];
			$this->load->view('admin/home/content.html', $data);
		}
	}

	//作者指南
	public function author_center($action, $id = 0, $col_id = 14) {
		if (!is_numeric($id)) {
			alert_msg('您访问的内容不存在！');
		}
		//添加页面 或者是修改页面
		if ($action == 'see') {

			if ($id == 0) {
				//添加页面
				$data['content'] = array('id' => 0, 'title' => '', 'content' => '');
			} else {
				$content = $this->admin_model->get_content_info(array('id' => $id));
				if (empty($content)) {
					alert_msg('您访问的内容不存在！');
				}
				$data['content'] = $content[0];
			}
			$data['col_id'] = $col_id;
			$this->load->view('admin/home/author_center.html', $data);
		} else if ($action == 'do') {
			//添加和编辑操作
			$data = array(
				'title' => $this->input->post('title'),
				'content' => $this->input->post('content', false),
			);
			if ($id == 0) {
				//添加操作
				$data['time'] = time();
				$data['col_id'] = $col_id; //作者指南col_id=>14
				$status = $this->db->insert('content', $data);
				$message = '添加';
			} else {
				$status = $this->db->update('content', $data, array('id' => $id));
				$message = '操作';
			}
			$status ? alert_msg($message . '成功！') : alert_msg($message . '失败，请检查您的网络！');
		} else if ($action == 'delete') {
			//删除链接
			$status = $this->db->delete('content', array('id' => $id));
			$status ? alert_msg('删除成功！') : alert_msg('删除失败，请检查你的网络！');
		} elseif ($action == 'top') {
			//置顶操作
			$content = $this->admin_model->get_content_info(array('id' => $id));
			if (empty($content)) {
				alert_msg('该条信息已被删除！');
			}
			$is_top = $content[0]['is_top'] == 0 ? 1 : 0;
			$status = $this->db->update('content', array('is_top' => $is_top), array('id' => $id));
			$status ? alert_msg('操作成功！') : alert_msg('操作失败，请检查您的网络！');
		} else {
			$offset = $id;
			$where_arr = array('col_id' => $col_id);
			$page_url = site_url('admin/admin/author_center/list');
			$total_rows = $this->db->where($where_arr)->count_all_results('content');
			$offset_uri_segment = 5;
			$per_page = 10;
			$this->load->library('myclass');
			$data['link'] = $this->myclass->fenye($page_url, $total_rows, $offset_uri_segment, $per_page);
			$data['content'] = $this->admin_model->get_content_list($where_arr, $offset, $per_page);
			$data['col_id'] = $col_id;
			$this->load->view('admin/home/author_center_list.html', $data);
		}
	}

	//在线期刊  未完成
	public function periodical($type, $offset = 0) {
		$season = get_season_time();
		switch ($type) {
		case 'now': //本期目录
			$season = get_season_time();
			$where_arr = array('check_status' => 3);
			break;
		case 'last': //下期目录
			$season = get_season_time('last');
			$where_arr = array('use_time >=' => $season['start'], 'use_time <=' => $season['end']);
			break;
		case 'pass': //过刊浏览

			break;
		default:
			# code...
			break;
		}
		$per_page = 10;
		$offset_uri_segment = 5;
		$total_rows = $this->db->where($where_arr)->count_all_results('article');
		$page_url = site_url('admin/admin/periodical/' . $type);

		$this->load->library('myclass');
		$data['link'] = $this->myclass->fenye($page_url, $total_rows, $offset_uri_segment, $per_page);
		$status = $this->admin_model->get_article_list($where_arr, $offset, ', use_time');
		$this->load->view('');

	}

	//下载中心
	public function dl_center($action, $id = 0) {
		if (!is_numeric($id)) {
			alert_msg('该信息不存在！');
		}
		if ($action == 'list') {
			//下载文件列表
			$where_arr = array('col_id' => 7);
			$offset = $id;
			$page_url = site_url('admin/admin/dl_center/list/');
			$offset_uri_segment = 5;
			$per_page = 10;
			$total_rows = $this->db->where($where_arr)->count_all_results('content');
			$this->load->library('myclass');
			$data['link'] = $this->myclass->fenye($page_url, $total_rows, $offset_uri_segment, $per_page);
			$data['content'] = $this->admin_model->get_content_list($where_arr, $offset, $per_page);
			//echo $this->db->last_query();
			//print_r($data['content']);
			$this->load->view('admin/home/dl_list.html', $data);
		} else if ($action == 'edit') {
			//修改下载文件
			$data = array(
				'title' => $this->input->post('title'),
				'content' => $this->input->post('content', false),
				'time' => time(),

			);
			$status = $this->db->update('content', $data, array('id' => $id));
			$status ? alert_msg('修改成功！') : alert_msg('修改失败，请检查您的网络！');
		} else if ($action == 'add') {
			//添加现在文件
			$data = array(
				'title' => $this->input->post('title'),
				'content' => $_POST['content'],
				'time' => time(),
				'col_id' => 7,
			);
			$status = $this->db->insert('content', $data);
			if ($status) {
				alert_msg('添加成功！');
			} else {
				alert_msg('添加失败，请检查您的网络！');
			}
		} else if ($action == 'delete') {
			$status = $this->db->delete('content', array('id' => $id));
			if ($status) {
				alert_msg('删除成功！');
			} else {
				alert_msg('删除失败，请重试！');
			}
		} else if ($action == 'top') {
			$content = $this->admin_model->get_content_info(array('id' => $id));
			if (empty($content)) {
				alert_msg('该信息不存在！');
			}
			$is_top = $content[0]['is_top'] == 1 ? 0 : 1;
			$status = $this->db->update('content', array('is_top' => $is_top), array('id' => $id));
			if ($status) {
				alert_msg('操作成功！');
			} else {
				alert_msg('操作失败，请检查您的网络！');
			}
		} else {
			//查看下载文件
			$data['content'] = array('content' => '', 'title' => '', 'id' => '');
			if ($id != 0) {
				$where_arr = array('id' => $id);
				$status = $this->admin_model->get_content_info($where_arr);
				if (empty($status)) {
					alert_msg('该条信息已被删除！');
				}
				$data['content'] = $status[0];
				$data['action'] = 'edit';
			}
			$data['action'] = 'add';
			$this->load->view('admin/home/dl_center.html', $data);
		}
	}

	//友情链接
	public function link($action, $id = 0, $col_id = 24) {
		if (!is_numeric($id)) {
			alert_msg('该信息不存在！');
		}
		if ($action == 'do') {
			//执行操作
			$data = array(
				'content' => $this->input->post('content'),
				'title' => $this->input->post('title'),
			);

			if ($id == 0) {
				//执行添加操作 id为0表示数据库没有原来的链接信息 需要添加
				$data['time'] = time();
				$data['col_id'] = $col_id;
				$status = $this->db->insert('content', $data);
				$message = '添加';
			} else {
				//执行修改操作
				$status = $this->db->update('content', $data, array('id' => $id));
				$message = '修改';
			}
			$status ? alert_msg($message . '成功！') : alert_msg($message . '失败，请检查您的网络！');
		} else if ($action == 'delete') {
			//删除链接
			$status = $this->db->delete('content', array('id' => $id));
			$status ? alert_msg('删除成功！') : alert_msg('删除失败，请检查你的网络！');
		} elseif ($action == 'top') {
			//置顶操作
			$content = $this->admin_model->get_content_info(array('id' => $id));
			if (empty($content)) {
				alert_msg('该条信息已被删除！');
			}
			$is_top = $content[0]['is_top'] == 0 ? 1 : 0;
			$status = $this->db->update('content', array('is_top' => $is_top), array('id' => $id));
			$status ? alert_msg('操作成功！') : alert_msg('操作失败，请检查您的网络！');
		} else {
			$offset = $id;
			$where_arr = array('col_id' => $col_id);
			$page_url = site_url('admin/admin/link/list');
			$total_rows = $this->db->where($where_arr)->count_all_results('content');
			$offset_uri_segment = 5;
			$per_page = 10;
			$this->load->library('myclass');
			$data['link'] = $this->myclass->fenye($page_url, $total_rows, $offset_uri_segment, $per_page);
			$data['href'] = $this->admin_model->get_content_list($where_arr, $offset, $per_page);
			$data['col_id'] = $col_id;
			$this->load->view('admin/home/link.html', $data);
		}
	}
	/****************** 前台管理  End  *******************/

	//分页函数
	private function _fenye($page_url, $total_rows, $offset_uri_segment, $per_page = 10) {
		//载入分页类
		$this->load->library('pagination');
		//配置分页信息
		$config['base_url'] = $page_url;
		$config['total_rows'] = $total_rows;
		$config['per_page'] = $per_page;
		$config['uri_segment'] = $offset_uri_segment;
		$config['first_link'] = '首页';
		$config['last_link'] = '尾页';
		$config['next_link'] = '下一页';
		$config['prev_link'] = '上一页';
		//载入配置信息
		$this->pagination->initialize($config);
		$link = $this->pagination->create_links();
		return $link;
	}
}
