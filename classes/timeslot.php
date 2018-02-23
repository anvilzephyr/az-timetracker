<?php
namespace AZTimeTracker;
/*
class/timeslot.php 
*/
   if (!class_exists('Timeslot')){
   class Timeslot extends Base{

      private static $_instance = null;
      protected static $cpt_names = ['single' => 'az-timeslot', 'plural' => 'az-timeslots', 'uc_single' => 'Timeslot', 'uc_plural' => 'Timeslots'];

      public static $tablename = 'az_timeslots';
      
      public static function get_instance() {
         if (self::$_instance == null) {
            self::$_instance = new Timeslot();
         }

         return self::$_instance;
      }
      public function admin_menu(){
         add_submenu_page(Base::$name, 'Timeslots', 'Timeslots','manage_options', 'edit.php?post_type=az-timeslot', NULL);
      }
      public function init(){  
         $this->add_cpt(self::$cpt_names);
      }
      
      function admin_init(){
         $this->add_meta_boxes();
         add_action( 'restrict_manage_posts', array($this,'list_filter' ));
         add_filter( 'pre_get_posts', array($this,'filter_list' ));
         add_filter( 'manage_az-timeslot_posts_columns', array($this,'columns_head'),10,1);
         add_action( 'manage_az-timeslot_posts_custom_column', array($this,'columns_content'), 10, 2);
         add_action( 'wp_ajax_set_time', [__CLASS__, 'set_time']);
         add_action('save_post', [$this, 'save_metadata']);
         
      }

      
      /*************
       * Admin Views
       */
      
      function add_meta_boxes(){
         add_meta_box("meta", "Meta", array($this, "meta_box"),self::$cpt_names['single'], "side", "high");
      }
      
      public function task_box($post){
         $args = [
               'numberposts'     => -1,
               'post_type'       => 'az-task',
         ];
         $tasks = get_posts($args);
         if ($tasks && !is_wp_error($tasks)){
            
            ?><select name='parent_id' id='task' required>
               <option value= ''>Select a Workspace</option><?php
            foreach ($tasks as $space){
               ?> <option value ='<?php echo $space->ID; ?>' <?php selected($post->post_parent,$space->ID); ?>><?php echo $space->post_title; ?></option><?php
            }
            ?></select><?php
         }
         else echo '<p>You do not have any tasks yet.</p>';
      }
      
      function meta_box(){
         global $post;
         ?>
            <div class='inside'>
         <?php      
         $this->task_box($post);
         $meta = get_post_meta($post->ID);
         $user_args = apply_filters('aztime_user_args',['show_option_none'=> 'Current User',]);
         $start = empty($meta['start_time'][0])?date('Y-m-d\TH:i:s'): date('Y-m-d\TH:i:s',$meta['start_time'][0]);

         $end = empty($meta['end_time'][0])?'':date('Y-m-d\TH:i:s',$meta['end_time'][0]);

         ?>
         <label for="start_time">Start</label><input type='datetime-local' value='<?php echo $start; ?>' name='start_time' />
         <label for="end_time">End</label><input type='datetime-local' value='<?php echo $end; ?>' name='end_time' />
         <?php wp_dropdown_users($user_args); ?>

         </div>
         <?php
      }
      
      
      /***************************
       * Customize Timeslot list view
       */
      function columns_head($defaults) {   
          $add = array(
            'cb'				=> $defaults['cb'],
            'title'			=> $defaults['title'],
            'task'         => 'Task',
            'start'        => 'Start',                
            'time'        => 'Time Logged',
            'action'      => 'Action',
            );
         $defaults = array_merge($add,$defaults);

         return $defaults;
     }
     
     function columns_content($column_name, $post_id) {
         switch($column_name){

            case 'task':
               $parent = wp_get_post_parent_id( $post_id );
               echo get_the_title( $parent );
               break;
            case 'time':
               $time = $this->total_time($post_id);
               if ($time)
                  echo number_format($time/3600,2);
               break;
            case 'start':
                  echo get_post_field('post_date',$post_id);
               break;
            case 'action':
               if  (get_post_status( $post_id )== 'publish') :
               if ($results = Timeslot::has_open_timeslot($post_id)){ 
                  echo "<span style='color:red'>".date('D h:i',$results->start_time); ?></span>
                  <button class='time-btn' id='end_time' data-timeslot='<?php echo $results->id; ?>'>End Time</button>
               <?php }
               else { ?>
                  <button class='time-btn' id="start_time"  data-timeslot='<?php echo $post_id; ?>'>Start Time</button> 
               <?php }
               endif;
               break;
               case 'workspace':
               $parent = wp_get_post_parent_id( $post_id );
               echo get_the_title( wp_get_post_parent_id( $parent ) );
               break;
         }
      }
      function list_filter(){

			$type = isset($_GET['post_type'])?$_GET['post_type']:'post';
			if ('az-timeslot' !== $type)
            return;

        ?>
        <select name="ws">    
         <option value="">Filter by workspace</option>
         <?php
          $current_v = isset($_GET['ws']) ? (int)$_GET['ws'] : ''; ?>
          <?php 
          $workspaces = get_posts([
                'post_type'      => 'az-workspace',
                'numberposts'    => -1,
          ]);
          foreach ($workspaces as $ws) {
             printf('<option value="%d" %s>%s</option>', $ws->ID, $ws->ID == $current_v ? ' selected' : '', $ws->post_title);
          }
           ?>
         </select>
         <?php
         $this->date_range_filter('Detail');
           
		}
     
      public function filter_list( $query ){
			global $pagenow;
			
			if ( $query->query['post_type'] == 'az-timeslot' && is_admin() && $pagenow=='edit.php' ){
            // filter by workspace
            if (isset($_GET['ws']) && !empty($_GET['ws'])  ) {
               $ws = (int)$_GET['ws'];
               $all_ws = Workspace::get_workspace_children($ws, ['az-workspace','az-task'], 'ids');
               if (is_array($all_ws)){
                  $ws = array_merge($all_ws,[$ws]);
               }
               else $ws = [$ws];
               $query->query_vars['post_parent__in'] = $ws;
            }

			}
      }
      
      /**
       * int post_id
       * @global $wpdb
       */
      
      public static function get_timeslots($post_id){
         if (empty($post_id)){
            global $post;
            $post_id = $post->ID;
         }
         return get_posts([
            'post_type'       => 'az-timeslot',
            'numberposts'     => -1,
            'post_parent__in' => [$post_id],
         ]);
              
      }
      
      /**
       * Ajax expects $_POST vars: 
       * string field for field to update
       * int task (either post_id to start time or timeslot id to stop it)
       * string msg optional note with end time action
       * @global \AZTimeTracker\$wpdb $wpdb
       */
      public static function set_time(){

         $field = filter_input(INPUT_POST,'field',FILTER_SANITIZE_STRING);
         // task can also be post id for timeslot
         $task = filter_input(INPUT_POST,'task',FILTER_SANITIZE_NUMBER_INT);
         if (empty ($field) || empty($task)){
            echo 'Error: Missing data!';
            wp_die();
         }
         $msg = filter_input(INPUT_POST,'msg',FILTER_SANITIZE_STRING);
         global $wpdb;
         switch ($field){
            case 'start_time':
               $new = self::start_time($task);
               if (is_numeric($new)){
                  echo 'Timeslot created';
               }
               else echo 'error';
               break;
            case 'end_time':
                  if ( add_post_meta($task,'end_time',current_time('timestamp'))){
                     if (!empty($msg)){
                        // Update post content for timeslot
                        wp_update_post( ['ID'=>$task,'post_content'=>$msg] );
                     }
                     echo 'Timeslot closed';
                     $parent = wp_get_post_parent_id( $task );
                     update_post_meta($parent,'last_activity',date('Y-m-d h:i'));
                  }
                  else echo 'error';
                  
            break;
            case 'delete_time':
               if (wp_delete_post($task))
                  echo 'Timeslot deleted';
               else echo 'error';
               break;
               
         }
         wp_die();
         
      }
            
      public static function start_time($post_id = null){
         if (is_null($post_id)){
            global $post_id;
         }
         
         global $wpdb;
         // just making sure the task is published if we're adding timeslots
         $wpdb->query($wpdb->prepare("UPDATE $wpdb->posts set post_status = 'publish' where ID = %d",$post_id));
         $args = [
               'post_type'    => 'az-timeslot',
               'post_parent'  => $post_id,
               'post_status'  => 'publish',
         ];
         $timeslot = wp_insert_post($args);
         update_post_meta($timeslot, 'assigned_to', get_current_user_id());
         return update_post_meta($timeslot,'start_time', current_time('timestamp'));
      }
      
      public static function total_time($post_id,$start='',$end=''){
         // check date range first
            if (isset($_GET['start']) && !empty($_GET['start'])) {
               $start = strtotime($_GET['start']);
               $end = empty($_GET['end'])?current_time('timestamp'):strtotime($_GET['end']);

            }
            else {
               $start = '';
               $end = current_time('timestamp');
            }

            return self::get_time($post_id, $start, $end);
      }
      
      public static function get_time($post_id = null, $start, $end){
         if (empty($post_id)){
            global $post;
            $post_id = $post->ID;
         }
         global $wpdb;
         $tablename = $wpdb->postmeta;
         $sql1 = $wpdb->prepare("SELECT meta_value FROM $tablename  where meta_key='end_time' and meta_value<=%d and post_id =%d ",$end, $post_id);
         $end = $wpdb->get_var($sql1);
         if ($end>=1){ // we have a timeslot
         
         $sql = $wpdb->prepare("SELECT s.meta_value FROM $tablename s  "
            . " where s.post_id =%d and s.meta_key='start_time' and s.meta_value>=%d ",$post_id,$start );
         
         $start = $wpdb->get_var( $sql );
         
            //var_dump($sql);die();
         if (is_wp_error($start)){
            var_dump($sums->last_error);die();
         }
         elseif (!empty($start)) {
            return ($end - $start)*1;
         }
         else return '';
         }
         return '';
      }
      
      /**
       * 
       * @global \AZTimeTracker\$post $post
       * @global \AZTimeTracker\$wpdb $wpdb
       * @param int $post_id task id
       * @return mixed boolean or int
       */
       public static function has_open_timeslot($post_id){
         if (empty($post_id)){
            global $post;
            $post_id = $post->ID;
         }
         global $wpdb;
         $tablename = $wpdb->postmeta;
         $sql = $wpdb->prepare("SELECT count(meta_value) FROM $tablename m join $wpdb->posts p on (p.ID=m.post_id and m.meta_key='start_time' and p.post_parent =%d) group by p.post_parent",$post_id);
         $count_start = $wpdb->get_var(
               $sql
               );
         
         if (empty($count_start))
            return false;
         
         $count_end = $wpdb->get_var(
               $wpdb->prepare("SELECT count(meta_value) FROM $tablename m join $wpdb->posts p on (p.ID=m.post_id and m.meta_key='end_time' and p.post_parent =%d) group by p.post_parent",$post_id)
               );
         
         // we have a slog open so return data
         if ($count_start>$count_end){
            return $wpdb->get_row(
               $wpdb->prepare("SELECT post_id as timeslot,meta_value as start FROM $tablename m join $wpdb->posts p on (p.ID=m.post_id and m.meta_key='start_time' and p.post_parent =%d) order by p.ID DESC limit 1",$post_id)
               );

         }
         return false;
      }
      
      /**
       * 
       * @global \AZTimeTracker\$post $post
       */
      function save_metadata(){
         global $post;
         if (!empty($_POST) && $post && isset($_POST['user']) && did_action('save_post')==1){
            $start = empty($_POST['start_time'])?current_time('timestamp'):strtotime($_POST['start_time']);
            $assigned_to = $_POST['user']==-1?get_current_user_id():(int)$_POST['user'];
            
            if ($post->post_type=='az-task' && !empty($_POST['manual_time'])){
               $data = [
                     'post_type'    => 'az-timeslot',
                     'post_parent'    => $post->ID,
                     'post_content' => sanitize_text_field($_POST['note']),
                     'post_status'  => 'publish',
               ];

               $new = wp_insert_post($data);  
               
               $end = empty($_POST['end_time'])?current_time('timestamp'):strtotime($_POST['end_time']);
               update_post_meta($new, 'start_time', $start);
               update_post_meta($new, 'end_time', $end);
               update_post_meta($new, 'assigned_to', $assigned_to);
               update_post_meta($post->ID,'last_activity',date('Y-m-d h:i'));
            }
            elseif ($post->post_type=='az-timeslot'){
               update_post_meta($post->ID, 'start_time', $start);
               $end = sanitize_text_field($_POST['end_time']);
               if (!empty($end))
                  update_post_meta($post->ID, 'end_time', strtotime($end));
               update_post_meta($post->ID, 'assigned_to', $assigned_to);
            }
            
         }

      }
      
      
      public static function install() { 
         // add caps
         parent::manage_plugin_caps('add_cap',self::$cpt_names);
      }
      

   }
}
$instance = Timeslot::get_instance();

