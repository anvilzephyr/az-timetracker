<?php
namespace AZTimeTracker;
/*
class/workspace.php 
*/
   if (!class_exists('AZTimeTracker\\Workspace')){
   class Workspace extends Base {

      private static $_instance = null;
      public static $cpt_names = [
            'single' => 'az-workspace', 
            'plural' => 'az-workspaces', 
            'uc_single' => 'Workspace', 
            'uc_plural' => 'Workspaces',
      ];
      public static function get_instance() {
         if (self::$_instance == null) {
            self::$_instance = new Workspace();
         }

         return self::$_instance;
      }
      
      public function init(){  
         $this->add_cpt(self::$cpt_names);
      }
      
      function admin_init(){
         $this->add_meta_boxes();
         add_action( 'restrict_manage_posts', array($this,'list_filter' ));
         add_filter( 'pre_get_posts', array($this,'filter_list' ));
         add_filter( 'manage_az-workspace_posts_columns', array($this,'columns_head'),10,1);
         add_action( 'manage_az-workspace_posts_custom_column', array($this,'columns_content'), 10, 2);
      }
      
      /***********************
       * Customize edit view
       */
      public function admin_menu(){
         add_submenu_page( Base::$name, __('Workspaces', self::$name), __('Workspaces', self::$name),'manage_options', 'edit.php?post_type=az-workspace', NULL);
      }
      function add_meta_boxes(){

         //add_meta_box("", "Checklist", array($this, "class_checklist_box"),'gs_class', "side", "high");
         add_meta_box("tasks",__('Tasks', self::$name) , [$this, "tasks_box"],self::$cpt_names['single'], "normal", "high");
         
      }
      
      function tasks_box(){
         global $post;
         $args = [
               'numberposts'     => -1,
               'post_type'       => 'az-task',
               'post_parent'     => $post->ID
         ];
         $tasks = get_posts($args);
         if ($tasks && !is_wp_error($tasks)){ ?>
            <ul>
            <?php
                foreach ($tasks as $task){
                    echo '<li><a href="' . get_edit_post_link($task->ID,'') . '" >' .   $task->post_title.'</a> </li> ';
                }
            ?>
            </ul>
         <?php   
         }
         else echo '<p>You do not have any tasks yet.</p>';
      }
      
      /*********************************************
       * Customize list view
       */
      function columns_head($defaults) {   
          $add = array(
            'cb'				=> $defaults['cb'],
            'title'			=> $defaults['title'],
            'time'        => 'Time Logged',
            );
         $defaults = array_merge($add,$defaults);

         return $defaults;
     }
     
     function columns_content($column_name, $post_id) {
         switch($column_name){

            case 'time':
                  // Date filter        
               $time = $this->total_time($post_id);
               if ($time)
                  echo number_format($time/3600,2);
               break;

         }
      }
      
      function list_filter(){

			$type = isset($_GET['post_type'])?$_GET['post_type']:'post';
			if ('az-workspace' !== $type)
            return;

        ?>
        <select name="ws">    
         <option value="">Filter by wordspace</option>
         <?php
          $current_v = isset($_GET['ws']) ? (int)$_GET['ws'] : ''; ?>
          <?php 
          $workspaces = get_posts([
                'post_type'      => 'az-workspace',
                'numberposts'    => -1,
                'orderby'        => 'title',
                'order'          => 'ASC',
          ]);
          foreach ($workspaces as $ws) {
             printf('<option value="%d" %s>%s</option>', $ws->ID, $ws->ID == $current_v ? ' selected' : '', $ws->post_title);
          }
           ?>
         </select>
         <?php
         $this->date_range_filter();
           
		}
     
      public function filter_list( $query ){
         
			global $pagenow;
			
			if ( $query->query['post_type'] == 'az-workspace' && is_admin() && $pagenow=='edit.php' ){
            // filter by workspace
            if (isset($_GET['ws']) && !empty($_GET['ws'])  ) {
               $children = self::get_workspace_children((int)$_GET['ws'], ['az-workspace'], 'ids');
               $children[] = (int)$_GET['ws'];
               $query->query_vars['post__in'] = $children;
            }
            

			}
      }

      function total_time($post_id = null, $total = 0){
         
         if (empty($post_id)){
            global $post;
            $post_id = $post->ID;
         }
         $children = self::get_workspace_children($post_id);
         if ($children):
            $total = 0;
         // check date range first
            if (isset($_GET['start']) && !empty($_GET['start'])) {
               $start = strtotime($_GET['start']);
               $end = empty($end)?strtotime('tomorrow'):strtotime($_GET['end']);
               $date_where = "where start_time > $start and end_time <= $end";
            }
            else {
               $date_where = "where start_time!='' and end_time!=''";
            }
            foreach ($children as $child){
               if ($child->post_type == 'az-task'){
                  // get the time
                  $total += Timeslot::total_time($child->ID, $date_where); 
               }
            }
            return $total;
         endif;
         return 0;
         
      }
      
      /**************
       * Functions 
       */
      
      public static function get_workspaces($orderby = 'title', $order = 'ASC'){
         
         return get_posts([
               'post_type'      => 'az-workspace',
               'numberposts'    => -1,
               'orderby'         => $orderby,
               'order'           => $order,
               'post_parent'     => 0,
         ]);
         
      }
      
      /**
       * Get all children recursively
       * @param int $parent_id
       * @param arr $type
       * @param str $fields
       * @return array of posts
       */
      public static function get_workspace_children($parent_id,$type=['az-workspace','az-task'],$fields = ''){
         $children = array();
         // grab the posts children
         $posts = get_posts([ 
               'numberposts' => -1, 
               'post_status' => 'publish', 
               'post_type' => $type, 
               'post_parent' => $parent_id, 
               'suppress_filters' => true,
               'fields' => $fields,
               'orderby'   => 'title',
               'order'     => 'ASC',
               ]
               );

         // now grab the grand children
         foreach( $posts as $child ){
             // recursion
            $id = (is_object($child))? $child->ID: $child;
             $gchildren = self::get_workspace_children($id, $type, $fields);
             // merge the grand children into the children array
             if( !empty($gchildren) ) {
                 $children = array_merge($children, $gchildren);
             }
         }
         // merge in the direct descendants we found earlier
         $children = array_merge($children,$posts);
         return $children;
     }
     
   public static function dropdown($workspaces = NULL, $value = '', $name = 'workspace',$orderby = 'title', $order = 'ASC', $atts = 'required'){
      if (is_null ($workspaces)){
           $workspaces = self::get_workspaces($orderby, $order);
      }

      if ($workspaces && !is_wp_error($workspaces)){ 
         ob_start();        
         ?><select name='<?php echo $name; ?>' id='<?php echo $name; ?>' <?php echo $atts; ?>>
                <option value= ''>Select a Workspace</option><?php
             foreach ($workspaces as $space){
                ?> <option value ='<?php echo $space->ID; ?>' <?php selected($value,$space->ID); ?>><?php echo $space->post_title; ?></option><?php
                $children = self::get_workspace_children($space->ID, ['az-workspace']);
                if ($children && !is_wp_error($children)){
                   foreach ($children as $child){
                      ?> <option value ='<?php echo $child->ID; ?>' <?php selected($value,$child->ID); ?> class='child-ws'><?php echo $child->post_title; ?></option><?php
                  }
                }
             }
             ?></select><?php
             return ob_get_clean();
      }
      else echo '<p>You do not have any workspaces yet.</p>';
     }
      
      public static function install() {
         
         // add caps
         parent::manage_plugin_caps('add_cap',self::$cpt_names);
 
      }
      

   }
}
$instance = Workspace::get_instance();

