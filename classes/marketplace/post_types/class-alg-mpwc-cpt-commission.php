<?php
/**
 * Marketplace for WooCommerce - Commission custom post type
 *
 * @version 1.0.0
 * @since   1.0.0
 * @author  Algoritmika Ltd.
 */

if ( ! class_exists( 'Alg_MPWC_CPT_Commission' ) ) {
	class Alg_MPWC_CPT_Commission {

		// Custom post type args
		protected $labels;
		protected $args;
		public $id = 'alg_mpwc_commission';

		/**
		 * Manages the creation of commissions
		 *
		 * @var Alg_MPWC_CPT_Commission_Creator
		 */
		public $comission_creator;

		// Comission value from admin
		public $commission_fixed_value = '';
		public $commission_percentage_value = '';

		// Automatic creation option from admin
		public $automatic_creation = array();

		/**
		 * Gives full permissions to manage_woocommerce roles.
		 *
		 * Called when saving vendor admin settings and on plugin activation
		 *
		 * @version 1.0.0
		 * @since   1.0.0
		 */
		public static function gives_all_caps_to_roles() {
			$caps = array(
				'read_alg_mpwc_commission',
				'delete_alg_mpwc_commission',
				'edit_alg_mpwc_commissions',
				'edit_others_alg_mpwc_commissions',
				'publish_alg_mpwc_commissions',
				'read_private_alg_mpwc_commissions',
				'delete_alg_mpwc_commissions',
				'delete_private_alg_mpwc_commissions',
				'delete_published_alg_mpwc_commissions',
				'delete_others_alg_mpwc_commissions',
				'edit_private_alg_mpwc_commissions',
				'edit_published_alg_mpwc_commissions',
			);

			$editable_roles = get_editable_roles();
			foreach ( $editable_roles as $role_key => $details ) {
				$role_obj = get_role( $role_key );
				if ( ! $role_obj->has_cap( 'manage_woocommerce' ) ) {
					continue;
				}
				foreach ( $caps as $cap ) {
					if ( ! $role_obj->has_cap( $cap ) ) {
						$role_obj->add_cap( $cap );
					}
				}
			}
		}

		/**
		 * Setups the post type
		 *
		 * @version 1.0.0
		 * @since   1.0.0
		 */
		public function setup() {
			$this->set_args();
			$this->get_values_from_admin();
			$this->handle_automatic_creation();
			add_action( 'cmb2_admin_init', array( $this, 'add_custom_meta_boxes' ) );
			add_action( 'admin_init', array( $this, 'remove_add_new_from_menu' ) );
			add_filter( 'manage_'.$this->id.'_posts_columns', array( $this, 'display_total_value_in_edit_columns' ), 999 );
			add_action( 'restrict_manage_posts', array( $this, 'create_vendor_filter' ), 10 );
			add_action( 'restrict_manage_posts', array( $this, 'create_status_filter' ), 10 );

			// Bulk actions
			add_filter( "bulk_actions-edit-{$this->id}", array( $this, 'bulk_actions_create' ) );
			add_filter( "handle_bulk_actions-edit-{$this->id}", array( $this, 'bulk_actions_handle' ), 10, 3 );
			add_action( 'admin_notices', array( $this, 'notify_about_bulk_action' ) );

			// Setup role options based on admin settings
			$id      = 'alg_mpwc';
			$section = 'vendors';
			add_action( "woocommerce_update_options_{$id}_{$section}", array( __CLASS__, 'gives_all_caps_to_roles' ) );
		}

		/**
		 * Notifies the user about the bulk actions results
		 *
		 * @version 1.0.0
		 * @since   1.0.0
		 */
		public function notify_about_bulk_action() {
			if ( ! empty( $_REQUEST['alg_mpwc_commissions_paid'] ) ) {
				$count = intval( $_REQUEST['alg_mpwc_commissions_paid'] );
				printf( '<div id="message" class="notice notice-success is-dismissible"><p>' .
				        _n( 'Set %s commission as paid.',
					        'Set %s commissions as paid.',
					        $count,
					        'marketplace-for-woocommerce'
				        ) . '</p></div>', $count );
			}

			if ( ! empty( $_REQUEST['alg_mpwc_commissions_unpaid'] ) ) {
				$count = intval( $_REQUEST['alg_mpwc_commissions_unpaid'] );
				printf( '<div id="message" class="notice notice-success is-dismissible"><p>' .
				        _n( 'Set %s commission as unpaid.',
					        'Set %s commissions as unpaid.',
					        $count,
					        'marketplace-for-woocommerce'
				        ) . '</p></div>', $count );
			}
		}

		/**
		 * Handle the bulk actions.
		 *
		 * Sets all selected commissions as paid or unpaid
		 *
		 * @version 1.0.0
		 * @since   1.0.0
		 */
		function bulk_actions_handle( $redirect_to, $doaction, $post_ids ) {
			$status_tax = new Alg_MPWC_Commission_Status_Tax();

			if ( $doaction == 'alg_mpwc_set_selected_to_paid' ) {
				$term = get_term_by( 'slug', 'paid', $status_tax->id );
				if ( is_array( $post_ids ) && count( $post_ids ) > 0 ) {
					$redirect_to = add_query_arg( 'alg_mpwc_commissions_paid', count( $post_ids ), $redirect_to );
				}
			} else if ( $doaction == 'alg_mpwc_set_selected_to_unpaid' ) {
				$term = get_term_by( 'slug', 'unpaid', $status_tax->id );
				if ( is_array( $post_ids ) && count( $post_ids ) > 0 ) {
					$redirect_to = add_query_arg( 'alg_mpwc_commissions_unpaid', count( $post_ids ), $redirect_to );
				}

			} else {
				return $redirect_to;
			}

			foreach ( $post_ids as $post_id ) {
				wp_set_object_terms( $post_id, $term->term_id, $status_tax->id );
			}

			return $redirect_to;
		}

		/**
		 * Creates two more bulk actions labels on bulk actions dropdown
		 *
		 * @version 1.0.0
		 * @since   1.0.0
		 */
		public function bulk_actions_create( $bulk_actions ) {
			if ( current_user_can( Alg_MPWC_Vendor_Role::ROLE_VENDOR ) ) {
				return $bulk_actions;
			}
			$bulk_actions['alg_mpwc_set_selected_to_paid']   = __( 'Set selected commissions as paid', 'marketplace-for-woocommerce' ).' ';
			$bulk_actions['alg_mpwc_set_selected_to_unpaid'] = __( 'Set selected commissions as unpaid', 'marketplace-for-woocommerce' ).' &nbsp;';
			return $bulk_actions;
		}

		/**
		 * Creates a status filter on commissions edit.php page
		 *
		 * @version 1.0.0
		 * @since   1.0.0
		 *
		 * @param $post_type
		 */
		public function create_status_filter( $post_type ) {
			if ( $post_type != $this->id ) {
				return;
			}

			$status_tax = new Alg_MPWC_Commission_Status_Tax();
			wp_dropdown_categories( array(
				'show_option_all' => 'Select a status',
				'name'            => $status_tax->id,
				'taxonomy'        => $status_tax->id,
				'selected'        => get_query_var( $status_tax->id ),
				'value_field'     => 'slug'
			) );
		}

		/**
		 * Creates a dropdown filter to show commissions from a specific vendor user
		 *
		 * @version 1.0.0
		 * @since   1.0.0
		 *
		 * @param $post_type
		 * @param $which
		 */
		public function create_vendor_filter( $post_type ) {
			if ( $post_type != $this->id ) {
				return;
			}

			$show_dropdown = apply_filters( 'alg_mpwc_show_commissions_by_vendor_filter', false );
			if ( ! $show_dropdown ) {
				return;
			}

			$dropdown = new Alg_MPWC_Vendor_Products_Filter();
			echo $dropdown->get_html( array(
				'get_dropdown_only' => true,
			) );
		}

		/**
		 * Displays the commission value on post edit column
		 *
		 * @version 1.0.0
		 * @since   1.0.0
		 */
		public function display_total_value_in_edit_columns($defaults){
			$admin_settings = new Alg_MPWC_CPT_Commission_Admin_Settings();
			$admin_settings->set_args($this);
			$defaults = $admin_settings->get_total_value_in_edit_columns($defaults);
			return $defaults;
		}

		/**
		 * Removes add new from left menu
		 *
		 * @version 1.0.0
		 * @since   1.0.0
		 */
		public function remove_add_new_from_menu(){
			global $submenu;
			unset($submenu['edit.php?post_type='.$this->id.''][10]);
		}

		/**
		 * Creates custom meta boxes for commissions
		 *
		 * @version 1.0.0
		 * @since   1.0.0
		 */
		public function add_custom_meta_boxes() {
			$admin_settings = new Alg_MPWC_CPT_Commission_Admin_Settings();
			$admin_settings->set_args( $this );
			$admin_settings->add_commission_details_cmb();
			$admin_settings->add_commission_status_cmb();
		}

		/**
		 * Gets values from admin
		 *
		 * @version 1.0.0
		 * @since   1.0.0
		 */
		protected function get_values_from_admin() {
			//$this->comission_base     = sanitize_text_field( get_option( Alg_MPWC_Settings_Vendor::OPTION_COMMISSIONS_BASE ) );
			$this->commission_fixed_value      = sanitize_text_field( get_option( Alg_MPWC_Settings_Vendor::OPTION_COMMISSIONS_FIXED_VALUE ) );
			$this->commission_percentage_value = sanitize_text_field( get_option( Alg_MPWC_Settings_Vendor::OPTION_COMMISSIONS_PERCENTAGE_VALUE ) );
			$this->automatic_creation          = get_option( Alg_MPWC_Settings_Vendor::OPTION_COMMISSIONS_AUTOMATIC_CREATION );
		}

		/**
		 * Handles automatic commissions creation
		 *
		 * @version 1.0.0
		 * @since   1.0.0
		 */
		protected function handle_automatic_creation() {
			$commissions_creator     = new Alg_MPWC_CPT_Commission_Creator();
			$this->comission_creator = $commissions_creator;
			$commissions_creator->set_args( $this );
			$commissions_creator->handle_automatic_creation();
		}

		/**
		 * Setups the arguments for creating the post type
		 *
		 * @version 1.0.0
		 * @since   1.0.0
		 */
		public function set_args() {
			$labels = array(
				'name'               => __( 'Commissions', 'marketplace-for-woocommerce' ),
				'singular_name'      => __( 'Commission', 'marketplace-for-woocommerce' ),
				'menu_name'          => __( 'Commissions', 'marketplace-for-woocommerce' ),
				'name_admin_bar'     => __( 'Commission', 'marketplace-for-woocommerce' ),
				'add_new'            => __( 'Add New', 'marketplace-for-woocommerce' ),
				'add_new_item'       => __( 'Add New Commission', 'marketplace-for-woocommerce' ),
				'new_item'           => __( 'New Commission', 'marketplace-for-woocommerce' ),
				'edit_item'          => __( 'Edit Commission', 'marketplace-for-woocommerce' ),
				'view_item'          => __( 'View Commission', 'marketplace-for-woocommerce' ),
				'all_items'          => __( 'Commissions', 'marketplace-for-woocommerce' ),
				'search_items'       => __( 'Search Commissions', 'marketplace-for-woocommerce' ),
				'parent_item_colon'  => __( 'Parent Commissions:', 'marketplace-for-woocommerce' ),
				'not_found'          => __( 'No Commissions found.', 'marketplace-for-woocommerce' ),
				'not_found_in_trash' => __( 'No Commissions found in Trash.', 'marketplace-for-woocommerce' ),
			);

			$args = array(
				'labels'             => $labels,
				'description'        => __( 'Description.', 'marketplace-for-woocommerce' ),
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'query_var'          => false,
				'rewrite'            => array( 'slug' => 'commission' ),
				'capability_type'    => 'alg_mpwc_commission',
				'capabilities'       => array(
					'create_posts' => 'manage_woocommerce',
				),
				'map_meta_cap'       => true,
				'has_archive'        => false,
				'hierarchical'       => false,
				'menu_position'      => null,
				'menu_icon'          => 'dashicons-cart',
				'supports'           => array( 'title' ),
			);

			$this->labels = $labels;
			$this->args   = $args;
		}

		/**
		 * Registers the post type
		 *
		 * @version 1.0.0
		 * @since   1.0.0
		 */
		public function register() {
			$args = $this->args;
			register_post_type( $this->id, $args );
		}

	}
}