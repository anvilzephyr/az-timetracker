<?php
namespace AZTimeTracker;
/*
class/base.php
*/

if (  ! class_exists( 'AZTimeTracker\\Base' ) ){
   
   class Base {

      private static $_instance = null;
      protected static $name = 'az-time';
      protected static $cpt_names = [  ];
      protected $taxonomy_names = [  ];
      public static function get_instance() {
         if ( self::$_instance == null ) {
            self::$_instance = new Base();
         }

         return self::$_instance;
      }
      
      public function __construct(){
         $this->add_hooks();
         $this->manage_plugin_caps( 'add_cap',[ 'single'=>'az-task','plural'=>'az-tasks' ] );
      }
      
      private function add_hooks(){
         add_action ( 'admin_menu', [ $this, 'admin_menu' ] );
         add_action( 'init',[ $this,'init' ] );
         add_action( 'admin_init',[ $this,'admin_init' ] );
         add_action(  'wp_dashboard_setup',array( $this, 'add_dashboard_widgets'  ) );
         
        // add_action(  'save_post', [ $this, 'save_custom_data' ]  );
          
      }
      
      public function admin_menu(){

         add_menu_page( 'AZ Time', 'AZ Time', 'manage_options',Base::$name,[ $this,'settings_page' ], null );
         
         remove_submenu_page( Base::$name,Base::$name );

      }
      
      public function init(){
         
      }
      
      public function admin_init(){
        
      }
      
      public function settings_page(){
         echo 'There are no settings yet';
      }
      
      protected function add_cpt( $names ){
         
         $args = [ 
               'labels'	=>	[ 
                  'name'         => $names[ 'uc_plural' ],
                  'all_items'           => $names[ 'uc_plural' ],
                  'add_new'               => 'Add New',
                  'add_new_item'          => 'Add New '.$names[ 'uc_single' ],
						'menu_name'             =>	$names[ 'uc_plural' ],
						'singular_name'       =>	$names[ 'uc_single' ],
					 	'edit_item'           =>	'Edit '.$names[ 'uc_single' ],
					 	'new_item'            =>	'New '.$names[ 'uc_single' ],
					 	'view_item'           =>	'View '.$names[ 'uc_single' ],
					 	'items_archive'       =>	$names[ 'uc_plural' ],
					 	'search_items'        =>	'Search '.$names[ 'uc_plural' ],
					 	'not_found'	      		=>	'None found',
					 	'not_found_in_trash'  =>	'None found in trash',
					 ],
               'public'             => true,
               'show_in_menu'       => 'edit.php?post_type='.$names[ 'single' ],
               'query_var'          => false,
               'capability_type'    => 'az-task',
               'map_meta_cap'       => true,
               'has_archive'        => false,
			   'publicly_queryable' => false,
               'can_export'         => true,
               'hierarchical'       => $names[ 'single' ]=='az-timeslot'?false:true,
               'supports'           => [ 'title','author','editor','thumbnail','page-attributes' ],
             ];

            register_post_type(  $names[ 'single' ] , $args  );
            
            
      }
      
      public static function manage_plugin_caps( $action = 'add_cap',$names ){
			if (   ! function_exists( 'get_editable_roles' )  ) {
            require_once(  ABSPATH . '/wp-admin/includes/user.php'  );
         }
			$roles = get_editable_roles();
			$admin_caps = array( 
               'create_'.$names[ 'single' ],
               'edit_'.$names[ 'single' ],
               'edit_'.$names[ 'plural' ],
               'edit_others_'.$names[ 'plural' ],
               'bulk_edit_'.$names[ 'plural' ],
               'publish_'.$names[ 'plural' ],
               'read_private_'.$names[ 'plural' ],
               'delete_'.$names[ 'plural' ],
               'delete_others_'.$names[ 'plural' ],
               'approve_'.$names[ 'plural' ],
               'view_'.$names[ 'single' ],
                );
			foreach ( $GLOBALS[ 'wp_roles' ]->role_objects as $key => $role ) {
				if ( isset( $roles[ $key ] ) ) {     	
					if ( $key == 'administrator' ){
						foreach ( $admin_caps as $c ){
							if ( $action=='add_cap' )
								{$role->add_cap(  $c  );}
							else  {$role->{$action}(  $c  );}
						} 
					}
				}
			}
		}
      
      public function add_taxonomy( $names,$cpt ) {

	      register_taxonomy(   
	          $names[ 'single' ], 
	          $cpt,        
               [  
                    'hierarchical' 	=> true,    
                    'query_var' 		=> true,
                    'show_ui'			=> true,
                    'show_admin_column' => false,
                    'rewrite' 		=> array( 
                        'slug' 			=> $names[ 'single' ], 
                        'with_front' 	=> true, 
                     ),
                    'show_tagcloud' => false,
                    'sort'			=> true,
                    'labels' => array( 
                        'name' => _x(  $names[ 'uc_single' ], self::$name  ),
                        'singular_name' => _x(  $names[ 'uc_single' ], self::$name  ),
                        'search_items' =>  __(  'Search '.$names[ 'uc_plural' ]  ),
                        'all_items' => __(  'All '.$names[ 'uc_plural' ]  ),
                        'parent_item' => __(  'Parent '.$names[ 'uc_plural' ]  ),
                        'parent_item_colon' => __(  'Parent '.$names[ 'uc_single' ].':'  ),
                        'edit_item' => __(  'Edit '.$names[ 'uc_single' ]  ),
                        'update_item' => __(  'Update '.$names[ 'uc_single' ]  ),
                        'add_new_item' => __(  'Add New '.$names[ 'uc_single' ]  ),
                        'new_item_name' => __(  'New '.$names[ 'uc_single' ].' Name'  ),
                        'menu_name' => __(  $names[ 'uc_plural' ] ),
                       )
                 ]
             );  
      }
      
      /**********************
       * Dashboard widgets
       */
      public function add_dashboard_widgets(){
         wp_add_dashboard_widget( 
                 'open_timeslots',         // Widget slug.
                 'Open Timeslots',         // Title.
                 array( $this,'open_timeslots_widget' ) // Display function.
         );
        wp_add_dashboard_widget( 
                 'recent_tasks',         
                 'Recent Tasks',        
                 array( $this,'recent_tasks_widget' ) 
         );

      }
      
      public function open_timeslots_widget(){
         if (  ! is_super_admin() ){
            $where = " and `created_by` = ".get_current_user_id();
         }
         else {
            $where = '';
         }
         global $wpdb;
         $date = date( 'Y-m-d',strtotime( 'tomorrow' ) );
         $sql = "select p.ID,m1.meta_value as start_time,post_author,post_parent from $wpdb->posts p join $wpdb->postmeta m1 on ( m1.post_id=p.ID and p.post_type='az-timeslot' and m1.meta_key='start_time' ) where p.ID not in ( select post_id from $wpdb->postmeta where meta_key='end_time' and meta_value  != '' )";

         $records = $wpdb->get_results( $sql );

         if ( $records ):
            ?>
            <div class='inner'>
           
           <table class='widefat striped'>
               <tr><th>User</th><th>Task</th><th>Started</th><th>&nbsp;</th></tr>
          <?php
         foreach ( $records as $row ){
            $task = get_the_title(  $row->post_parent  );
            echo "<tr id='$row->ID' >"
               . "<td>$row->post_author</td>"
                  . "<td><a href='post.php?post=$row->post_parent&action=edit' target='blank'>$task</a>" 
                  . "<td>".date( 'F j h:s',$row->start_time ). "</td>"
                  . "<td><button class='time-btn' data-action='end_time' data-task='$row->ID'>End Time</button></td>"
            . "</tr>";
         }
         ?>
       </table>
       </div>
       <button type="button" data-div='fu_past_due' class='print-box' >Print</button>
       <script>
          jQuery( document ).ready( function(){
            jQuery( '.print-box' ).click( function(){
            var div_id = jQuery( this ).data( 'div' );
            jQuery( '#'+div_id+' tr' ).removeClass( 'hidden' );
            var mywindow = window.open( '', 'Print Box', 'height=500,width=700' );
            mywindow.document.write( '<html><head><title>Golf Squad Dashboard Data</title></head>' );
            mywindow.document.write( '<body>'+jQuery( '#'+div_id ).html()+'</body></html>' );
            mywindow.print();
            mywindow.close();
            jQuery( '#'+div_id+' tr.notes' ).addClass( 'hidden' );
             } );
          } );
          
          </script>
         <?php
         endif;
      }
      
      function recent_tasks_widget(){
         $posts = get_posts( [ 
               'post_type'    => [ 'az-task' ],
               'numberposts'  => apply_filters( 'az_recent_tasks_count',12 ),
               'meta_key'     => 'last_activity',
               'orderby'      => 'meta_value',
    
               
          ] );

         echo "<div class='inner'>" ; ?>
         <a href='/wp-admin/post-new.php?post_type=az-task' class='btn btn-large'>Create New Task</a>
         <?php
         if ( $posts &&  ! is_wp_error( $posts ) ){
            $i = 1;
            echo "<table class='widefat striped'>";
            foreach ( $posts as $post ){
               //if ( $i >20 ) break;
               $parent = get_the_title( $post->post_parent );
               $parent_link = get_edit_post_link( $post->post_parent, '' );
               $last = get_post_meta( $post->ID,'last_activity',true );
               $date = empty( $last )?'':date(  'M d H:i', strtotime($last)  );
               echo "<tr>"
               . "<td>[ <a href='$parent_link' target='blank'> $parent </a> ]<a href='".get_edit_post_link( $post->ID )."' target='blank'> $post->post_title $date</a></td>"
                     . "<td>";
               if ( $results = Timeslot::has_open_timeslot( $post->ID ) ){ 
                  //echo "<span style='color:red'>".date( 'D h:i',$results->start_time ); 
                  ?>
                  <button class='time-btn' data-action='end_time' data-task='<?php echo $results->timeslot; ?>' data-redirect="true">End Time</button>
               <?php }
               else { ?>
                  <button class='time-btn' data-action="start_time"  data-task='<?php echo $post->ID; ?>' data-redirect="true">Start Time</button> 
               <?php }
               echo "</td>"
               . "<tr>";
               $i++;
            }
            echo "</table>";
         }
         echo "</div>";
      }
      
      /**
       * Adds filter by date range to list views
       */
      protected function date_range_filter(){
         // date ranges
         list( $start, $end  ) = $this->get_date_range();
         ?>
         <input type='date' name='start' id='week-start' value='<?php echo $start; ?>'/>
         <input type='date' name='end' id='week-end' value='<?php echo $end; ?>' />
         <a id='set-today' > Today </a> 
         <a id='set-week' > This week </a>
         <a id='set-month' > This month </a>
         <a id='set-last' > Last month </a>
         <a id='clear-week' > Clear Range </a>
         
         <?php
      }
      
      /**
       * get the start and end dates from query string
       * @return array
       */
      protected function get_date_range( $format = 'Y-m-d' ){
         
         if ( isset( $_GET[ 'start' ] ) &&  ! empty( $_GET[ 'start' ] ) ) {
               $start = date( $format,strtotime( sanitize_text_field( $_GET[ 'start' ] ) ) );
               $end = empty( $_GET[ 'end' ] )?date( $format,strtotime( 'tomorrow' ) ):date( $format,strtotime( sanitize_text_field( $_GET[ 'end' ] ).'+ 1 day' ) );

         }
         else {
            $start = '';
            $end = date( $format, current_time( 'timestamp' ) );
         }

         return [ $start, $end ];
            
      }
      
      public function save_custom_data( $post_id ){
         
         var_dump( self::$cpt_names );die();
         
         if ( get_post_type( $post_id ) == rgar( self::$cpt_names, 'single' ) &&  ! wp_is_post_revision(  $post_id  ) && did_action( 'save_post' ) ==1 ){
            if(  isset( $_POST ) && isset( $_POST[ 'parent_id' ] )  ){
               global $wpdb;
               $wpdb->update( 
                     [ 
                           'ID'  => ( int )$post_id, 
                           'post_parent' => ( int )$_POST[ 'parent_id' ] 
                      ]
                      );
            }
         }
      }
      
      protected function print_button( $start='', $end='', $subheading='' ){
         ?>
         <button type="button" data-div='the-list' class='print-box' data-start="<?php echo $start; ?>" data-end="<?php echo $end; ?>" data-sub="<?php echo $subheading; ?>">Print</button>
         <?php
      }

      public static function install() {
         
         Task::install();
         Workspace::install();
 
      }
      
       public static function uninstall() {
         if ( empty( $_POST ) || empty( $_POST[ 'delete' ] ) ){
            // popup to see if we need to delete the data
         ?><script>
            if ( window.confirm( "Delete all timeslots? This deletes the entire timeslot table and all timeslots. If you are not sure, click 'Cance' as you can always delete it later manually." ) ){
               jQuery.post( 
                  ajaxurl,
                  {'action':'uninstall','delete'=>2},
                  function( response ){
                      alert ( response );
                  }
               );
            }
         </script>
         <?php
         }
         elseif ( $_POST[ 'delete' ] == 2 ) {
            global $wpdb;
            if ( $wpdb->query( "DROP TABLE ".Timeslot::$tablename ) )
                  echo 'Data deleted';
            else echo 'There was an issue deleting the table. Maybe it was already deleted. Please verify manually.';
            $posts = get_posts( [ 
                  'post_type'       => [ 'az-workspace','az-task' ],
                  'numberposts'     => -1,
                  'fields'          => 'ids',
                  ''
             ] );
            if ( $posts ){
               $deleted = 0;
               foreach( $posts as $post_id ){
                  echo $post_id.' will be deleted';
                  //wp_delete_post( $post_id );
                  $deleted ++;
               }
               echo $deleted. ' posts deleted';
            }
            self::manage_plugin_caps( 'remove_cap',Workspace::$cpt_names );
            self::manage_plugin_caps( 'remove_cap',Task::$cpt_names );
            wp_die();
         }
 
      }

      /**
       * Upload documents to various post types
       * @global obj $post
       * @global obj $wpdb
       */
      public static function doc_box( $post ){ 
      ?>
         
         <div>
             <label for="file_url">Upload File</label>
             <input type="text" name="az-document" id="file_url" class="med-text">
             <input type="button" name="upload-btn" id="upload-btn" class="button-secondary" value="Upload File">
         </div>

         <?php
         global $wpdb;
         $docs = $wpdb->get_results($wpdb->prepare("SELECT meta_id,post_id,meta_key,meta_value FROM $wpdb->postmeta WHERE post_id=%d and meta_key LIKE 'az-document' ORDER BY meta_id ASC", $post->ID));
         if ($docs){
            ?><h4>Uploaded Files:</h4><?php
            echo "<ul>";
            foreach ($docs as $doc){
               $paths = explode('/',$doc->meta_value);
               $date = $wpdb->get_var($wpdb->prepare("SELECT post_date FROM $wpdb->posts WHERE post_type='attachment' and guid=%s LIMIT 1", $doc->meta_value));
               echo "<li><a href='{$doc->meta_value}' target='_blank' >$date ".array_pop($paths)."</a>";
               //<input type='checkbox' value='{$doc->meta_id}' name='remove_doc[]'/>Detach</li>";
            }
            echo "</ul>";
         }
      }
      
      public function save_metabox_data($post_id){
         if ( wp_is_post_revision( $post_id ) ) {
            return;
        }
         if (!empty( $_POST['az-document'] )){
            $this->upload_document($post_id, $_POST['az-document'] );
         }
      }
      
      public function upload_document($post_id, $url){
         if (empty($url))
            return;
         return add_post_meta($post_id,'az-document',filter_var($url, FILTER_SANITIZE_URL));
      }
   }// end class
}
$aztt = Base::get_instance();
