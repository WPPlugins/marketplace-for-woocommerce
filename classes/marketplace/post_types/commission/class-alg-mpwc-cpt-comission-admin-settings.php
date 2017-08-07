<?php
/**
 * Marketplace for WooCommerce - Commission admin settings
 *
 * @version 1.0.0
 * @since   1.0.0
 * @author  Algoritmika Ltd.
 */

if ( ! class_exists( 'Alg_MPWC_CPT_Commission_Admin_Settings' ) ) {
	class Alg_MPWC_CPT_Commission_Admin_Settings {
		/**
		 * @var Alg_MPWC_CPT_Commission
		 */
		private $commission_manager;

		/**
		 * Sets arguments
		 *
		 * @version 1.0.0
		 * @since   1.0.0
		 */
		public function set_args( Alg_MPWC_CPT_Commission $commission_manager ) {
			$this->commission_manager = $commission_manager;
		}

		/**
		 * Gets vendors
		 *
		 * @version 1.0.0
		 * @since   1.0.0
		 */
		public function get_vendors( $field ) {
			$users_with_role = get_users( array(
				'fields' => array( 'id', 'display_name' ),
				'role'   => Alg_MPWC_Vendor_Role::ROLE_VENDOR,
			) );

			if ( is_array( $users_with_role ) && count( $users_with_role ) > 0 ) {
				return wp_list_pluck( $users_with_role, 'display_name', 'id' );
			} else {
				return array();
			}
		}

		/**
		 * Display the sum of commissions values in edit.php page
		 *
		 * Called on Alg_MPWC_CPT_Commission::display_commission_value_column()
		 *
		 * @param $defaults
		 *
		 * @return mixed
		 */
		public function get_total_value_in_edit_columns( $defaults ) {
			global $wp_query;

			$show_total_commissions_value = apply_filters( 'alg_mpwc_show_total_commissions_value', false );

			if ( ! $show_total_commissions_value ) {
				return $defaults;
			}

			$args             = $wp_query->query_vars;
			$args['nopaging'] = true;
			$the_query        = new WP_Query( $args );

			// The Loop
			if ( $the_query->have_posts() ) {
				$total_value = 0;
				while ( $the_query->have_posts() ) {
					$the_query->the_post();
					$total_value += get_post_meta( get_the_ID(), Alg_MPWC_Post_Metas::COMMISSION_FINAL_VALUE, true );
				}

				/* Restore original Post Data */
				wp_reset_postdata();

				$total_value                                             = '<strong>' . wc_price( $total_value ) . '</strong>';
				$defaults[ Alg_MPWC_Post_Metas::COMMISSION_FINAL_VALUE ] = "Value ({$total_value})";
			}
			return $defaults;
		}

		/**
		 * Gets products
		 *
		 * @version 1.0.0
		 * @since   1.0.0
		 */
		public function get_products( $field ) {
			/* @var CMB2_Field $field */

			$args      = array(
				'posts_per_page' => '-1',
				'post_type'      => 'product',
			);
			$object_id = $field->object_id();
			if ( ! empty( $object_id ) ) {
				$author_id = get_post_meta( (int) $object_id, Alg_MPWC_Post_Metas::COMMISSION_AUTHOR_ID, true );
				if ( ! empty( $author_id ) ) {
					$args['author'] = $author_id;
				}
			}
			$posts = get_posts( $args );

			if ( is_array( $posts ) && count( $posts ) > 0 ) {
				return wp_list_pluck( $posts, 'post_title', 'ID' );
			} else {
				return array();
			}
		}

		public function add_commission_status_cmb() {
			$status_tax = new Alg_MPWC_Commission_Status_Tax();

			$cmb_demo = new_cmb2_box( array(
				'id'           => 'alg_mpwc_commissions_status_cmb',
				'title'        => __( 'Status', 'marketplace-for-woocommerce' ),
				'object_types' => array( $this->commission_manager->id ),
				'context'      => 'side',
				'priority'     => 'low',
			) );

			$cmb_demo->add_field( array(
				'id'               => Alg_MPWC_Post_Metas::COMMISSION_STATUS,
				'show_option_none' => false,
				'type'             => 'taxonomy_radio_inline',
				'default'          => 'unpaid',
				'taxonomy'         => $status_tax->id,
				'remove_default'   => 'true',
				'display_cb'       => array( $this, 'display_status_column' ),
				'column'           => array( 'position' => 6, 'name' => 'Status' ),
			) );
		}

		public function display_order_id_column( $field_args, $field ) {
			$order = wc_get_order( (int) $field->escaped_value() );
			if ( $order ) {
				echo apply_filters( 'woocommerce_order_number', $order->get_id(), $order );
			}
		}

		/**
		 * Adds the commission details CMB
		 *
		 * @version 1.0.0
		 * @since   1.0.0
		 */
		public function add_commission_details_cmb() {

			$cmb_demo = new_cmb2_box( array(
				'id'           => 'alg_mpwc_commissions_details_cmb',
				'title'        => __( 'Details', 'marketplace-for-woocommerce' ),
				'object_types' => array( $this->commission_manager->id ),
			) );

			$cmb_demo->add_field( array(
				'name'       => __( 'Order ID', 'marketplace-for-woocommerce' ),
				'desc'       => __( 'Commission order id', 'marketplace-for-woocommerce' ),
				'id'         => Alg_MPWC_Post_Metas::COMMISSION_ORDER_ID,
				'type'       => 'text',
				'attributes' => array(
					'type'  => 'number',
					'style' => 'width: 99%',
				),
				'column'     => array( 'position' => 2 ),
				'display_cb' => array( $this, 'display_order_id_column' ),
			) );

			$cmb_demo->add_field( array(
				'name'       => __( 'Fixed Value', 'marketplace-for-woocommerce' ) . ' (' . get_woocommerce_currency() . ')',
				'desc'       => __( 'Fixed value settled when this commission was created', 'marketplace-for-woocommerce' ) . ' (' . get_woocommerce_currency_symbol() . ')',
				'id'         => Alg_MPWC_Post_Metas::COMMISSION_FIXED_VALUE,
				'type'       => 'text',
				'attributes' => array(
					'step'     => '0.001',
					'type'     => 'number',
					'style'    => 'width: 99%',
					//'readonly' => true,
				),
			) );

			$cmb_demo->add_field( array(
				'name'       => __( 'Percentage', 'marketplace-for-woocommerce' ) . ' (%)',
				'desc'       => __( 'Percentage settled when this commission was created', 'marketplace-for-woocommerce' ) . ' (%)',
				'id'         => Alg_MPWC_Post_Metas::COMMISSION_PERCENTAGE_VALUE,
				'type'       => 'text',
				'attributes' => array(
					'step'     => '0.001',
					'type'     => 'number',
					'style'    => 'width: 99%',
					//'readonly' => true,
				),
			) );

			$cmb_demo->add_field( array(
				'name'       => __( 'Value', 'marketplace-for-woocommerce' ) . ' (' . get_woocommerce_currency() . ')',
				'desc'       => __( 'Final commission value', 'marketplace-for-woocommerce' ) . ' (' . get_woocommerce_currency_symbol() . ')',
				'id'         => Alg_MPWC_Post_Metas::COMMISSION_FINAL_VALUE,
				'type'       => 'text',
				'attributes' => array(
					'step'  => '0.001',
					'type'  => 'number',
					'style' => 'width: 99%',
				),
				'column'     => array( 'position' => 3 ),
				'display_cb' => array( $this, 'display_commission_value_column' ),
			) );

			$cmb_demo->add_field( array(
				'name'          => __( 'Deal', 'marketplace-for-woocommerce' ),
				'desc'          => __( 'Combination of fixed value / percentage settled when this commission was created', 'marketplace-for-woocommerce' ) . ' (%)',
				'id'            => Alg_MPWC_Post_Metas::COMMISSION_DEAL,
				'type'          => 'text',
				//'escape_cb'   => false,
				'save_fields'   => false,
				'render_row_cb' => false,
				'attributes'    => array(
					'step'     => '0.001',
					'type'     => 'number',
					'style'    => 'width: 99%',
					'readonly' => true,
				),
				'column'        => array( 'position' => 4 ),
				'display_cb'    => array( $this, 'display_deal_column' ),
			) );

			$cmb_demo->add_field( array(
				'name'       => __( 'Vendor', 'marketplace-for-woocommerce' ),
				'id'         => Alg_MPWC_Post_Metas::COMMISSION_AUTHOR_ID,
				'type'       => 'pw_select',
				'options_cb' => array( $this, 'get_vendors' ),
				'attributes' => array(
					'style' => 'width: 99%',
				),
				'display_cb' => array( $this, 'display_vendor_column' ),
				'column'     => array( 'position' => 5 ),
			) );

			$cmb_demo->add_field( array(
				'name'       => __( 'Related products', 'marketplace-for-woocommerce' ),
				'id'         => Alg_MPWC_Post_Metas::COMMISSION_PRODUCT_IDS,
				'type'       => 'pw_multiselect',
				'options_cb' => array( $this, 'get_products' ),
				'attributes' => array(
					'style' => 'width: 99%',
				),
				'display_cb' => array( $this, 'display_products_column' ),
				'column'     => array( 'position' => 6 ),
			) );

		}

		/**
		 * Displays the deal settled (percentage + fixed value) when commission was created
		 *
		 * @version 1.0.0
		 * @since   1.0.0
		 *
		 */
		public function display_deal_column( $field_args, $field ) {
			$post_id          = $field->object_id;
			$fixed_value      = get_post_meta( $post_id, Alg_MPWC_Post_Metas::COMMISSION_FIXED_VALUE, true );
			$percentage_value = get_post_meta( $post_id, Alg_MPWC_Post_Metas::COMMISSION_PERCENTAGE_VALUE, true );
			if ( ! empty( $fixed_value ) ) {
				echo wc_price( $fixed_value );

				if ( ! empty( $percentage_value ) ) {
					echo ' + ';
				}
			}

			if ( ! empty( $percentage_value ) ) {
				echo $percentage_value . '%';
			}
		}

		/**
		 * Displays the commission value on post edit column
		 *
		 * @version 1.0.0
		 * @since   1.0.0
		 */
		public function display_commission_value_column( $field_args, $field ) {
			echo wc_price( $field->escaped_value() );
		}

		/**
		 * Displays the products column
		 *
		 * @version 1.0.0
		 * @since   1.0.0
		 */
		public function display_products_column( $field_args, $field ) {
			$values = $field->value;
			wp_reset_postdata();
			if ( is_array( $values ) && count( $values ) > 0 ) {
				$posts       = get_posts( array(
					'post_type'      => 'product',
					'posts_per_page' => - 1,
					'post__in'       => $values,
				) );
				$post_titles = array();
				foreach ( $posts as $post ) {
					$post_titles[] = $post->post_title;
				}
				wp_reset_postdata();
				echo implode( ', ', $post_titles );
			}
		}

		/**
		 * Displays the vendor column
		 *
		 * @version 1.0.0
		 * @since   1.0.0
		 */
		public function display_vendor_column( $field_args, $field ) {
			if ( $field->escaped_value() ) {
				echo get_userdata( $field->escaped_value() )->display_name;
			}
		}

		/**
		 * Displays the status column
		 *
		 * @version 1.0.0
		 * @since   1.0.0
		 */
		public function display_status_column( $field_args, $field ) {
			if ( $field->object_id ) {
				$tax   = new Alg_MPWC_Commission_Status_Tax();
				$terms = wp_get_post_terms( $field->object_id, $tax->id, array( 'fields' => 'names' ) );
				echo implode( ', ', $terms );
			}
		}


	}
}