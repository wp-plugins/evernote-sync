<?php
/*
Plugin Name: Evernote Sync
Plugin URI: http://www.biliyu.com/evernote-sync
Description: The evernote timing synchronization to wordpress.
Version: 1.1.0
Author: Gaowei Tang
Author URI: http://www.tanggaowei.com/
Text Domain: evernotesync
Domain Path: /languages/
*/

/*

1. 该插件同时适用于 Evernote 和 印象笔记（以下统一称为 Evernote）；
2. 添加“posts”标签的 Evernote 将自动同步至 WordPress；
3. 大约每 30 分钟同步一次；
4. 同步内容包括分类、标签、标题、内容及其包含的图片；
5. Evernote 中相同名称的标签同步为 WordPress 的分类；
6. 其它 Evernote 标签按名称全部同步为 WordPress 的标签。（除“posts”标签外）
*/
/*
Features:

1. The plugin applies to Evernote and Yinxiang.com;
2. Add "posts" tag Evernote will automatically sync to WordPress;
3. About synchronization every 30 minutes;
4. Synchronous including categories, tags, titles, content and pictures contained;
5. The same name tags of Evernote synchronization into WordPress categories;
6. Other Evernote tags are all synchronized to the WordPress label. (except for "posts")
*/

require 'src/autoload.php';

/**
 * Doesn't work if PHP version is not 5.3.0 or higher
 */
if (version_compare(phpversion(), '5.3.0', '<')) {
  return;
}

/**
 * Loader class for the EvernoteSync plugin
 */
class EvernoteSyncLoader {

  /**
   * Enables the EvernoteSync plugin with registering all required hooks.
   */
  public static function Enable() {
    $path = dirname(__FILE__);
    load_plugin_textdomain( 'evernotesync', false, dirname( plugin_basename( __FILE__ ) ) . '/localization/' );

	  // Init database（启动插件是执行）
	  register_activation_hook(__FILE__, array('EvernoteSyncLoader', 'activation'));

      // 插件停用是执行
	  register_deactivation_hook(__FILE__, array('EvernoteSyncLoader', 'deactivation'));

      // Add plugin options page
      add_action('admin_menu', array('EvernoteSyncLoader', 'AddPluginOptionsPage'));

    return true;
  }

  /*当插件启用时*/
  public static function activation() {     
            error_log('activation');
    global $wpdb;	
 
    $table_name = $wpdb->prefix . "evernote_sync_pots";
    if($wpdb->get_var("show tables like '$table_name'") != $table_name){
        $sql = "CREATE TABLE IF NOT EXISTS `".$table_name."` (
						`id` int(11) NOT NULL auto_increment,
			`guid` varchar(60) default '',
			`title` varchar(200) default '',
			`hash` varchar(60) default '',
			`address` varchar(200) default '',
			`url` varchar(200) default '',
			`created` bigint(15) default NULL,
			`updated` bigint(15) default NULL,
			`postid` int(11) default NULL,      
			UNIQUE KEY `id` (`id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
 
        dbDelta($sql);
    }
  }

  /*当插件停止时*/
  public static function deactivation() {      
    error_log('deactivation');    
    wp_clear_scheduled_hook( 'evernote_sync_cron' );
  }

  // 添加管理页面
  public static function AddPluginOptionsPage() {
	  
    //$plugin_dir = basename(dirname(__FILE__));
    //load_plugin_textdomain('evernotesync', false, "$plugin_dir/languages");

    if (function_exists('add_options_page')) {
      add_options_page(__('EvernoteSync', 'evernotesync'), __('EvernoteSync', 'evernotesync'), 'manage_options', 'evernotesync.php', array('EvernoteSyncLoader', 'ShowOptionsPage'));
    }
  }

  public static function ShowOptionsPage() {
    global $wpdb;
    $table_name = $wpdb->prefix . "evernote_sync_pots";

    // 手动同步
    if ( isset( $_POST[ 'sync' ] ) ) {
      EvernoteSyncLoader::sync();
    }
    ?>
    <script>
      jQuery(document).ready(function(){
        jQuery('#publishMode').change(function(){
          if(jQuery(this).val() == 3){
            jQuery('#timedSpan').show();
          }
          else{
            jQuery('#timedSpan').hide();
          }
        });
      });
    </script>
    <div class="wrap">
      <?php screen_icon(); ?>
      <h2><?php _e('EvernoteSync Plugin Options', 'evernotesync') ?></h2>		
      <?php
      $mode = get_option('evernotesync_publish_mode');
      if ( isset( $_POST[ 'submit' ] ) ) {
        update_option('evernotesync_platform',$_POST['evernotesync_platform']);
        update_option('evernotesync_token',$_POST['evernotesync_token']);
        $mode = $_POST['evernotesync_publish_mode'];        
        if($mode == 3){
          $time = $_POST['evernotesync_timed_time'];
          update_option('evernotesync_timed_time',$time);
          $patten = "/^(0?[0-9]|1[0-9]|2[0-3])\:(0?[0-9]|[1-5][0-9])$/";
          if (preg_match ( $patten, $time )) {
                update_option('evernotesync_publish_mode',$mode);
                ?><div class="updated"><?php _e('Save success.', 'evernotesync') ?></div><?php
          } else {
                update_option('evernotesync_publish_mode',2);
                ?><div class="error"><?php _e('Time format is error!', 'evernotesync') ?></div><?php
          }
        }
        else{
          update_option('evernotesync_publish_mode',$mode);
                ?><div class="updated"><?php _e('Save success.', 'evernotesync') ?></div><?php
        }
      }?>
      <div><br/><?php _e( 'Explain: Sync with "posts" tag notes', 'evernotesync' ) ?></div>
      <form action="" method="post">
      <table width="100%" class="form-table">
            <tr valign="top">
              <th scope="row"><label for="evernotesync_lines_to_scroll"><?php _e('Publish Mode', 'evernotesync') ?>:</label></th>
              <td>
                <select id="publishMode" name="evernotesync_publish_mode">
                  <option value="1"<?php if($mode==1) echo ' selected'; ?>><?php _e('published', 'evernotesync') ?></option>
                  <option value="2"<?php if($mode==2) echo ' selected'; ?>><?php _e('draft', 'evernotesync') ?></option>
                  <option value="3"<?php if($mode==3) echo ' selected'; ?>><?php _e('timed', 'evernotesync') ?></option>
                </select>
                <span id="timedSpan"<?php if($mode!=3) echo ' style="display:none;"'; ?>>
                <?php _e('Every Day', 'evernotesync') ?>&nbsp;<input name="evernotesync_timed_time" type="text" size="10" value="<?php echo get_option('evernotesync_timed_time')?>"/>
                (<?php _e('e.g.', 'evernotesync') ?>, 7:00)
                </span>
              </td>
            </tr>
            <tr valign="top">
              <th scope="row"><label for="evernotesync_lines_to_scroll"><?php _e('Platform', 'evernotesync') ?>:</label></th>
              <td>
                <select name="evernotesync_platform">
                  <option value="1"<?php if(get_option('evernotesync_platform')==1) echo ' selected'; ?>><?php _e('Yinxiang', 'evernotesync') ?></option>
                  <option value="2"<?php if(get_option('evernotesync_platform')==2) echo ' selected'; ?>><?php _e('Evernote', 'evernotesync') ?></option>
                </select>
              </td>
            </tr>
            <tr valign="top">
              <th scope="row"><label for="evernotesync_lines_to_scroll"><?php _e('Developer Token', 'evernotesync') ?>:</label></th>
              <td>
                <input name="evernotesync_token" type="text" size="60" value="<?php echo get_option('evernotesync_token')?>"/>
              </td>
            </tr>
      </table>
      <div>
      <br/>
      <?php _e( 'Get a developer token from: <a href="https://www.evernote.com/api/DeveloperToken.action" target="_blank">Evernote Developer Token</a> or <a href="https://app.yinxiang.com/api/DeveloperToken.action" target="_blank">Yinxiang Developer Token</a>.', 'evernotesync');?>
      <br/>
      </div>
      <p class="submit">
        <input type="submit" name="submit" class="button-primary" value="<?php _e( 'Save Options', 'evernotesync' ) ?>" />
      </p>
	
		</form>
    <form action="" method="post">
      <p class="submit">
        <input type="submit" name="sync" class="button" value="<?php _e( 'Manual Sync', 'evernotesync' ) ?>" />
      </p>	
		</form>
    <?php if(get_option('evernotesync_last') != null){?>
    <div><?php _e( 'The last synchronization time', 'evernotesync' ) ?>: <?php echo get_option('evernotesync_last')?></div>
    <div><?php _e( 'Next time', 'evernotesync' ) ?>: <?php
        date_default_timezone_set(get_option('timezone_string')); 
        echo date("Y-m-d H:i:s", wp_next_scheduled( 'evernote_sync_cron' ));
    ?></div>   
    <!--<div><?php echo get_option('evernotesync_log')?></div>-->
    <?php } ?>

    </div>

    <?php
  }


  public static function sync(){
    global $wpdb;

    $table_name = $wpdb->prefix . "evernote_sync_pots";

    // 计算定时
    $time = current_time( 'timestamp');
    $laststr = get_option('evernotesync_last');
    $mode = get_option('evernotesync_publish_mode');
    $timedstr = get_option('evernotesync_timed_time');

    // 保存最后一次同步时间
    update_option('evernotesync_last',date("Y-m-d H:i:s", $time));

    if($laststr && $mode && $timedstr){
      if($mode == 3){
        $lasttime = strtotime($laststr);
        $datestr = date("Y-m-d ", $time);
        $timedtime = strtotime($datestr . $timedstr . ':00');

        $logstr = $laststr;
        $logstr = $logstr . '<br/>';
        $logstr = $logstr . $datestr . $timedstr . ':00';
        $logstr = $logstr . '<br/>';
        $logstr = $logstr . date("Y-m-d H:i:s", $time);
        if($timedtime < $lasttime || $timedtime >= $time){
          $logstr = $logstr . 'false';
        }
        else{
          $logstr = $logstr . 'true';
        }

        update_option('evernotesync_log',$logstr);

        if($timedtime < $lasttime || $timedtime >= $time){
            return;
        }
      }
    }

    $token = get_option('evernotesync_token');

    $sandbox = false;

    $client = new \Evernote\Client($token, $sandbox);

    if(get_option('evernotesync_platform')==2){
      // Evernote 同步
      $client->getAdvancedClient()->setEvernote();
    }
    else{
      // 印象笔记同步
      $client->getAdvancedClient()->setYinxiang();
    }

    /**
     * 搜索标签“posts”的笔记
     */
    $search = new \Evernote\Model\Search('tag:posts');

    /**
     * The notebook to search in
     */
    $notebook = null;

    /**
     * 在默认范围搜索
     */
    $scope = \Evernote\Client::SEARCH_SCOPE_DEFAULT;

    /**
     * 按钮修改时间排序
     */
    $order = \Evernote\Client::SORT_ORDER_RECENTLY_UPDATED;

    /**
     * 返回的最大记录数
     */
    $maxResult = 10;

    
    // Upload location
    $now = time();
    $month = date("m", $now);
    $year = date("Y", $now);
    $upload_dir = wp_upload_dir();
    $baseurl = $upload_dir['baseurl'] . "/" . $year . "/" . $month . "/";
    $basedir = $upload_dir['basedir'] . "/" . $year . "/" . $month . "/";
 
    $results = $client->findNotesWithSearch($search, $notebook, $scope, $order, $maxResult);

    

    if( is_array($results) ){
      foreach ($results as $result) {
          $noteGuid    = $result->guid;
          $noteType    = $result->type;
          $noteTitle   = $result->title;
          $noteCreated = $result->created;
          $noteUpdated = $result->updated;

          // 判断文章是否更新
          $featureid = null; // 特征图ID
          $postid = null;
          $post = null;
          $record = null;
          $exist = false;
          $records = $wpdb->get_results("SELECT postid, updated FROM $table_name where guid='$noteGuid'");  
          if(is_array($records) && count($records) > 0){
            $exist = true;
            $record = $records[0];
            if($noteUpdated <= $record->updated){
              continue;
            }
          }

          $note = $client->getNote($noteGuid);
          $notebookGuid = $note->edamNote->notebookGuid;


          if( is_array($note->resources) ){
            // 声明数组
            $img_array = array(); 
            for($i=0; $i<count($note->resources); $i++){ 
              $resource = $note->resources[$i];
              $bin = unpack("H*" , $resource->data->bodyHash);
              $hash = $bin[1];
              $filename = $resource->attributes->fileName;
              // 使用 hash 值作为文件名称
              $filename = preg_replace('/[\s\S]*\./',$hash .'.', $filename);
              $filepath = $basedir . $filename;
              // 记录图片的URL
              $img_array[$hash] = $baseurl . $filename;
              if(!file_exists($filepath)) {
                // 上传图片
                file_put_contents($filepath, $resource->data->body,LOCK_EX);
                // 插数据库生成预览图
		        	  $wp_filetype = wp_check_filetype(basename($filepath), null );
                $wp_upload_dir = wp_upload_dir();
                $attachment = array(
                     'guid' => $wp_upload_dir['url'] . '/' . basename( $filepath ), 
                     'post_mime_type' => $wp_filetype['type'],
                     'post_title' => preg_replace( '/\.[^.]+$/', '', basename( $filepath ) ),
                     'post_content' => '',
                     'post_status' => 'inherit'
                );
                $attach_id = wp_insert_attachment( $attachment, $filepath, $postid );
                require_once( ABSPATH . 'wp-admin/includes/image.php' );
                $attach_data = wp_generate_attachment_metadata( $attach_id, $filepath );
                wp_update_attachment_metadata( $attach_id, $attach_data );
                // 设置文章的特征图
                if($featureid == null){
                  $featureid = $attach_id;
                }
              }
            }
          }

          $content = $note->content;
          $media_pattern = "/<en-media[^>]*>/ims";
          preg_match_all($media_pattern, $content, $output_array);
          $media_array = $output_array[0];
          if(is_array($media_array))    
          foreach($media_array as $media_inner)
          {	
            preg_match('/hash="([^"]*)"/',$media_inner,$matches);
            $hash = $matches[1];
            $img_url = $img_array[$hash];
            $theHTML = '<img src="' . $img_url . '"/>';
            $content = str_replace($media_inner,$theHTML,$content);
            $content = str_replace('</en-media>', '', $content);
          }
          // 删除 xml 标签
          $content = preg_replace('/<\?xml[^>]*?>/ims', '', $content);
          $content = preg_replace('/<!DOCTYPE[^>]*?>/ims', '', $content);
          $content = preg_replace('/<en-note[^>]*?>/ims', '', $content);
          $content = str_replace('</en-note>', '', $content);

          // 删除印象笔记的 <del> 标签
          $content = preg_replace('/<del[^>]*?>[\s\S]*?<\/del>/ims', '', $content);
          
          // 删除印象笔记的隐藏标签
          $content = preg_replace('/<center[^>]*?display[^>]*?>[\s\S]*?<\/center>/ims', '', $content);
          // 清除所有DIV和BR标签
          $content = preg_replace('/<[\/]*?div[^>]*?>/ims', '', $content);  
          $content = preg_replace('/<[\/]*?br[^>]*?>/ims', '', $content); 
          // 清除标签的 style 属性
          $content = preg_replace('/ style="[^"]*?"/ims', '', $content);
          $content = preg_replace('/ style=\'[^\']*?\'/ims', '', $content);
          // 清除 code 标签下的所有标签
          preg_match_all('/<code[^>]*?>([\s\S]*?)<\/code>/',$content,$mat);
          for($i=0; $i<count($mat[0]); $i++){
            $tmp = preg_replace('/<[^>]*?>/ims', '', $mat[1][$i]);
            $tmp = str_replace($mat[1][$i], $tmp, $mat[0][$i]);
            $content = str_replace($mat[0][$i], $tmp, $content);
          }
          
          // 获取文章
          if($exist){
            $postid = $record->postid;
            $post = get_post($postid);
          }

          // 获取分类ID
          $categoryIds = array();
          $tags = '';
          $tagGuids = $note->edamNote->tagGuids;
          if( is_array($tagGuids) ){
            foreach($tagGuids as $tagGuid)
            {	
              $tagName = $client->getUserNotestore()->getTag($tagGuid)->name;
              // 获取分类ID（传入分类名称）
              $cat_ID = get_cat_ID($tagName);  
              if($cat_ID > 0){
                array_push($categoryIds,$cat_ID);
              }
              else if($tagName != 'posts'){
                if(strlen($tags) > 0){
                  $tags = $tags . ',';
                }
                $tags = $tags . $tagName;
              }
            }
          }        

          // 没有找到文章，则新建 
          if(is_null($post)){        
            $publishMode = 'publish';
            // 创建 post 对象（数组）
            if(get_option('evernotesync_publish_mode')==2){
              $publishMode = 'draft';
            }
            $my_post = array(
               'post_title' => $noteTitle,
               'post_content' => $content,
               'post_status' => $publishMode,
               'post_author' => 1,
               'post_category' => $categoryIds,
               'tags_input' => array($tags)
            );

            // 写入日志到数据库$result = $wpdb->get_results("SELECT id,guid,title,created,updated FROM $table_name"); 
            $postid = wp_insert_post( $my_post );
          }
          else{
            // 创建 post 对象（数组）
            $my_post = array(
               'ID' => $postid,
               'post_title' => $noteTitle,
               'post_content' => $content,
               'post_author' => 1,
               'post_category' => $categoryIds,
               'tags_input' => array($tags)
            );

            // 写入日志到数据库$result = $wpdb->get_results("SELECT id,guid,title,created,updated FROM $table_name"); 
            wp_update_post( $my_post );
          }

          /*保存发布记录*/        
          if($exist){
            $wpdb->update(
                $table_name, // Table
                array( 'postid' => $postid, 'title' => $noteTitle, 'guid' => $noteGuid, 'created' => $noteCreated, 'updated' => $noteUpdated ), // Array of key(col) => val(value to update to)
                array( 'guid' => $noteGuid ) // Where
            );
          }
          else{
            $wpdb->insert($table_name,array( 'postid' => $postid, 'title' => $noteTitle, 'guid' => $noteGuid, 'created' => $noteCreated, 'updated' => $noteUpdated ) );            
          }

          // 设置特征图
          if($featureid != null){
            set_post_thumbnail( $postid, $featureid );
          }
      }
    }
  }
}

EvernoteSyncLoader::Enable();

add_filter('cron_schedules', 'evernote_sync_interval');
function evernote_sync_interval($schedules) {
    $schedules['minute'] = array('interval'=>1800, 'display'=>'30 minutes');
    return $schedules;
}
add_action( 'wp', 'evernote_sync_schedule' );
function evernote_sync_schedule() {
    if (!wp_next_scheduled('evernote_sync_cron')) {
        //error_log("evernote_sync_schedule if");
        wp_schedule_event(time(), 'minute', 'evernote_sync_cron');
    }
    else{
        //error_log(date("H:i:s", wp_next_scheduled( 'evernote_sync_cron' )));
    }
}
add_action('evernote_sync_cron', 'evernote_sync');
function evernote_sync() {
    //error_log("Evernote sync...");
    EvernoteSyncLoader::sync();
}