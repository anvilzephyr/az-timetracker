<?php
namespace AZTimeTracker;
/*
class/task.php 
*/
if (!class_exists('AZTimeTracker\\Task')){
      
   class Task extends Base{

      private static $_instance = null;
      protected static $cpt_names = ['single' => 'az-task', 'plural' => 'az-tasks', 'uc_single' => 'Task', 'uc_plural' => 'Tasks'];
      protected $billing_names = ['single'=>'billing','uc_single'=>'Billing','uc_plural'=>'Billing'];
      public $last_date = '';
      
      public static function get_instance() {
         if (self::$_instance == null) {
            self::$_instance = new Task();
         }

         return self::$_instance;
      }
      public function admin_menu(){
         add_submenu_page(Base::$name, __('Tasks', self::$name) ,__('Tasks', self::$name),'manage_options', 'edit.php?post_type=az-task', NULL);
      }
      public function init(){  
         $this->add_cpt(self::$cpt_names);
         $this->add_taxonomy($this->billing_names, __CLASS__);
      }
      
      function admin_init(){
         $this->add_meta_boxes();
         add_action( 'restrict_manage_posts', array($this,'list_filter' ));
         add_filter( 'pre_get_posts', array($this,'filter_list' ));
         add_filter( 'manage_az-task_posts_columns', array($this,'columns_head'),10,1);
         add_action( 'manage_az-task_posts_custom_column', array($this,'columns_content'), 10, 2);
         add_filter( 'default_title', array($this,'remove_title_filter'), 100, 2 ); 
         
      }

      
      /*************
       * Admin view metaboxes
       */
      
      function add_meta_boxes(){
         add_meta_box("workspace", "Workspace", array($this, "workspace_box"),self::$cpt_names['single'], "side", "high");
         add_meta_box("timeslots", "Timeslots" , [$this, "timeslots_box"],self::$cpt_names['single'], "normal", "high");  
         add_meta_box("manual", "Manual Time" , [$this, "manual_box"],self::$cpt_names['single'], "normal", "high"); 
      }
      
      public function timeslots_box(){
         global $post;
         ?>
         <div id='timeslot-actions'>
            <button class='time-btn pull-right red_button' data-action="start_time"  data-task='<?php echo $post->ID; ?>'><?php echo __('Start Time', self::$name); ?></button> 
         </div>
         <?php
         $slots = Timeslot::get_timeslots($post->ID);

         if ($slots && !is_wp_error($slots)){
            $rows = [];
            $total_total = 0;
            
            foreach ($slots as $slot){
               $meta = get_post_meta($slot->ID);
               $link = site_url()."/wp-admin/post.php?post=$slot->ID&action=edit";
               $created = date('F j, Y',strtotime($slot->post_date));
               $user = get_user_by('id',$meta['assigned_to'][0]);
               $start = $meta['start_time'][0];
               $start_display = date('h:i a',$meta['start_time'][0]);
               $end_time = isset($meta['end_time'][0])?$meta['end_time'][0]:'';
               $end = !empty($end_time)?date('h:i a',$end_time):"<button class='time-btn' data-action='end_time' data-task='$slot->ID'>End Time</button>";
               $total =  !empty($end_time)?number_format(($end_time-$start)/3600,2):0;
               $hours = floor($total);   
               $per = $total - $hours; 
               $minutes = round($per * 60,0);
               $total_total += $total;
               $rows[] = "<tr><td><a href='$link' target='blank'>$created</a></td><td>$slot->post_content</td><td>$user->display_name</td><td>$start_display</td><td>$end</td><td class='right'>$total</td><td class='right'>$hours : $minutes</td><td><i data-action='delete_time' class='fa fa-close time-btn'  data-task='$slot->ID'></i></td></tr>";
            }
            $this->print_timeslots_table($rows);  
         }
         else { 
            echo "<p>".__('No timeslots for this task', self::$name)."</p>";
         }
      }
      
      protected function print_timeslots_table($rows){
         ?><table class='widefat striped'>
         <tr><th><?php echo __('Created', self::$name); ?></th><th><?php echo __('Note', self::$name); ?></th><th><?php echo __('Assigned To', self::$name); ?></th><th><?php echo __('Start', self::$name); ?></th><th><?php echo __('End', self::$name); ?></th><th><?php echo __('Total', self::$name); ?></th><th><?php echo __('H:M', self::$name); ?></th><th><?php echo __('Delete', self::$name); ?></th></tr><?php
         echo implode('',$rows);
         echo "<tr><td colspan='5'>".__('Total', self::$name)."</td><td class='right'>$total_total</td><td colspan='2'>&nbsp;</td></tr>";
         ?>     
         </table>
<?php
      }
      
      function manual_box(){
         $user_args = apply_filters('aztime_user_args',['show_option_none'=> 'Current User',]);
         ?>
            <div class='inside'> 
               <input type='datetime-local' value='' name='start_time' />
               <input type='datetime-local' value='' name='end_time' />
               <?php wp_dropdown_users($user_args); ?>
               <br><label for='note'><?php echo __('Note', self::$name); ?></label><input type='text' class='widefat' name='note' />
               <input type='hidden' value='0' name='manual_time' id='manual_time' />
               <button id='add_time' ><?php echo __('Add', self::$name); ?></button>
            </div>
         <?php
      }
      
      public function workspace_box(){
         global $post;             
         echo Workspace::dropdown(NULL, $post->post_parent, 'parent_id');
         
      }
      
      
      /***************************
       * Customize Task list view
       */
      function columns_head($defaults) {   
         
          $add = array(
            'cb'				=> $defaults['cb'],
            'title'			=> $defaults['title'],
            'workspace'    => __('Workspace', self::$name),
            'task'         => __('Task', self::$name),
            'time'         => __('Time Logged', self::$name),
            'last_date'    => __('Last Activity', self::$name),
            'action'       => __('Action', self::$name),
            );
          
         $defaults = array_merge($add,$defaults);

         return $defaults;
     }
     
     function columns_content($column_name, $post_id) {
        
         switch($column_name){
            case 'workspace':
               $parent = wp_get_post_parent_id( $post_id );
               echo get_the_title( $parent );
               break;
            case 'task':
               echo get_the_title( $post_id );
               break;
            case 'time':
               $time = $this->total_time($post_id);
               if ($time)
                  echo "<span >".sprintf("%02d%s%02d%s", floor($time/3600), 'h', ($time/60)%60, 'm')." ( </span><span class='time-span'>".number_format($time/3600,2)." )</span>";
               break;
            case 'last_date':
               echo apply_filters('aztime_last_activity',date('m/d',$this->last_date),$this->last_date);
               break;
            case 'action':
               if  (get_post_status( $post_id )== 'publish') :
               if ($results = Timeslot::has_open_timeslot($post_id)){ 
                  echo "<span style='color:red'>".date('D h:i',$results->start); ?></span><br>
                  <button class='time-btn' data-action='end_time' data-task='<?php echo $results->timeslot; ?>'><?php echo __('End Time', self::$name); ?></button>
               <?php }
               else { ?>
                  <button class='time-btn' data-action="start_time"  data-task='<?php echo $post_id; ?>'><?php echo __('Start Time', self::$name); ?></button> 
               <?php }
               endif;
               break;
         }
      }
      
      function remove_title_filter($title, $post) {
         
         if (get_post_type($post) !== 'az-task'){
            return;
         }
         
         return $post->post_title;
         
      }
      function list_filter(){

			$type = isset($_GET['post_type'])?$_GET['post_type']:'post';
			if ('az-task' !== $type)
            return;
         $value = isset($_GET['ws']) ? (int)$_GET['ws'] : ''; 
         echo Workspace::dropdown(null, $value, 'ws', 'title','ASC', '');
         $this->date_range_filter();
         $this->hide_empty_filter();
         $title = empty($value)?'':get_the_title($value);
         $this->print_button($title);
           
		}
     
      public function filter_list( $query ){
         
			global $pagenow;
			
			if ( $query->query['post_type'] == 'az-task' && is_admin() && $pagenow=='edit.php' ){
            // filter by workspace
            if (isset($_GET['ws']) && !empty($_GET['ws'])  ) {

               $children = Workspace::get_workspace_children((int)$_GET['ws'], ['az-workspace'], 'ids');
               $children[] = (int)$_GET['ws'];
               $query->query_vars['post_parent__in'] = $children;

            }
            if (isset($_GET['hide_empty'])){
               add_filter('posts_where', [$this, 'posts_where'] );
            }

			}

         $query->query_vars['orderby'] = 'date';
         $query->query_vars['order'] = 'ASC';
         
      }
      
      protected function hide_empty_filter(){
         ?>
         <input type='checkbox' name='hide_empty' value='1' /><label for='checkbox'><?php echo __('Hide Empty', self::$name); ?></label>
         <?php
      }
      
      /**
       * Hides results with no time logged
       * @global object $wpdb
       * @param string $where
       * @return string altered where clause
       */
      public function posts_where($where){
         global $wpdb;
         list($start, $end) = $this->get_date_range();
         $where .= $wpdb->prepare( " AND ID IN (SELECT post_parent FROM $wpdb->posts where post_type='az-timeslot' and ID in (SELECT post_id FROM $wpdb->postmeta WHERE meta_key='start_time' AND meta_value BETWEEN %d AND %d) )", $start, $end);

         return $where;
      }
      
      /**
       * Used in list view
       * @param int $post_id
       * @return float
       */
      protected function total_time($post_id){
         
         // check date range first
         list($start, $end) = $this->get_date_range();
         return $this->get_time($post_id , $start, $end);
         
      }
      
            
      public function get_time($post_id = null, $start, $end){
         
         if (empty($post_id)){
            global $post;
            $post_id = $post->ID;
         }
         
         global $wpdb;
         $tablename = $wpdb->postmeta;

         $sql1 = $wpdb->prepare("SELECT count(meta_value) FROM $tablename m join $wpdb->posts p on (p.ID=m.post_id AND p.post_status='publish') where meta_key='end_time' and meta_value<=%d and p.post_parent =%d group by p.post_parent",$end, $post_id);

         $count_end = $wpdb->get_var($sql1);
         if ($count_end>=1){ // we have a timeslot
            $sql = $wpdb->prepare("SELECT SUM(s.meta_value)as sumstart, SUM(e.meta_value)as sumend,max(e.meta_value) as last_date FROM $wpdb->posts p "
                        . "join $tablename s on (p.ID=s.post_id and s.meta_key='start_time' and s.meta_value>=%d) "
                        . "right join $tablename e on (p.ID=e.post_id and e.meta_key='end_time' and e.meta_value<=%d)"
                        . " where p.post_parent =%d AND p.post_status = 'publish' group by p.post_parent  ",$start, $end, $post_id);
            $sums = $wpdb->get_row( $sql );
            //var_dump($sql);die();
            if (is_wp_error($sums)){
               if (WP_DEBUG_LOG){
                  error_log(__METHOD__.' : '.$sums->last_error);
               }
            }
            elseif (!empty($sums)) {
               $this->last_date = $sums->last_date;
               return ($sums->sumend - $sums->sumstart)*1;
            }
            else return 0;
            
         }
         
      }
      
      public static function install() { 
         // add caps
         parent::manage_plugin_caps('add_cap',self::$cpt_names);
      }
      

   }
}
$instance = Task::get_instance();

