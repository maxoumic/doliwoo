<?php
/* Copyright (C) 2013-2014 Cédric Salvador <csalvador@gpcsolutions.fr>
 * Copyright (C) 2015 Maxime Lafourcade <mlafourcade@gpcsolutions.fr>
 * Copyright (C) 2015 Raphaël Doursenaud <rdoursenaud@gpcsolutions.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * DoliWoo settings
 *
 * WooCommerce settings integration.
 */

if ( ! class_exists( 'WC_Integration_Doliwoo_Settings' ) ) :

	/**
	 * Doliwoo settings WooCommerce integration
	 */
	class WC_Integration_Doliwoo_Settings extends WC_Integration {
		/**
		 * Init and hook in the integration.
		 */
		public function __construct() {
			$this->id                 = 'doliwoo';
			$this->method_title       = __( 'Doliwoo Settings', 'doliwoo' );
			$this->method_description = __( 'Dolibarr webservices access', 'doliwoo' );

			// Load the settings
			$this->init_form_fields();
			$this->init_settings();

			// Define user set variables
			$this->webservs_url = $this->get_option( 'webservs_url' );

			$this->delay_update = $this->get_option('delay_update');

			$this->dolibarr_key = $this->get_option( 'dolibarr_key' );
			$this->sourceapplication
			                      = $this->get_option( 'sourceapplication' );
			$this->dolibarr_login = $this->get_option( 'dolibarr_login' );
			$this->dolibarr_password
			                      = $this->get_option( 'dolibarr_password' );
			$this->dolibarr_entity
			                      = $this->get_option( 'dolibarr_entity' );
			$this->dolibarr_category_id
			                      = $this->get_option( 'dolibarr_category_id' );
			$this->dolibarr_generic_id
			                      = $this->get_option( 'dolibarr_generic_id' );

			// Actions
			add_action( 'woocommerce_update_options_integration_' . $this->id,
				array( $this, 'process_admin_options' ) );
		}

		/**
		 * Initialize integration settings form fields.
		 *
		 * @return void
		 */
		public function init_form_fields() {
			$this->form_fields = array(
				'sourceapplication'    => array(
					'title'       => __( 'Source application', 'doliwoo' ),
					'description' => __( 'How this application will identify itself to the webservice.', 'doliwoo' ),
					'type'        => 'text',
					'desc_tip'    => false,
					'default'     => 'WooCommerce'
				),
				'webservs_url'         => array(
					'title'       => __( 'URL', 'doliwoo' ),
					'description' => __( 'Enter Dolibarr webservices root URL (i.e. https://mydolibarr.com/webservices)',
						'doliwoo' ),
					'type'        => 'text',
					'desc_tip'    => false,
					'default'     => ''
				),
				'delay_update' => array(
					'title'             => __('Delay','doliwoo' ),
					'description'       => __('Choice the time of the automatically update' ),
					'type'              => 'select',
					'desc_tip'          => false,
					'options'           => array(
						'hourly'  => __('Once Hourly'),
						'twicedaily'  => __('Twice Daily'),
						'daily'  => __('Once Daily'),
					),
				),
				'dolibarr_key'         => array(
					'title'       => __( 'Key', 'doliwoo' ),
					'description' => __( 'Enter your Dolibarr webservices key', 'doliwoo' ),
					'type'        => 'text',
					'desc_tip'    => false,
					'default'     => ''
				),
				'dolibarr_login'       => array(
					'title'       => __( 'User login', 'doliwoo' ),
					'description' => __( 'Dolibarr actions will be done as this user', 'doliwoo' ),
					'type'        => 'text',
					'desc_tip'    => false,
					'default'     => ''
				),
				'dolibarr_password'    => array(
					'title'    => __( 'User password', 'doliwoo' ),
					'type'     => 'password',
					'desc_tip' => false,
					'default'  => ''
				),
				// TODO: do a 2 step configuration procedure and try to get these parameters in a human readable form from the SOAP webservice
				'dolibarr_entity'      => array(
					'title'       => __( 'Entity', 'doliwoo' ),
					'description' => __( 'If you\'re using ulticompany: the ID of the entity you want to integrate. Leave to 1 otherwise.', 'doliwoo' ),
					'type'        => 'text',
					'desc_tip'    => false,
					'default'     => 1
				),
				'dolibarr_category_id' => array(
					'title'       => __( 'Product category', 'doliwoo' ),
					'description' => __( 'The ID of the product category you want to automatically import products from.', 'doliwoo' ),
					'type'        => 'text',
					'desc_tip'    => false,
					'default'     => ''
				),
				'dolibarr_generic_id'  => array(
					'title'       => __( 'Generic thirdparty', 'doliwoo' ),
					'description' => __( 'The ID of the thirdparty that\'ll be used for anonymous orders.', 'doliwoo' ),
					'type'        => 'text',
					'desc_tip'    => false,
					'default'     => ''
				),
			);
		}

		/**
		 * Check if the fields URL Webservs is HTTPS
		 *
		 * @param string $key The form setting
		 *
		 * @return string The form value
		 */
		public function validate_webservs_url_field( $key ) {
			$value = $_POST['woocommerce_doliwoo_webservs_url'];

			// FIXME: move to a sanitization method
			// Make sure we have the trailing slash
			$value = trailingslashit($value);

			// Make sure we use HTTPS
			if ( ( substr( $value, 0, 8 ) ) !== 'https://' ) {
				$this->errors[] = 'The protocol to use is https://';
			}

			// Check that the server is available
			try {
				new SoapClient($value . 'server_other.php?wsdl');
			} catch ( SoapFault $exc ) {
				$this->errors[] = 'The webservice is not available. Please check the URL.';
			}

			return $value;
		}

		/**
		 * Display HTTPS is needed
		 *
		 * @return void
		 */
		public function display_errors() {
			foreach ( $this->errors as $key => $value ) {
				?>
				<div class="error">
					<p><b>
					<?php
						_e( $value, 'doliwoo' );
					?>
					</b></p>
				</div>
			<?php
			}
		}
	}
endif;
