<?php

/**
 * Exercise: Primary Category Project
 *
 * @link              https://github.com/upeshv/filter-primary-category
 * @since             1.0.0
 * @package           filter_primary_category
 *
 * @wordpress-plugin
 * Plugin Name:       Filter Primary Category
 * Plugin URI:        https://github.com/upeshv/filter-primary-category
 * Description:       Ability to query for posts (and custom post types) based on their primary categories.
 * Version:           1.0.0
 * Requires PHP:      5.6.0
 * Author:            Upesh Vishwakarma
 * Author URI:        https://github.com/upeshv/filter-primary-category
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       tenup-filter-primary-category
 * Domain Path:       /languages
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}

// Plugin version.
if (!defined('TENUP_FPC_VERSION')) {
	define('TENUP_FPC_VERSION', '1.0.0');
}

// Plugin Folder Path.
if (!defined('TENUP_FPC_PLUGIN_DIR')) {
	define('TENUP_FPC_PLUGIN_DIR', plugin_dir_path(__FILE__));
}

// Plugin Folder URL.
if (!defined('TENUP_FPC_PLUGIN_URL')) {
	define('TENUP_FPC_PLUGIN_URL', plugin_dir_url(__FILE__));
}

// Plugin Root File.
if (!defined('TENUP_FPC_PLUGIN_FILE')) {
	define('TENUP_FPC_PLUGIN_FILE', __FILE__);
}

// Minimum PHP Version
if (!defined('MIN_PHP_VER')) {
	define('MIN_PHP_VER', '5.6.0');
}

// Form prefix for plugin
if (!defined('TENUP_FPC'))
		define('TENUP_FPC', 'of');
		

/**
 * Include meta box and plugin settings
 */
include 'includes/Admin/category-meta.php';
include 'includes/Admin/category-settings.php';


class Tenup_fpc_main { 

	private $has_form_posted = false;
	private $hasqmark = false;
	private $urlparams = "/";
	private $tagid = 0;
	private $defaults = array();
	private $frmreserved = array();
	private $frmqreserved = array();
	private $taxonomylist = array();

	public function __construct() {

		// Set up reserved fields
		$this->frmreserved = array(TENUP_FPC."category", TENUP_FPC."submitted", TENUP_FPC."post_types");
		$this->frmqreserved = array(TENUP_FPC."category_name", TENUP_FPC."submitted", TENUP_FPC."post_types"); //same as reserved

		//add primary category options menu
		add_filter('admin_menu', array($this,'tenup_fpc_primary_cat_menu') );

		//add query vars
		add_filter('query_vars', array($this,'add_queryvars') );

		//filter post type & date if it is set
		add_filter('pre_get_posts', array($this,'filter_query_post_types'));

		// Add shortcode support for widgets
		add_shortcode('filterprycat', array($this, 'shortcode'));
		add_filter('widget_text', 'do_shortcode');

		// Check the header to see if the form has been submitted
		add_action( 'get_header', array( $this, 'check_posts' ) );


		add_action('admin_enqueue_scripts', array($this,'tenup_fpc_backend_styles')); //backend scripts and styles
		add_action('admin_enqueue_scripts', array($this,'tenup_fpc_backend_scripts')); //backend scripts and styles
	
		add_action('wp_enqueue_scripts', array($this,'tenup_fpc_frontend_styles')); //public scripts and styles
		add_action('wp_enqueue_scripts', array($this,'tenup_fpc_frontend_scripts')); //public scripts and styles


		register_activation_hook(TENUP_FPC_PLUGIN_FILE, array($this,'plugin_activate')); //activate hook
		register_deactivation_hook(TENUP_FPC_PLUGIN_FILE, array($this,'plugin_deactivate')); //deactivate hook
	}

	//Functions for registering backend stylesheet
	function tenup_fpc_backend_styles()
	{

		wp_enqueue_style('tenup-fpc-main-backend', TENUP_FPC_PLUGIN_URL . '/assets/css/main-backend.css', array(), TENUP_FPC_VERSION, 'all');
	}

	// Functions for registering backend script
	/**
	 * Load JavaScript helper functions
	 * Return false if admin screen is not a post editor screen
	 */
	function tenup_fpc_backend_scripts($hook)
	{
		if (!in_array($hook, array('post.php', 'post-new.php'))) {
			return;
		}

		wp_enqueue_script('tenup-fpc-main-backend-js', TENUP_FPC_PLUGIN_URL . '/assets/js/main-backend.js', array('jquery'), TENUP_FPC_VERSION);
	}

	//Functions for registering frontend stylesheet
	function tenup_fpc_frontend_styles()
	{

		wp_enqueue_style('tenup-fpc-main-frontend', TENUP_FPC_PLUGIN_URL . '/assets/css/main-frontend.css', array(), TENUP_FPC_VERSION, 'all');
	}

	// Functions for registering frontend script
	function tenup_fpc_frontend_scripts()
	{

		wp_enqueue_script('tenup-fpc-main-frontend-js', TENUP_FPC_PLUGIN_URL . '/assets/js/main-frontend.js', array(), TENUP_FPC_VERSION);
	}


	/**
	 * Create plugin options menu
	 */
	public function tenup_fpc_primary_cat_menu() {
		add_options_page(__('Primary Category', 'wp-primary-category'), __('Primary Category', 'wp-primary-category'), 'manage_options', 'tenup_fpc_settings', 'tenup_fpc_settings');
	}
	

	
	public function shortcode($atts, $content = null)
	{
		// extract the attributes into variables
		extract(shortcode_atts(array(
		
			'fields' => null,
			'taxonomies' => null, 
			'submit_label' => null,
			'submitlabel' => null, 
			'types' => "",
			'type' => "", 
			'headings' => "",
			'all_items_labels' => "",
			'class' => "",
			'post_types' => "",
			'hierarchical' => "",
			'hide_empty' => "",
			'order_by' => "",
			'show_count' => "",
			'order_dir' => "",
			'operators' => "",
			'add_search_param' => "0",
			'empty_search_url' => ""
			
		), $atts));
		
		// Fields data
		if($fields!=null)
		{
			$fields = explode(",",$fields);
		}
		else
		{
			$fields = explode(",",$taxonomies);
		}	
		
		// assigning fields to taxonomy array
		$this->taxonomylist = $fields;
		$nofields = count($fields);
		
		$add_search_param = (int)$add_search_param;
		
		
		//init submitlabel
		if($submitlabel!=null)
		{
			//submitlabel has been supplied
			$submit_label = $submitlabel;
			
		}
		else if($submitlabel==null)
		{
			if($submit_label==null)
			{
				//default submitlabel value
				$submit_label = "Submit"; 
			}
		}
		
		//init post_types
		if($post_types!="")
		{
			$post_types = explode(",",$post_types);
		}
		else
		{
			if(in_array("post_types", $fields))
			{
				$post_types = array("all");
			}
			
		}
		
		//init hierarchical
		if($hierarchical!="")
		{
			$hierarchical = explode(",",$hierarchical);
		}
		else
		{
			$hierarchical = array("");
		}
		
		//init hide_empty
		if($hide_empty!="")
		{
			$hide_empty = explode(",",$hide_empty);
		}
		else
		{
			$hide_empty = array("");
		}
		
		//init show_count
		if($show_count!="")
		{
			$show_count = explode(",",$show_count);
		}
		else
		{
			$show_count = array();
		}
		
		//init order_by
		if($order_by!="")
		{
			$order_by = explode(",",$order_by);
		}
		else
		{
			$order_by = array("");
		}
		
		//init order_dir
		if($order_dir!="")
		{
			$order_dir = explode(",",$order_dir);
		}
		else
		{
			$order_dir = array("");
		}
		
		//init operators
		if($operators!="")
		{
			$operators = explode(",",$operators);
		}
		else
		{
			$operators = array("");
		}
		
		
		//init labels
		$labels = explode(",",$headings);
		
		if(!is_array($labels))
		{
			$labels = array();
		}
		
		//init all_items_labels
		$all_items_labels = explode(",",$all_items_labels);
		
		if(!is_array($all_items_labels))
		{
			$all_items_labels = array();
		}
		
		//init types
		if($types!=null)
		{
			$types = explode(",",$types);
		}
		else
		{
			$types = explode(",",$type);
		}
		
		if(!is_array($types))
		{
			$types = array();
		}
		
		
		//Loop through Fields and set up default vars
		for($i=0; $i<$nofields; $i++)
		{
			
			//set up types
			if(isset($types[$i]))
			{
				if(($types[$i]!="select"))
				{
					$types[$i] =  "select"; //use default
				}
			
			}
			else
			{
				//set to default
				$types[$i] =  "select";
			}
			
			//setup labels
			if(!isset($labels[$i]))
			{
				$labels[$i] = "";
			}
			
			//setup all_items_labels
			if(!isset($all_items_labels[$i]))
			{
				$all_items_labels[$i] = "";
			}
			
			
			if(isset($order_by[$i]))
			{
				if(($order_by[$i]!="id")&&($order_by[$i]!="name")&&($order_by[$i]!="slug")&&($order_by[$i]!="count")&&($order_by[$i]!="term_group"))
				{
					$order_by[$i] =  "name"; //use default - possible typo or use of unknown value
				}
			}
			else
			{
				$order_by[$i] =  "name"; //use default
			}
			
			if(isset($order_dir[$i]))
			{
				if(($order_dir[$i]!="asc")&&($order_dir[$i]!="desc"))
				{//then order_dir is not a wanted value
					
					$order_dir[$i] =  "asc"; //set to default
				}
			}
			else
			{
				$order_dir[$i] =  "asc"; //use default
			}
			
			if(isset($operators[$i]))
			{
				$operators[$i] = strtolower($operators[$i]);
				
				if(($operators[$i]!="and")&&($operators[$i]!="or"))
				{
					$operators[$i] =  "and"; //else use default - possible typo or use of unknown value
				}
			}
			else
			{
				$operators[$i] =  "and"; //use default
			}
		}
		
		//set all form defaults / dropdowns etc
		$this->set_defaults();

		return $this->get_tenup_fpc_form($submit_label, $fields, $types, $labels, $hierarchical, $hide_empty, $show_count, $post_types, $order_by, $order_dir, $operators, $all_items_labels, $empty_search_url, $add_search_param, $class);
	}


	function add_queryvars( $qvars )
		{
			$qvars[] = 'post_types';
			return $qvars;
		}


		function filter_query_post_types($query)
		{
			global $wp_query;

			if(($query->is_main_query())&&(!is_admin()))
			{
				if(isset($wp_query->query['post_types']))
				{
					$search_all = false;

					$post_types = explode(",",esc_attr($wp_query->query['post_types']));
					if(isset($post_types[0]))
					{
						if(count($post_types)==1)
						{
							if($post_types[0]=="all")
							{
								$search_all = true;
							}
						}
					}
					if($search_all)
					{
						$post_types = get_post_types( '', 'names' );
						$query->set('post_type', $post_types); //here we set the post types that we want WP to search
					}
					else
					{
						$query->set('post_type', $post_types); //here we set the post types that we want WP to search
					}
				}
			}

			return $query;
		}

		/*
		 * check to set defaults - to be called after the shortcodes have been init so we can grab the wanted list of fields
		*/
		public function set_defaults()
		{
			global $wp_query;
			
			$categories = array();
			if(isset($wp_query->query['category_name']))
			{
				$category_params = (preg_split("/[,\+ ]/", esc_attr($wp_query->query['category_name']))); //explode with 2 delims
							
				
				foreach($category_params as $category_param)
				{
					$category = get_category_by_slug( $category_param );
					if(isset($category->cat_ID))
					{
						$categories[] = $category->cat_ID;
					}
				}
			}
			$this->defaults[TENUP_FPC.'category'] = $categories;


			if ( isset( $wp_query->query ) && is_array( $wp_query->query ) ) {
				//loop through all the query vars
				foreach($wp_query->query as $key=>$val) {
					if(!in_array(TENUP_FPC.$key, $this->frmqreserved))
					{//make sure the get is not a reserved get as they have already been handled above

						//now check it is a desired key
						if(in_array($key, $this->taxonomylist))
						{
							$taxslug = ($val);
							
							$tax_params = (preg_split("/[,\+ ]/", esc_attr($taxslug))); //explode with 2 delims
							
							$taxs = array();
							
							foreach($tax_params as $tax_param)
							{
								$tax = get_term_by("slug",$tax_param, $key);

								if(isset($tax->term_id))
								{
									$taxs[] = $tax->term_id;
								}
							}

							$this->defaults[TENUP_FPC.$key] = $taxs;
						}
					}
				}
			}

			// Post types			
			$post_types = array();
			if(isset($wp_query->query['post_types']))
			{
				$post_types = explode(",",esc_attr($wp_query->query['post_types']));
			}
			$this->defaults[TENUP_FPC.'post_types'] = $post_types;
			
		}




		/*
		 * check to see if form has been submitted and handle vars
		*/

		public function check_posts()
		{
			if(isset($_POST[TENUP_FPC.'submitted']))
			{
				if($_POST[TENUP_FPC.'submitted']==="1")
				{
					//set var to confirm the form was posted
					$this->has_form_posted = true;
				}
			}
			
			$taxcount = 0;
			
			/* CATEGORIES */
			if((isset($_POST[TENUP_FPC.'category']))&&($this->has_form_posted))
			{
				$the_post_cat = ($_POST[TENUP_FPC.'category']);

				//make the post an array for easy looping
				if(!is_array($_POST[TENUP_FPC.'category']))
				{
					$post_cat[] = $the_post_cat;
				}
				else
				{
					$post_cat = $the_post_cat;
				}
				$catarr = array();

				foreach ($post_cat as $cat)
				{
					$cat = esc_attr($cat);
					$catobj = get_category($cat);
					
					if(isset($catobj->slug))
					{
						$catarr[] = $catobj->slug;
					}
				}

				if(count($catarr)>0)
				{
					$operator = "+"; //default behaviour
					
					$categories = implode($operator,$catarr);

					if(get_option('permalink_structure')&&($taxcount==0))
					{
						$category_base = (get_option( 'category_base' )=="") ? "category" : get_option( 'category_base' );
						$category_path = $category_base."/".$categories."/";
						$this->urlparams .= $category_path;
					}
					else
					{
						if(!$this->hasqmark)
						{
							$this->urlparams .= "?";
							$this->hasqmark = true;
						}
						else
						{
							$this->urlparams .= "&";
						}
						
						$this->urlparams .= "category_name=".$categories;
					}
					
					$taxcount++;
				}
				
				
			}
			
			//Double checking that if the search form is related.
			if($this->has_form_posted)
			{
				foreach($_POST as $key=>$val)
				{
					
					if(!in_array($key, $this->frmreserved))
					{
						
						// strip off all prefixes for custom fields - we just want to do a redirect - no processing
						if (strpos($key, TENUP_FPC) === 0)
						{
							$key = substr($key, strlen(TENUP_FPC));
						}
						
						$the_post_tax = $val;
						
						$post_tax = array();
						
						//make the post an array for easy looping
						if(!is_array($the_post_tax))
						{
							$post_tax[] = $the_post_tax;
						}
						else
						{
							$post_tax = $the_post_tax;
						}
						
						$taxarr = array();

						foreach ($post_tax as $tax)
						{
							$tax = esc_attr($tax);
							$taxobj = get_term_by('id', $tax, $key);
							
							if(isset($taxobj->slug))
							{
								$taxarr[] = $taxobj->slug;
							}
						}
						
						if(count($taxarr)>0)
						{
							$operator = "+"; //default behaviour
						
							$taxs = implode($operator,$taxarr);

							//**Since first taxonomy which get rewritten only uses the first value of an array, so doing it manually.
							if(get_option('permalink_structure')&&($taxcount==0))
							{	
								$key_taxonomy = get_taxonomy( $key );
								
								$tax_path = $key."/".$taxs."/";
								if((isset($key_taxonomy->rewrite))&&(isset($key_taxonomy->rewrite['slug'])))
								{
									$tax_path = $key_taxonomy->rewrite['slug']."/".$taxs."/";
								}
										
								$this->urlparams .= $tax_path;
							}
							else
							{
								if(!$this->hasqmark)
								{
									$this->urlparams .= "?";
									$this->hasqmark = true;
								}
								else
								{
									$this->urlparams .= "&";
								}
								$this->urlparams .=  $key."=".$taxs;
							}
							
							$taxcount++;

						}
					}
				}
				
				
			}
		
			
			/* POST TYPES */
			if((isset($_POST[TENUP_FPC.'post_types']))&&($this->has_form_posted))
			{
				$the_post_types = ($_POST[TENUP_FPC.'post_types']);

				//make the post an array for easy looping
				if(!is_array($the_post_types))
				{
					$post_types_arr[] = $the_post_types;
				}
				else
				{
					$post_types_arr = $the_post_types;
				}

				$num_post_types = count($post_types_arr);

				for($i=0; $i<$num_post_types; $i++)
				{
					if($post_types_arr[$i]=="0")
					{
						$post_types_arr[$i] = "all";
					}
				}

				if(count($post_types_arr)>0)
				{
					$operator = ","; //default behaviour
					
					$post_types = implode($operator,$post_types_arr);
					
					if(!$this->hasqmark)
					{
						$this->urlparams .= "?";
						$this->hasqmark = true;
					}
					else
					{
						$this->urlparams .= "&";
					}
					$this->urlparams .= "post_types=".$post_types;

				}
			}
			
			//if the search has been posted, redirect to the newly formed url with all the right params
			if($this->has_form_posted)
			{
			
				// Incase of params are not set than forcing to load search page atleast ortherwise it will redirect to homepage ("/")
				if($this->urlparams=="/")
				{
					$this->urlparams .= "?s=";
				}
				
				// Chekcing if incase add_search_param already added a "?s=" to the url string.
				if($this->urlparams=="/?s=")
				{
					//then redirect to the provided empty search url
					if(isset($_POST[TENUP_FPC.'empty_search_url']))
					{	
						wp_redirect(esc_url($_POST[TENUP_FPC.'empty_search_url']));
						exit;
					}				
				}
				wp_redirect((home_url().$this->urlparams));
				exit;
			}
			
		}



		public function get_tenup_fpc_form($submitlabel, $fields, $types, $labels, $hierarchical, $hide_empty, $show_count, $post_types, $order_by, $order_dir, $operators, $all_items_labels, $empty_search_url, $add_search_param, $class)
		{
			$returnvar = '';

			$addclass = "";
			if($class!="")
			{
				$addclass = ' '.$class;
			}

			$returnvar .= '
				<form action="" method="post" class="filterprimarycat'.$addclass.'">
					<div>';

					// If incase post types is not added than we are using an hidden attribute
					if(!in_array("post_types", $fields))
					{
						if(($post_types!="")&&(is_array($post_types)))
						{
							foreach($post_types as $post_type)
							{
								$returnvar .= "<input type=\"hidden\" name=\"".TENUP_FPC."post_types[]\" value=\"".esc_attr($post_type)."\" />";
							}
						}
					}
					$returnvar .= '
						<ul>';

						$i = 0;
						
						//special cases
						foreach($fields as $field)
						{					
							//build field array
							if($field == "post_types") 
							{
								$returnvar .= $this->build_post_type_element($types, $labels, $post_types, $field, $all_items_labels, $i);

							}
							else
							{	
								$returnvar .= $this->build_taxonomy_element($types, $labels, $field, $hierarchical, $hide_empty, $show_count, $order_by, $order_dir, $operators, $all_items_labels, $i);
							}
							$i++;

						}

						$returnvar .='<li>';
						
						if($add_search_param==1)
						{
							$returnvar .= "<input type=\"hidden\" name=\"".TENUP_FPC."add_search_param\" value=\"1\" />";
						}
						
						if($empty_search_url!="")
						{
							$returnvar .= "<input type=\"hidden\" name=\"".TENUP_FPC."empty_search_url\" value=\"".esc_url($empty_search_url)."\" />";
						}
						
						
						$returnvar .=
							'<input type="hidden" name="'.TENUP_FPC.'submitted" value="1">
							<input type="submit" value="'.esc_attr($submitlabel).'">
						</li>';

						$returnvar .= "</ul>";
					$returnvar .= '</div>
				</form>';

			return $returnvar;
		}



		function build_post_type_element($types, $labels, $post_types, $field, $all_items_labels, $i)
		{
			$returnvar = "";
			$taxonomychildren = array();
			$post_type_count = count($post_types);

			//Checking the post types array
			if(is_array($post_types))
			{
				if(($post_type_count==1)&&($post_types[0]=="all"))
				{
					$args = array('public'   => true);
					$output = 'object'; // names or objects, note names is the default
					$operator = 'and'; // 'and' or 'or'

					$post_types_objs = get_post_types( $args, $output, $operator );

					$post_types = array();

					foreach ( $post_types_objs  as $post_type )
					{
						if($post_type->name!="attachment")
						{
							$tempobject = array();
							$tempobject['term_id'] = $post_type->name;
							$tempobject['cat_name'] = $post_type->labels->name;

							$taxonomychildren[] = (object)$tempobject;

							$post_types[] = $post_type->name;

						}
					}
					$post_type_count = count($post_types_objs);

				}
				else
				{
					foreach($post_types as $post_type)
					{
						$post_type_data = get_post_type_object( $post_type );

						if($post_type_data)
						{
							$tempobject = array();
							$tempobject['term_id'] = $post_type;
							$tempobject['cat_name'] = $post_type_data->labels->name;

							$taxonomychildren[] = (object)$tempobject;
						}
					}
				}
			}
			$taxonomychildren = (object)$taxonomychildren;

			$returnvar .= "<li>";

			$post_type_labels = array();
			$post_type_labels['name'] = "Post Types";
			$post_type_labels['singular_name'] = "Post Type";
			$post_type_labels['search_items'] = "Search Post Types";
			
			if($all_items_labels[$i]!="")
			{
				$post_type_labels['all_items'] = $all_items_labels[$i];
			}
			else
			{
				$post_type_labels['all_items'] = "All Post Types";
			}

			$post_type_labels = (object)$post_type_labels;

			if($labels[$i]!="")
			{
				$returnvar .= "<h4>".$labels[$i]."</h4>";
			}
			
			if($post_type_count>0)
			{
				$defaultval = implode(",",$post_types);
			}
			else
			{
				$defaultval = "all";
			}

			if($types[$i]=="select")
			{
				$returnvar .= $this->generate_select($taxonomychildren, $field, $this->tagid, $post_type_labels, $defaultval);
			}
			$returnvar .= "</li>";
			
			return $returnvar;
		}



		//gets all the data for the taxonomy then display as form element
		function build_taxonomy_element($types, $labels, $taxonomy, $hierarchical, $hide_empty, $show_count, $order_by, $order_dir, $operators, $all_items_labels, $i)
		{
			$returnvar = "";
			
			$taxonomydata = get_taxonomy($taxonomy);

			if($taxonomydata)
			{
				$returnvar .= "<li>";
				
				if($labels[$i]!="")
				{
					$returnvar .= "<h4>".$labels[$i]."</h4>";
				}

				$args = array(
					'fpc_name' => TENUP_FPC . $taxonomy,
					'taxonomy' => $taxonomy,
					'hierarchical' => false,
					'child_of' => 0,
					'echo' => false,
					'hide_if_empty' => false,
					'hide_empty' => true,
					'order' => $order_dir[$i],
					'orderby' => $order_by[$i],
					'show_option_none' => '',
					'show_count' => '0',
					'show_option_all' => '',
					'show_option_all_fpc' => ''
				);
				
				if(isset($hierarchical[$i]))
				{
					if($hierarchical[$i]==1)
					{
						$args['hierarchical'] = true;
					}
				}
				
				if(isset($hide_empty[$i]))
				{
					if($hide_empty[$i]==0)
					{
						$args['hide_empty'] = false;
					}
				}
				
				if(isset($show_count[$i]))
				{
					if($show_count[$i]==1)
					{
						$args['show_count'] = true;
					}
				}
				
				if($all_items_labels[$i]!="")
				{
					$args['show_option_all_fpc'] = $all_items_labels[$i];
				}
				
				
				
				$taxonomychildren = get_categories($args);

				if($types[$i]=="select")
				{
					$returnvar .= $this->generate_wp_dropdown($args, $taxonomy, $this->tagid, $taxonomydata->labels);
				}
				
				//check to see if operator is set for this field
				if(isset($operators[$i]))
				{
					$operators[$i] = strtolower($operators[$i]);
					
					if(($operators[$i]=="and")||($operators[$i]=="or"))
					{
						$returnvar .= '<input type="hidden" name="'.esc_attr(TENUP_FPC.$taxonomy).'_operator" value="'.esc_attr($operators[$i]).'" />';
					}
				}
				
				$returnvar .= "</li>";
			}
			
			return $returnvar;
		}


		//use wp array walker to enable hierarchical display
		public function generate_wp_dropdown($args, $name, $currentid = 0, $labels = null, $defaultval = "0")
		{
			$args['name'] = $args['fpc_name'];
			
			$returnvar = '';
			
			if($args['show_option_all_fpc']=="")
			{
				$args['show_option_all'] = $labels->all_items != "" ? $labels->all_items : 'All ' . $labels->name;
			}
			else
			{
				$args['show_option_all'] = $args['show_option_all_fpc'];
			}
			
			if(isset($this->defaults[TENUP_FPC.$name]))
			{
				$defaults = $this->defaults[TENUP_FPC . $name];
				if (is_array($defaults)) {
					if (count($defaults) == 1) {
						$args['selected'] = $defaults[0];
					}
				}
				else {
					$args['selected'] = $defaultval;
				}
			}

			$returnvar .= wp_dropdown_categories($args);

			return $returnvar;
		}

		//generate generic form inputs for use elsewhere, such as post types and non taxonomy fields
		public function generate_select($dropdata, $name, $currentid = 0, $labels = null, $defaultval = "0")
		{
			$returnvar = "";

			$returnvar .= '<select class="postform" name="'.TENUP_FPC.$name.'">';
			if(isset($labels))
			{
				if($labels->all_items!="")
				{//check to see if all items has been registered in field then use this label
					$returnvar .= '<option class="level-0" value="'.$defaultval.'">'.$labels->all_items.'</option>';
				}
				else
				{//check to see if all items has been registered in field then use this label with prefix of "All"
					$returnvar .= '<option class="level-0" value="'.$defaultval.'">All '.$labels->name.'</option>';
				}
			}

			foreach($dropdata as $dropdown)
			{
				$selected = "";

				if(isset($this->defaults[TENUP_FPC.$name]))
				{
					$defaults = $this->defaults[TENUP_FPC.$name];

					$noselected = count($defaults);

					if(($noselected==1)&&(is_array($defaults))) //there should never be more than 1 default in a select, if there are then don't set any, user is obviously searching multiple values, in the case of a select this must be "all"
					{
						foreach($defaults as $defaultid)
						{
							if($defaultid==$dropdown->term_id)
							{
								$selected = ' selected="selected"';
							}
						}
					}
				}
				$returnvar .= '<option class="level-0" value="'.$dropdown->term_id.'"'.$selected.'>'.$dropdown->cat_name.'</option>';

			}
			$returnvar .= "</select>";

			return $returnvar;
		}

}


if ( class_exists( 'Tenup_fpc_main' ) )
{
	global $tenup_fpc_main;
	$tenup_fpc_main = new Tenup_fpc_main;
}

/*
* Includes
*/


