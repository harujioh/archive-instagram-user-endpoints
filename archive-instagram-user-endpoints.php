<?php
/*
Plugin Name: Archive Instagram User Endpoints
Description: Instagramの投稿をユーザ別に取得してDBに格納するプラグイン
Author: harujioh
Version: 1.0
*/

define('INSTAGRAM_USER_ENDPOINTS_API_URI'			, 'https://api.instagram.com/v1/users/%s/media/recent');
define('INSTAGRAM_USER_ENDPOINTS_API_COUNT'			, 10);
define('INSTAGRAM_USER_ENDPOINTS_API_MAX_PAGENATION', 3);
 
class ArchiveInstagramUserEndpoints {
	var $menuName;
	var $pageTitle;
	var $pageName;
	var $version;

	var $tableName;
	var $versionName;
	var $optName;
	var $cronName;

	public function __construct(){
		global $wpdb;
		$this->menuName		= 'Instagram by User';
		$this->pageTitle	= '"Archive Instagram User Endpoints" Settings';
		$this->pageName		= 'instagram-by-user';
		$this->version 		= '1.0';

		$name = 'archive_instagram_user_endpoints';
		$this->tableName	= $wpdb->prefix . $name;
		$this->versionName	= 'version_' . $name;
		$this->optName		= 'opt_' . $name;
		$this->cronName		= 'cron_' . $name;

		// プラグインを有効/無効にした際に、フックする
		register_activation_hook(__FILE__, array($this, 'activate'));
		register_deactivation_hook(__FILE__, array($this, 'deactivate'));

		// メニュー追加
		add_action('admin_menu', array($this, 'add_pages'));
		add_filter('plugin_action_links', array($this, 'add_plugin_settings_link'), 10, 2);

		// ajax
		add_action('wp_ajax_'. $this->pageName, array($this, 'myplugin_response'));
		add_action('wp_ajax_nopriv_'. $this->pageName, array($this, 'myplugin_response'));

		// css/js追加
		add_action('admin_print_styles', array($this, 'register_admin_styles'));
		add_action('admin_enqueue_scripts', array($this, 'register_admin_scripts'));

		// cron登録
		add_action($this->cronName, array($this, 'cron'));
		if (!wp_next_scheduled($this->cronName)){
			wp_schedule_event(time(), 'hourly', $this->cronName);
		}
	}

	// プラグインを有効化したときに呼ばれる
	function activate() {

		//現在のDBバージョン取得
		$installedVer = get_option($this->versionName);

		// DBバージョンが違ったら作成
		if($installedVer != $this->version){
			$sql = "CREATE TABLE `" . $this->tableName . "` (
						`ai_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
						`instagram_id` varchar(128) DEFAULT NULL,
						`instagram_link` varchar(128) DEFAULT NULL,
						`instagram_username` varchar(64) DEFAULT NULL,
						`instagram_profile_picture` varchar(256) DEFAULT NULL,
						`instagram_text` varchar(512) DEFAULT NULL,
						`instagram_image` varchar(256) DEFAULT NULL,
						`instagram_created_time` int(11) NOT NULL DEFAULT '0',
						`instagram_disabled` tinyint(11) NOT NULL DEFAULT '0',
						PRIMARY KEY (`ai_id`)
					) CHARSET=utf8";
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			dbDelta($sql);

			//オプションにDBバージョン保存
			update_option($this->versionName, $this->version);
		}
	}

	// プラグインを無効化したときに呼ばれる
	function deactivate() {

		// cron停止
		if (wp_next_scheduled($this->cronName)){
			wp_clear_scheduled_hook($this->cronName);
		}
	}

	// css追加
	function register_admin_styles(){
		wp_enqueue_style($this->pageName .'-admin-styles', plugins_url('style.css', __FILE__), array(), $this->version);
	}

	// js
	function register_admin_scripts(){
		wp_enqueue_script($this->pageName .'-admin-scripts', plugins_url('script.js', __FILE__), array(), $this->version);
	}

	// オプションメニューを追加
	function add_pages() {
		add_options_page($this->pageTitle, $this->menuName, 'manage_options', $this->pageName, array($this, 'option_page'));
	}

	// オプションメニューを追加
	function add_plugin_settings_link( $links, $file ){
		$this_plugin = plugin_basename(__FILE__);

		if(is_plugin_active($this_plugin) && $file == $this_plugin){
			$links[] = '<a href="' . admin_url('options-general.php?page=' . $this->pageName) . '">Settings</a>';
		}

		return $links;
	}

	// オプションメニュー
	function option_page() {
		if(isset($_POST['options'])){
			check_admin_referer('option_hash');
			$opt = get_option($this->optName);
			$opt = is_array($opt) ? $opt : array();
			update_option($this->optName, array_merge($opt, $_POST['options']));
?>
	<div class="updated fade">
		<p><strong><?php _e('Options saved.'); ?></strong></p>
	</div>
<?php
		}
?>
	<div class="wrap">
		<h1><?=$this->pageTitle?></h1>
		<form method="post">
<?php
			wp_nonce_field('option_hash');
			$opt = get_option($this->optName);

			$clientId = isset($opt['client_id']) ? $opt['client_id']: null;
			$user = isset($opt['user']) ? $opt['user']: null;
?>
			<table>
				<tr>
					<th>API client_id&nbsp;:</th>
					<td><input type="text" class="regular-text" name="options[client_id]" value="<?=$clientId?>" placeholder="API client_id"></td>
				</tr>
				<tr>
					<th>user&nbsp;:</th>
					<td><input type="text" class="regular-text" name="options[user]" value="<?=$user?>" placeholder="user"></td>
				</tr>
				<tr>
					<th colspan="2"><input type="submit" class="button-primary" value="save"></td>
				</tr>
			</table>
		</form>
	</div>

<?
		$page = is_numeric($tmp = $_GET['paging']) && $tmp > 0 ? (int)$tmp : 1;
		$limit = 10;

		global $wpdb;
		$maxPage = ceil($wpdb->get_var("SELECT COUNT(*) FROM `". $this->tableName ."`") / $limit);
		$tmpPaging = array($page);
		for($i = 0; $i < 2; $i++){
			$tmpPaging[] = $i + 1;
			$tmpPaging[] = $maxPage - $i;
		}
		for($i = 0; $i < 2; $i++){
			$tmpPaging[] = $page - $i - 1;
			$tmpPaging[] = $page + $i + 1;
		}
		sort($tmpPaging);
		$paging = array();
		foreach(array_unique($tmpPaging) as $p){
			if(1 <= $p && $p <= $maxPage){
				$paging[] = $p;
			}
		}
?>
	<div id="archive-instagram" class="wrap" data-api-url="<?=admin_url('admin-ajax.php?action='. $this->pageName)?>">
		<ul class="paging">
<?$last = 0; foreach($paging as $p):?>
<?if($last + 1 < $p):?>
			<li>...</li>
<?endif?>
			<li class="<?if($p == $page):?>now<?endif?>"><a href="<?=admin_url('options-general.php?page='. $this->pageName .'&paging='. $p)?>"><?=$p?></a></li>
<?$last = $p; endforeach?>
		</ul>
		<ul class="photo">
<?foreach($this->get_data($page, $limit) as $data):?>
			<li class="<?if($data->instagram_disabled):?>disabled<?endif?>" data-id="<?=$data->ai_id?>"><img src="<?=$data->instagram_image?>"></li>
<?endforeach?>
		</ul>
	</div>

<?php
	}

	// ajax
	function myplugin_response(){
		try{
			if(!isset($_REQUEST['id'])){
				throw new Exception('No param [id]');
			}else if(!isset($_REQUEST['method'])){
				throw new Exception('No param [method]');
			}else if(!in_array($_REQUEST['method'], array('enabled', 'disabled'))){
				throw new Exception('[method] is only enabled/disabled');
			}

			global $wpdb;
			$result = $wpdb->update(
				$this->tableName,
				array(
					'instagram_disabled' => $_REQUEST['method'] === 'enabled' ? 0 : 1
				), array(
					'ai_id' => $_REQUEST['id']
				)
			);
			wp_send_json(array('success' => $result > 0));
		}catch(Exception $e){
			wp_send_json(array(
				'success' => false,
				'message' => $e->getMessage()
			));
		}
	}

	// cron
	function cron() {
		$insertDatas = $this->get_instagram(get_option($this->optName));

		if(count($insertDatas) > 0){
			global $wpdb;
			foreach($insertDatas as $data){
				$wpdb->insert($this->tableName, $data);
			}
		}
	}

	// instagramからデータを取得
	private function get_instagram($param, $url = null, $n = 0){
		// unset($param['min_id']);
		
		if(!isset($param['user'])){
			return array();
		}

		$data = http_build_query(array_intersect_key(
			array_merge($param, array('count' => INSTAGRAM_USER_ENDPOINTS_API_COUNT)),
			array_flip(array('count', 'client_id', 'min_id'))
		), '', '&');

		$response = wp_remote_get(isset($url) ? $url : sprintf(INSTAGRAM_USER_ENDPOINTS_API_URI, $param['user']) . '?' . $data);
		$json = json_decode($response['body']);
		
		$minTagId = -1;
		$insertDatas = array();
		if(isset($json->data)){
			foreach($json->data as $data){
				$insertDatas[] = array(
					'ai_id' => null,
					'instagram_id' => (int)($data->caption->id),
					'instagram_link' => $data->link,
					'instagram_username' => $data->user->username,
					'instagram_profile_picture' => $data->user->profile_picture,
					'instagram_text' => substr(preg_replace_callback('/[^\x{0}-\x{FFFF}]/u', function($m) {
						return sprintf("&#x%X;", ((ord($m[0][0]) & 0x7) << 18) | ((ord($m[0][1]) & 0x3F) << 12) | ((ord($m[0][2]) & 0x3F) << 6) | (ord($m[0][3]) & 0x3F));
					}, $data->caption->text), 0, 511),
					'instagram_image' => $data->images->standard_resolution->url,
					'instagram_created_time' => $data->caption->created_time
				);
				if($minTagId < 0){
					$minTagId = (int)($data->caption->id);
				}
			}
		}

		if($n == 0 && $minTagId >= 0){
			update_option($this->optName, array_merge(get_option($this->optName), array('min_id' => $minTagId)));
		}
		if(isset($json->pagination->next_url) && $n + 1 < INSTAGRAM_USER_ENDPOINTS_API_MAX_PAGENATION){
			return array_merge($insertDatas, $this->get_instagram($param, $json->pagination->next_url, $n + 1));
		}
		return $insertDatas;
	}

	// dbからデータを取得
	private function get_data($page = 1, $limit = 10){
		$offset = $limit * ($page - 1);

		global $wpdb;
		$sql = $wpdb->prepare("SELECT * FROM `". $this->tableName ."` ORDER BY `instagram_created_time` desc LIMIT ${offset},${limit}", null);
		$datas = array();
		foreach($wpdb->get_results($sql) as $data){
			$data->instagram_text = nl2br($data->instagram_text);
			$data->instagram_created_time = date('M d Y', $data->instagram_created_time);

			$datas[] = $data;
		}
		return $datas;
	}
}

$archiveInstagramUserEndpoints = new ArchiveInstagramUserEndpoints;
